<?php
/**
 * Legal-page scrub — strips the registered entity name and the company phone
 * number out of page content, leaving only the DBA and the email address.
 *
 * Run via WP-CLI:  wp eval-file scripts/legal-entity-scrub.php [dry-run] [revisions]
 *
 * (Options are bare words, not --flags: WP-CLI consumes unknown --flags
 * itself and they never reach eval-file scripts.)
 *
 *   dry-run     Print planned changes without writing.
 *   revisions   ALSO scrub post revisions. OFF by default: revisions are not
 *               publicly served, and revision 106 is the only surviving copy of
 *               the long-form Refund and Returns Policy that is still queued to
 *               replace the short live page 13. Scrubbing it is fine — it only
 *               rewrites the entity name — but do it deliberately, not by
 *               accident.
 *
 * Enforces docs/COMPLIANCE invariants 1 (no legal entity name user-facing) and
 * 2 (no phone number, no tel: links — contact is email only).
 *
 * Writes byte-exact via $wpdb rather than wp_update_post(): under WP-CLI there
 * is no logged-in user, so kses filters are active and wp_update_post() would
 * rewrite the stored HTML. Every original body is dumped to a JSON backup file
 * before the first write; the path is printed at the end.
 *
 * Idempotent — a second run finds nothing to do.
 */

/** @var array $args WP-CLI eval-file injects positional args into scope. */
if (!isset($args) || !is_array($args)) {
    fwrite(STDERR, "Run via WP-CLI: wp eval-file scripts/legal-entity-scrub.php [dry-run]\n");
    exit(1);
}
$opts          = array_map(fn($a) => ltrim((string) $a, '-'), $args);
$dry           = in_array('dry-run', $opts, true);
$do_revisions  = in_array('revisions', $opts, true);

global $wpdb;

$ENTITY = 'Elytherion LLC';
$DBA    = 'Navigate Peptides';

/**
 * Literal replacements. strtr() with an array always matches the LONGEST key
 * at each position, so the DBA parenthetical and the possessive are consumed
 * before the bare fallback — without that we would emit "Navigate Peptides
 * (doing business as Navigate Peptides…)" and "Navigate Peptides's".
 */
$LQ = "\u{201C}";  // “
$RQ = "\u{201D}";  // ”
$AP = "\u{2019}";  // ’
$literals = [
    // "<entity> (doing business as Navigate Peptides, "we", "us", or "our")"
    $ENTITY . ' (doing business as ' . $DBA . ', "we", "us", or "our")'
        => $DBA . ' ("we", "us", or "our")',
    // Smart-quote variant, in case a later edit was made in the block editor.
    $ENTITY . ' (doing business as ' . $DBA . ', ' . $LQ . 'we' . $RQ . ', ' . $LQ . 'us' . $RQ . ', or ' . $LQ . 'our' . $RQ . ')'
        => $DBA . ' (' . $LQ . 'we' . $RQ . ', ' . $LQ . 'us' . $RQ . ', or ' . $LQ . 'our' . $RQ . ')',
    // Any other "doing business as" phrasing.
    $ENTITY . ' (doing business as ' . $DBA . ')' => $DBA,
    // Possessive: the DBA already ends in "s", so it takes a bare apostrophe.
    $ENTITY . "'s"      => $DBA . "'",
    $ENTITY . $AP . 's' => $DBA . $AP,
    // Bare entity name, and the name without the suffix.
    $ENTITY           => $DBA,
    'Elytherion, LLC' => $DBA,
    'Elytherion'      => $DBA,
];

/** Regex replacements, applied after the literals. */
$patterns = [
    // Unwrap tel: anchors first so the number inside is left as plain text
    // for the phone pattern below to remove.
    '~<a\b[^>]*href=(["\'])tel:[^"\']*\1[^>]*>(.*?)</a>~is' => '$2',
    // The company number in any punctuation, plus a trailing <br> and the
    // newline that precedes it, so the contact block closes up cleanly.
    '~\n?[ \t]*\+?\s*1?\s*[\(\[]?\s*619\s*[\)\]]?[\s.\-]*665[\s.\-]*2694[ \t]*(?:<br\s*/?>)?~i' => '',
];

// ---------------------------------------------------------------------------

$like_entity = '%' . $wpdb->esc_like('Elytherion') . '%';
$like_phone  = '%' . $wpdb->esc_like('665-2694') . '%';

$sql = "SELECT ID, post_title, post_type, post_status, post_content, post_excerpt
        FROM {$wpdb->posts}
        WHERE post_content LIKE %s OR post_excerpt LIKE %s OR post_title LIKE %s
           OR post_content LIKE %s OR post_excerpt LIKE %s";
$rows = $wpdb->get_results($wpdb->prepare(
    $sql, $like_entity, $like_entity, $like_entity, $like_phone, $like_phone
));

if (!$rows) {
    WP_CLI::success('Nothing to scrub — no post contains the entity name or the phone number.');
} else {
    $backup      = [];
    $backup_path = sys_get_temp_dir() . '/legal-scrub-backup-' . gmdate('Ymd-His') . '.json';
    $changed     = 0;
    $skipped     = 0;

    WP_CLI::log($dry ? '=== DRY RUN — no writes ===' : '=== APPLYING ===');

    foreach ($rows as $row) {
        $is_revision = ($row->post_type === 'revision');
        if ($is_revision && !$do_revisions) {
            $skipped++;
            continue;
        }

        $fields = [];
        foreach (['post_content', 'post_excerpt', 'post_title'] as $field) {
            $before = $row->$field;
            $after  = strtr($before, $literals);
            foreach ($patterns as $re => $to) {
                $after = preg_replace($re, $to, $after);
                if ($after === null) {
                    WP_CLI::error("preg_replace failed on post {$row->ID} ({$field}): " . preg_last_error_msg());
                }
            }
            if ($after !== $before) {
                $fields[$field] = $after;
            }
        }

        if (!$fields) {
            continue;
        }

        $n_entity = substr_count(strtolower($row->post_content . $row->post_title), 'elytherion');
        $n_phone  = preg_match_all('~665[\s.\-]*2694~', $row->post_content . $row->post_title);
        WP_CLI::log(sprintf(
            '  #%-5d %-9s %-8s %-38s  entity:%d phone:%d  fields: %s',
            $row->ID, $row->post_type, $row->post_status,
            mb_substr($row->post_title, 0, 38), $n_entity, $n_phone,
            implode(', ', array_keys($fields))
        ));

        if (!$dry) {
            $backup[$row->ID] = [
                'post_title'   => $row->post_title,
                'post_content' => $row->post_content,
                'post_excerpt' => $row->post_excerpt,
            ];
            $ok = $wpdb->update($wpdb->posts, $fields, ['ID' => $row->ID]);
            if ($ok === false) {
                WP_CLI::error("DB update failed on post {$row->ID}: {$wpdb->last_error}");
            }
            clean_post_cache($row->ID);
        }
        $changed++;
    }

    if (!$dry && $backup) {
        if (file_put_contents($backup_path, wp_json_encode($backup, JSON_PRETTY_PRINT)) === false) {
            WP_CLI::warning("Could not write backup to {$backup_path}");
        } else {
            WP_CLI::log("Backup of original bodies: {$backup_path}");
        }
    }

    WP_CLI::log(sprintf('%s %d post(s)%s.',
        $dry ? 'Would change' : 'Changed', $changed,
        $skipped ? ", skipped {$skipped} revision(s) (pass 'revisions' to include)" : ''
    ));
}

// --- Sweep the rest of the DB so nothing hides outside wp_posts -------------

WP_CLI::log('--- scanning other tables ---');
$sweeps = [
    'wp_options (option_value)' => "SELECT option_name AS k FROM {$wpdb->options} WHERE option_value LIKE %s OR option_value LIKE %s",
    'wp_postmeta (meta_value)'  => "SELECT CONCAT(post_id,':',meta_key) AS k FROM {$wpdb->postmeta} WHERE meta_value LIKE %s OR meta_value LIKE %s",
    'wp_termmeta (meta_value)'  => "SELECT CONCAT(term_id,':',meta_key) AS k FROM {$wpdb->termmeta} WHERE meta_value LIKE %s OR meta_value LIKE %s",
    'wp_terms / taxonomy'       => "SELECT name AS k FROM {$wpdb->terms} WHERE name LIKE %s OR name LIKE %s",
];
$found_elsewhere = false;
foreach ($sweeps as $label => $q) {
    $hits = $wpdb->get_col($wpdb->prepare($q, $like_entity, $like_phone));
    if ($hits) {
        $found_elsewhere = true;
        WP_CLI::warning(sprintf('%s — %d hit(s): %s', $label, count($hits), implode(', ', array_slice($hits, 0, 10))));
    } else {
        WP_CLI::log("  clean: {$label}");
    }
}
if ($found_elsewhere) {
    WP_CLI::warning('Hits outside wp_posts are NOT auto-fixed — inspect and clear them by hand.');
}

// --- Verify ----------------------------------------------------------------

if (!$dry) {
    $left = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE %s OR post_title LIKE %s OR post_content LIKE %s",
        $like_entity, $like_entity, $like_phone
    ));
    if ($left > 0) {
        WP_CLI::warning("{$left} post(s) still match — re-run with 'revisions' if those are revisions, else inspect.");
    } else {
        WP_CLI::success('Verified: no post contains the entity name or the phone number.');
    }
}
