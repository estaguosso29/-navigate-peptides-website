<?php
/**
 * COA catalog sync — August 2026 Azoth certificate wiring.
 *
 * Run via WP-CLI:  wp eval-file scripts/coa-sync.php <coa-pdf-url> [dry-run] [rename-slugs]
 *
 * (Options are bare words, not --flags: WP-CLI consumes unknown --flags
 * itself and they never reach eval-file scripts.)
 *
 * Idempotent: products are addressed by slug, every write is a plain
 * update, and re-running against an already-synced site is a no-op.
 *
 *   <coa-pdf-url>   URL of the compiled COA PDF in the media library.
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
if (empty($args[0]) || !preg_match('#^https?://#', $args[0])) {
    WP_CLI::error('First argument must be the COA PDF URL.');
}
$coa_url = $args[0];
$opts    = array_map(fn($a) => ltrim((string) $a, '-'), array_slice($args, 1));
$dry          = in_array('dry-run', $opts, true);
$rename_slugs = in_array('rename-slugs', $opts, true);

// slug => [testing lab, batch/lot, purity]
$wire = [
    'bpc-157'        => ['Freedom Diagnostics', '261807',          '99.31%'],
    'bpc-157-tb-500' => ['Freedom Diagnostics', '261807',          '99.76%'],
    'epithalon'      => ['Freedom Diagnostics', '261807',          '99.61%'],
    'ghk-cu'         => ['BioViridian',         '07022026-022',    '99.84%'],
    'kpv'            => ['Freedom Diagnostics', '261807',          '99.04%'],
    'mots-c'         => ['Freedom Diagnostics', '261807',          '99.06%'],
    'nad'            => ['Freedom Diagnostics', '261807',          '99.94%'],
    'tesamorelin'    => ['Freedom Diagnostics', '261807',          '99.78%'],
    'tirzepatide'    => ['Optiq Health Labs',   '0330.OPT.260729', '99.99%'],
    'retatrutide'    => ['Optiq Health Labs',   '0336.OPT.260729', '99.99%'],
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
    $log("wire  {$slug} (#{$p->ID}): coa_pdf, lab={$lab}, lot={$lot}, purity={$purity}");
    if ($dry) continue;
    update_post_meta($p->ID, '_nav_coa_pdf', esc_url_raw($coa_url));
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

if ($missing) {
    WP_CLI::error('Product slug(s) not found: ' . implode(', ', $missing));
}

// Post-write verification — assert the end state, not the writes.
if (!$dry) {
    $problems = [];
    foreach ($wire as $slug => $_) {
        $p = $find($slug);
        if (get_post_meta($p->ID, '_nav_coa_pdf', true) !== esc_url_raw($coa_url)) {
            $problems[] = "{$slug}: _nav_coa_pdf not set";
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
    if ($problems) {
        WP_CLI::error("Verification failed:\n  - " . implode("\n  - ", $problems));
    }
    WP_CLI::success(sprintf(
        'Synced and verified: %d wired, %d hidden, %d GLP names coded.',
        count($wire), count($hide), count($coded)
    ));
} else {
    WP_CLI::success('Dry run complete — no changes written.');
}
