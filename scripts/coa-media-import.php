<?php
/**
 * Import the per-product certificates into the Media Library and emit the
 * slug => URL map the other two scripts consume.
 *
 * Run via WP-CLI:
 *   wp eval-file scripts/coa-media-import.php <certs-dir> <out-map.json> [dry-run] [retire-compiled]
 *
 * Idempotent: an attachment is reused when one already exists whose file name
 * matches the certificate's stem, so repeat runs never duplicate media.
 *
 * dry-run performs NO writes: existing attachments are looked up, and any
 * certificate not yet uploaded is reported and given a placeholder URL purely
 * so the downstream dry-runs can print their plans.
 */

/** @var array $args */
if (!isset($args) || count($args) < 2) {
    WP_CLI::error('Usage: coa-media-import.php <certs-dir> <out-map.json> [dry-run] [retire-compiled]');
}
[$dir, $out] = $args;
$opts    = array_map(fn($a) => ltrim((string) $a, '-'), array_slice($args, 2));
$dry     = in_array('dry-run', $opts, true);
$retire  = in_array('retire-compiled', $opts, true);

$manifest_path = rtrim($dir, '/') . '/manifest.json';
if (!is_readable($manifest_path)) {
    WP_CLI::error("manifest.json not found in {$dir}");
}
$manifest = json_decode((string) file_get_contents($manifest_path), true);
if (!is_array($manifest) || !$manifest) {
    WP_CLI::error('manifest.json did not decode to a non-empty array.');
}

// ABSPATH-relative, so this works on wp.com Atomic and any local stack alike.
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$find_attachment = function (string $stem): ?int {
    $hits = get_posts([
        'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => -1,
        'meta_query' => [['key' => '_wp_attached_file', 'value' => $stem, 'compare' => 'LIKE']],
    ]);
    foreach ($hits as $a) {
        // LIKE also matches "coa-nad-1.pdf" etc; require the exact stem.
        $base = pathinfo((string) get_post_meta($a->ID, '_wp_attached_file', true), PATHINFO_FILENAME);
        if ($base === $stem || preg_match('/^' . preg_quote($stem, '/') . '(-\d+)?$/', $base)) {
            return (int) $a->ID;
        }
    }
    return null;
};

$map = [];
$created = $reused = $pending = $replaced = $stale = 0;

foreach ($manifest as $slug => $file) {
    $stem = pathinfo($file, PATHINFO_FILENAME);
    $path = rtrim($dir, '/') . '/' . $file;
    if (!is_readable($path)) { WP_CLI::error("{$slug}: {$file} missing from {$dir}"); }

    $id = $find_attachment($stem);
    if ($id) {
        // Same name does NOT mean same certificate: re-mapping a product to a
        // different page keeps the filename and changes only the bytes. Compare
        // content and overwrite in place (same URL, so nothing else breaks).
        $existing = get_attached_file($id);
        if (!$existing || !file_exists($existing) || md5_file($existing) !== md5_file($path)) {
            if ($dry) {
                $stale++;
                $map[$slug] = wp_get_attachment_url($id);
                continue;
            }
            copy($path, $existing);
            wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $existing));
            $replaced++;
        } else {
            $reused++;
        }
    } elseif ($dry) {
        $pending++;
        $map[$slug] = 'https://example.invalid/' . $file;   // placeholder, dry-run only
        continue;
    } else {
        // copy: media_handle_sideload MOVES the file, and the same directory
        // is re-read if this script is run twice in one session.
        $tmp = wp_tempnam($file);
        copy($path, $tmp);
        $id = media_handle_sideload(
            ['name' => $file, 'tmp_name' => $tmp],
            0,
            ucwords(str_replace('-', ' ', $stem))
        );
        if (is_wp_error($id)) {
            @unlink($tmp);
            WP_CLI::error("{$slug}: " . $id->get_error_message());
        }
        $created++;
    }
    $map[$slug] = wp_get_attachment_url($id);
}

if (!$dry) {
    // Verify every URL actually resolves to a stored file before anything
    // downstream wires it onto a product page.
    $bad = [];
    foreach ($map as $slug => $url) {
        $aid = attachment_url_to_postid($url);
        if (!$aid) { $bad[] = "{$slug}: {$url} resolves to no attachment"; continue; }
        $fp = get_attached_file($aid);
        if (!$fp || !file_exists($fp)) { $bad[] = "{$slug}: file missing on disk for {$url}"; continue; }
        // The stored file must be byte-identical to the verified split, so a
        // stale attachment can never be left wired to a product.
        $srcfile = rtrim($dir, '/') . '/' . $manifest[$slug];
        if (md5_file($fp) !== md5_file($srcfile)) {
            $bad[] = "{$slug}: stored file differs from the verified certificate";
        }
    }
    if ($bad) { WP_CLI::error("Media verification failed:\n  - " . implode("\n  - ", $bad)); }
}

file_put_contents($out, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
WP_CLI::log(sprintf('%s%d certificates: %d new, %d replaced, %d unchanged%s%s',
    $dry ? '[dry-run] ' : '', count($map), $created, $replaced, $reused,
    $pending ? ", {$pending} not yet uploaded" : '',
    $stale ? ", {$stale} would be replaced (content changed)" : ''));

/* The compiled catalogue PDF is retired once nothing references it: its index
 * page listed every supplier product — real GLP names included — plus twelve
 * "No current COA" rows. */
if ($retire && !$dry) {
    foreach (get_posts([
        'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => -1,
        'meta_query' => [['key' => '_wp_attached_file', 'value' => 'certificates-of-analysis', 'compare' => 'LIKE']],
    ]) as $a) {
        $url = wp_get_attachment_url($a->ID);
        $ref = get_posts([
            'post_type' => 'product', 'post_status' => ['publish', 'draft', 'pending', 'private'],
            'numberposts' => 1,
            'meta_query' => [['key' => '_nav_coa_pdf', 'value' => $url, 'compare' => '=']],
        ]);
        if ($ref) { WP_CLI::warning("compiled PDF #{$a->ID} still referenced — left in place"); continue; }
        wp_delete_attachment($a->ID, true);
        WP_CLI::log("retired compiled catalogue PDF (attachment #{$a->ID})");
    }
}
