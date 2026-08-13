<?php
/**
 * Push the version-controlled Terms of Service body onto the live Terms page.
 *
 * Run via WP-CLI:  wp eval-file scripts/terms-update.php <html-file> [dry-run]
 *
 * (Options are bare words, not --flags: WP-CLI consumes unknown --flags
 * itself and they never reach eval-file scripts.)
 *
 *   <html-file>   Path to the page body, normally docs/legal/terms-of-service.html
 *   dry-run       Report what would change without writing.
 *
 * Targets whatever page `woocommerce_terms_page_id` points at — that is the
 * page behind the "I accept the Terms and Conditions" checkbox at checkout —
 * rather than a hard-coded ID, so the two can never drift apart. Falls back to
 * the page with slug `terms`.
 *
 * Writes byte-exact via $wpdb rather than wp_update_post(): under WP-CLI there
 * is no logged-in user, so kses filters are active and would rewrite the stored
 * HTML. The previous body is dumped to a JSON backup before the write.
 *
 * Idempotent — re-running with unchanged content is a no-op.
 *
 * See docs/legal/terms-coverage-map.md and docs/PROCESSOR_FEEDBACK_2026-07
 * (item 1) for what this content is and why it reads the way it does.
 */

/** @var array $args WP-CLI eval-file injects positional args into scope. */
if (!isset($args) || !is_array($args) || empty($args[0])) {
    fwrite(STDERR, "Run via WP-CLI: wp eval-file scripts/terms-update.php <html-file> [dry-run]\n");
    exit(1);
}
$file = $args[0];
$opts = array_map(fn($a) => ltrim((string) $a, '-'), array_slice($args, 1));
$dry  = in_array('dry-run', $opts, true);

if (!is_readable($file)) {
    WP_CLI::error("Cannot read {$file}");
}
$new = file_get_contents($file);
if ($new === false || trim($new) === '') {
    WP_CLI::error("{$file} is empty or unreadable — refusing to blank the Terms page.");
}

global $wpdb;

// --- Resolve the target page ------------------------------------------------

$page_id = (int) get_option('woocommerce_terms_page_id');
$source  = 'woocommerce_terms_page_id';
if (!$page_id) {
    $page    = get_page_by_path('terms');
    $page_id = $page ? (int) $page->ID : 0;
    $source  = 'slug "terms"';
}
if (!$page_id) {
    WP_CLI::error('Could not resolve the Terms page — set woocommerce_terms_page_id or create /terms.');
}

$post = get_post($page_id);
if (!$post || $post->post_type !== 'page') {
    WP_CLI::error("Resolved ID {$page_id} is not a page.");
}
WP_CLI::log(sprintf('Target: #%d "%s" (/%s) — resolved via %s, status %s',
    $page_id, $post->post_title, $post->post_name, $source, $post->post_status));

$old = $post->post_content;

// --- Sanity-check the incoming body before it goes anywhere near the DB -----

$checks = [
    'legal entity name'    => '~Elytherion~i',
    'phone number'         => '~\+?1?\s*[\(\[]?619[\)\]]?[\s.\-]*665[\s.\-]*2694~i',
    'tel: link'            => '~href=(["\'])tel:~i',
    'unfilled placeholder' => '~MerchantWebsite|\[Merchant Name\]|\[insert\]|To be inserted~i',
];
$blocked = false;
foreach ($checks as $label => $re) {
    if (preg_match($re, $new)) {
        WP_CLI::warning("Incoming content contains a {$label} — refusing to publish.");
        $blocked = true;
    }
}
if ($blocked) {
    WP_CLI::error('Aborted on compliance pre-check. Fix ' . basename($file) . ' and re-run.');
}

// The two client-mandated slots must survive any future edit to the file.
$required = [
    'client RUO clause'  => 'intended strictly for laboratory research and research use only (RUO)',
    'refund RMA contact' => 'shipping@navigatepeptides.com',
    'clickwrap clause'   => 'clicking "Place Order" at checkout',
];
foreach ($required as $label => $needle) {
    if (!str_contains($new, $needle)) {
        WP_CLI::error("Incoming content is missing the {$label} — refusing to publish.");
    }
}
WP_CLI::log('Pre-checks passed: no entity name, no phone, no placeholders, required clauses present.');

// --- Apply ------------------------------------------------------------------

if ($old === $new) {
    WP_CLI::success('Live content already matches the file — nothing to do.');
    return;
}

WP_CLI::log(sprintf('%s: %d bytes -> %d bytes (%+d), sections %d -> %d',
    $dry ? 'Would change' : 'Changing',
    strlen($old), strlen($new), strlen($new) - strlen($old),
    preg_match_all('~<h2>~', $old), preg_match_all('~<h2>~', $new)
));

if ($dry) {
    WP_CLI::log('=== DRY RUN — no writes ===');
    return;
}

$backup_path = sys_get_temp_dir() . '/terms-backup-' . gmdate('Ymd-His') . '.json';
$backup      = ['ID' => $page_id, 'post_title' => $post->post_title, 'post_content' => $old];
if (file_put_contents($backup_path, wp_json_encode($backup, JSON_PRETTY_PRINT)) === false) {
    WP_CLI::error("Could not write backup to {$backup_path} — aborting rather than writing unbacked.");
}
WP_CLI::log("Backup of previous body: {$backup_path}");

$ok = $wpdb->update($wpdb->posts, ['post_content' => $new], ['ID' => $page_id]);
if ($ok === false) {
    WP_CLI::error("DB update failed: {$wpdb->last_error}");
}
clean_post_cache($page_id);

// --- Verify -----------------------------------------------------------------

$stored = get_post($page_id)->post_content;
if ($stored !== $new) {
    WP_CLI::error('Post-write verification FAILED — stored content does not match the file.');
}
WP_CLI::success(sprintf('Terms page #%d updated and verified byte-exact (%d bytes).', $page_id, strlen($stored)));
