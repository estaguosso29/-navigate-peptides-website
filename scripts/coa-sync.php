<?php
/**
 * COA catalog sync — August 2026 Azoth certificate wiring.
 *
 * Run via WP-CLI:  wp eval-file scripts/coa-sync.php <coa-map.json> [dry-run] [rename-slugs]
 *
 * <coa-map.json> maps product slug -> that product's OWN certificate URL.
 * Each product links only its own certificate (client 2026-08-10); the
 * compiled catalogue PDF is no longer used, so its index page — which listed
 * every supplier product incl. the real GLP names and 12 'No current COA'
 * rows — is no longer reachable from the site.
 *
 * (Options are bare words, not --flags: WP-CLI consumes unknown --flags
 * itself and they never reach eval-file scripts.)
 *
 * Idempotent: products are addressed by slug, every write is a plain
 * update, and re-running against an already-synced site is a no-op.
 *
 *   <coa-map.json>  slug => per-product certificate URL.
 *   --dry-run       Print planned changes without writing.
 *   --rename-slugs  ALSO rename GLP slugs (tirzepatide → glp-1-t,
 *                   retatrutide → glp-3-r). OFF by default — the client's
 *                   written instruction is to keep existing URLs. Core
 *                   wp_old_slug_redirect 301s the old slugs if enabled.
 */

/** @var array $args WP-CLI eval-file injects positional args into scope. */
if (!isset($args) || !is_array($args)) {
    fwrite(STDERR, "Run via WP-CLI: wp eval-file scripts/coa-sync.php <url> [dry-run]\n");
    exit(1);
}
if (empty($args[0]) || !is_readable($args[0])) {
    WP_CLI::error('First argument must be a readable coa-map.json (slug => certificate URL).');
}
$coa_map = json_decode((string) file_get_contents($args[0]), true);
if (!is_array($coa_map) || !$coa_map) {
    WP_CLI::error('coa-map.json did not decode to a non-empty array.');
}
$opts    = array_map(fn($a) => ltrim((string) $a, '-'), array_slice($args, 1));
$dry          = in_array('dry-run', $opts, true);
$rename_slugs = in_array('rename-slugs', $opts, true);

// slug => [testing lab, batch/lot, purity]
$wire = [
    'bpc-157'        => ['BioViridian',         '07022026-004',    '99.74%'],
    'bpc-157-tb-500' => ['Freedom Diagnostics', '261807',          '99.76%'],
    'epithalon'      => ['Freedom Diagnostics', '261807',          '99.61%'],
    'ghk-cu'         => ['BioViridian',         '07022026-022',    '99.84%'],
    'kpv'            => ['Freedom Diagnostics', '261807',          '99.04%'],
    'mots-c'         => ['Freedom Diagnostics', '261807',          '99.06%'],
    'nad'            => ['Freedom Diagnostics', '261807',          '99.94%'],
    'tesamorelin'    => ['Freedom Diagnostics', '261807',          '99.78%'],
    'tirzepatide'    => ['Optiq Health Labs',   '0330.OPT.260729', '99.99%'],
    'retatrutide'    => ['BioViridian',         '07022026-001',    '99.71%'],
];

// Products with no matching certificate — hidden until one exists.
// Semax and Selank are hidden because the only certificates cover the
// N-acetylated variants (N-Acetyl Semax p21, N-Acetyl & Selank p20), which are
// different compounds from the plain peptides sold under these slugs. Awaiting
// the client's decision; see docs/Azoth_Catalog_COAs_Compiled.md.
$hide = ['selank', 'semax', 'cjc-1295-no-dac'];

// Client-mandated coded display names for GLP compounds. Applied to the
// title AND to visible text (content/excerpt); slugs only with --rename-slugs.
$coded = [
    'tirzepatide' => ['title' => 'GLP-1 T', 'slug' => 'glp-1-t'],
    'retatrutide' => ['title' => 'GLP-3 R', 'slug' => 'glp-3-r'],
];
$name_map = [
    'Tirzepatide' => 'GLP-1 T', 'tirzepatide' => 'GLP-1 T', 'TIRZEPATIDE' => 'GLP-1 T',
    'Retatrutide' => 'GLP-3 R', 'retatrutide' => 'GLP-3 R', 'RETATRUTIDE' => 'GLP-3 R',
    'Semaglutide' => 'GLP-1 S', 'semaglutide' => 'GLP-1 S', 'SEMAGLUTIDE' => 'GLP-1 S',
];

// 3D vial models had the compound name baked into the label texture AND the
// filename. Re-textured copies ship with the theme under coded filenames;
// the old files are deleted, so stale meta would 404 the model-viewer.
$model_renames = [
    'vial-tirzepatide-10mg.glb' => 'vial-glp-1-t-10mg.glb',
    'vial-tirzepatide-5mg.glb'  => 'vial-glp-1-t-5mg.glb',
    'vial-retatrutide-10mg.glb' => 'vial-glp-3-r-10mg.glb',
    'vial-retatrutide-5mg.glb'  => 'vial-glp-3-r-5mg.glb',
];

$find = function (string $slug): ?WP_Post {
    // Explicit status list — 'any' silently excludes drafts (WP skips
    // statuses with exclude_from_search), so re-runs would miss hidden products.
    $posts = get_posts([
        'name' => $slug, 'post_type' => 'product',
        'post_status' => ['publish', 'draft', 'pending', 'private'],
        'numberposts' => 1,
    ]);
    return $posts[0] ?? null;
};

$log = function (string $msg) use ($dry) {
    WP_CLI::log(($dry ? '[dry-run] ' : '') . $msg);
};

$missing = [];

foreach ($wire as $slug => [$lab, $lot, $purity]) {
    $p = $find($slug);
    if (!$p) { $missing[] = $slug; continue; }
    $url = $coa_map[$slug] ?? null;
    if (!$url) { $missing[] = "{$slug}: no certificate URL in coa-map.json"; continue; }
    $log("wire  {$slug} (#{$p->ID}): " . basename(parse_url($url, PHP_URL_PATH))
         . ", lab={$lab}, lot={$lot}, purity={$purity}");
    if ($dry) continue;
    update_post_meta($p->ID, '_nav_coa_pdf', esc_url_raw($url));
    update_post_meta($p->ID, '_nav_testing_lab', $lab);
    update_post_meta($p->ID, '_nav_batch_number', $lot);
    update_post_meta($p->ID, '_nav_purity', $purity);
}

foreach ($hide as $slug) {
    $p = $find($slug);
    if (!$p) { $missing[] = $slug; continue; }
    if ($p->post_status === 'draft') { $log("hide  {$slug}: already draft"); continue; }
    $log("hide  {$slug} (#{$p->ID}): publish → draft");
    if ($dry) continue;
    wp_update_post(['ID' => $p->ID, 'post_status' => 'draft']);
}

foreach ($coded as $slug => $names) {
    $p = $find($slug) ?? $find($names['slug']);   // re-run after a slug rename
    if (!$p) { $missing[] = $slug; continue; }
    $update  = ['ID' => $p->ID];
    $changes = [];

    if ($p->post_title !== $names['title']) {
        $update['post_title'] = $names['title'];
        $changes[] = "title → {$names['title']}";
    }
    $content = strtr($p->post_content, $name_map);
    $excerpt = strtr($p->post_excerpt, $name_map);
    if ($content !== $p->post_content) { $update['post_content'] = $content; $changes[] = 'content de-named'; }
    if ($excerpt !== $p->post_excerpt) { $update['post_excerpt'] = $excerpt; $changes[] = 'excerpt de-named'; }
    if ($rename_slugs && $p->post_name !== $names['slug']) {
        $update['post_name'] = $names['slug'];
        $changes[] = "slug → {$names['slug']} (old slug 301s via core)";
    }

    $model = (string) get_post_meta($p->ID, '_nav_3d_model_url', true);
    $model_new = strtr($model, $model_renames);
    if ($model_new !== $model) {
        $changes[] = 'model → ' . basename($model_new);
        if (!$dry) update_post_meta($p->ID, '_nav_3d_model_url', $model_new);
    }

    if (!$changes) { $log("code  {$slug}: already applied"); continue; }
    $log("code  {$slug} (#{$p->ID}): " . implode(', ', $changes));
    if ($dry) continue;
    wp_update_post($update);
}

/* -----------------------------------------------------------------
 * Variation size fixes (client 2026-08-09: follow the supplier store).
 *
 * NAD+ sold "5000mg/10000mg" — matching nothing: certificates are 500/1000mg,
 * the supplier sells 200/500mg, and the unused 500mg/1000mg pa_size terms show
 * the intended values were mistyped ×10. Worse, the parent declared taxonomy
 * attribute pa_size while its variations carry attribute_amount meta, so a
 * customer's size selection never actually matched a variation. Converted to
 * the custom "Amount" attribute every other variable product here uses.
 *
 * Tesamorelin sold "5mg" — no 5mg exists in the certificates (10/20mg) or the
 * supplier store (2/10mg); renamed to the certified 10mg.
 * ----------------------------------------------------------------*/
// Client decision (reaffirmed 2026-08-09): peptideclub.com is authoritative
// for sizes. NAD+ therefore becomes 200/500mg (store-literal); the 200mg has
// no certificate yet and shows "pending" until her next stock arrives. The
// 200mg price is the store's own, per her "follow what they do" for gaps.
// Maps cover both origin states: fresh prod (5000/10000) and a DB where the
// earlier 500/1000 fix already ran.
$size_fixes = [
    'nad'         => [
        'map'    => ['5000mg' => '500mg', '10000mg' => '200mg', '1000mg' => '200mg'],
        'prices' => ['200mg' => '33.99'],
    ],
    'tesamorelin' => ['map' => ['5mg' => '10mg'], 'prices' => []],
];

foreach ($size_fixes as $slug => $fix) {
    $p = $find($slug);
    if (!$p) { $missing[] = $slug; continue; }
    $changes = [];
    $map = $fix['map'];

    $variations = get_posts([
        'post_type' => 'product_variation', 'post_parent' => $p->ID,
        'post_status' => ['publish', 'private'], 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC',
    ]);
    $amounts = [];
    foreach ($variations as $v) {
        $cur = (string) get_post_meta($v->ID, 'attribute_amount', true);
        $new = $map[$cur] ?? $cur;
        $amounts[] = $new;
        $want_price = $fix['prices'][$new] ?? null;
        $price_ok = $want_price === null
            || get_post_meta($v->ID, '_price', true) === $want_price;
        if ($new === $cur && $v->post_title === "{$p->post_title} - {$new}" && $price_ok) continue;
        $changes[] = "variation #{$v->ID}: {$cur} → {$new}" . ($price_ok ? '' : " @ {$want_price}");
        if ($dry) continue;
        update_post_meta($v->ID, 'attribute_amount', $new);
        wp_update_post(['ID' => $v->ID, 'post_title' => "{$p->post_title} - {$new}"]);
        if (!$price_ok) {
            update_post_meta($v->ID, '_regular_price', $want_price);
            update_post_meta($v->ID, '_price', $want_price);
        }
    }
    sort($amounts, SORT_NATURAL);

    // Parent attribute: single custom (non-taxonomy) "Amount", same shape as
    // the working variable products (e.g. the GLP pair).
    $want_attrs = ['amount' => [
        'name' => 'Amount', 'value' => implode(' | ', $amounts),
        'position' => 0, 'is_visible' => 1, 'is_variation' => 1, 'is_taxonomy' => 0,
    ]];
    if (get_post_meta($p->ID, '_product_attributes', true) !== $want_attrs) {
        $changes[] = 'parent attribute → Amount: ' . implode(' | ', $amounts);
        if (!$dry) update_post_meta($p->ID, '_product_attributes', $want_attrs);
    }
    if (get_the_terms($p->ID, 'pa_size')) {
        $changes[] = 'detach pa_size terms';
        if (!$dry) wp_set_object_terms($p->ID, [], 'pa_size');
    }

    if (!$changes) { $log("size  {$slug}: already correct"); continue; }
    $log("size  {$slug} (#{$p->ID}): " . implode(', ', $changes));
    if (!$dry) {
        if (class_exists('WC_Product_Variable')) WC_Product_Variable::sync($p->ID);
        if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($p->ID);
    }
}

if ($missing) {
    WP_CLI::error('Product slug(s) not found: ' . implode(', ', $missing));
}

// Post-write verification — assert the end state, not the writes.
if (!$dry) {
    $problems = [];
    foreach ($wire as $slug => $_) {
        $p = $find($slug);
        $want = esc_url_raw($coa_map[$slug] ?? '');
        $got  = get_post_meta($p->ID, '_nav_coa_pdf', true);
        if ($got !== $want) {
            $problems[] = "{$slug}: _nav_coa_pdf is '{$got}', expected '{$want}'";
        }
        // Every product must point at its OWN file, never a shared one.
        if ($got !== '' && !str_contains($got, "coa-")) {
            $problems[] = "{$slug}: certificate URL is not a per-product file: {$got}";
        }
        if ($p->post_status !== 'publish') $problems[] = "{$slug}: not published";
    }
    foreach ($hide as $slug) {
        if ($find($slug)->post_status !== 'draft') $problems[] = "{$slug}: not draft";
    }
    foreach ($coded as $slug => $names) {
        $p = $find($slug) ?? $find($names['slug']);
        foreach (['irzepatide', 'etatrutide', 'emaglutide'] as $leak) {
            if (stripos($p->post_content . ' ' . $p->post_excerpt . ' ' . $p->post_title, $leak) !== false) {
                $problems[] = "{$slug}: real compound name still in visible text";
            }
        }
    }
    foreach ($size_fixes as $slug => $fix) {
        $p = $find($slug);
        $vals = [];
        foreach (get_posts(['post_type' => 'product_variation', 'post_parent' => $p->ID,
                            'post_status' => ['publish', 'private'], 'numberposts' => -1]) as $v) {
            $amt = (string) get_post_meta($v->ID, 'attribute_amount', true);
            $vals[] = $amt;
            $want_price = $fix['prices'][$amt] ?? null;
            if ($want_price !== null && get_post_meta($v->ID, '_price', true) !== $want_price) {
                $problems[] = "{$slug} {$amt}: price is not {$want_price}";
            }
        }
        // Only sizes that are pure sources (never a target) must be gone —
        // e.g. 500mg is both a target of 5000mg and a valid final size.
        $gone = array_diff(array_keys($fix['map']), array_values($fix['map']));
        foreach ($gone as $old) {
            if (in_array($old, $vals, true)) $problems[] = "{$slug}: variation still {$old}";
        }
        $attrs = get_post_meta($p->ID, '_product_attributes', true);
        if (!isset($attrs['amount']) || ($attrs['amount']['is_taxonomy'] ?? 1)) {
            $problems[] = "{$slug}: parent attribute is not the custom Amount";
        }
        // Every dropdown value must match exactly one variation, else the
        // selector falls back to first-match and the wrong price ships.
        if (count($vals) !== count(array_unique($vals))) {
            $problems[] = "{$slug}: duplicate variation amounts: " . implode(',', $vals);
        }
    }
    if ($problems) {
        WP_CLI::error("Verification failed:\n  - " . implode("\n  - ", $problems));
    }
    WP_CLI::success(sprintf(
        'Synced and verified: %d wired, %d hidden, %d GLP names coded, %d size-fixed.',
        count($wire), count($hide), count($coded), count($size_fixes)
    ));
} else {
    WP_CLI::success('Dry run complete — no changes written.');
}
