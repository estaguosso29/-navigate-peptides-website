<?php
/**
 * Navigate Peptides Theme Functions
 *
 * @package NavigatePeptides
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

define('NAV_THEME_VERSION', '1.0.0');
define('NAV_THEME_DIR', get_template_directory());
define('NAV_THEME_URI', get_template_directory_uri());

/* ------------------------------------------------------------------
 * 1. Theme Setup
 * ----------------------------------------------------------------*/
add_action('after_setup_theme', function () {
    // Title tag support
    add_theme_support('title-tag');

    // Post thumbnails. The earlier add_image_size() registrations
    // (product-card 400×400, product-hero 800×800, category-hero
    // 1200×400) were never consumed by any template — the storefront
    // ships pre-baked WebP posters at fixed dimensions instead — so
    // every new media upload was triggering 3× unused thumbnail
    // regenerations. Dropped them. Reinstate selectively if a future
    // feature actually needs a WP-managed crop pipeline.
    add_theme_support('post-thumbnails');

    // HTML5 markup — dropped comment-form / comment-list since the
    // theme ships no comments.php (the storefront doesn't expose
    // comments on any post type). Declaring support for templates that
    // don't exist is a no-op and confuses theme-review tooling.
    add_theme_support('html5', [
        'search-form', 'gallery', 'caption', 'style', 'script',
    ]);

    // Translation loading — without this, every __() / esc_html__()
    // call against the navigate-peptides text domain falls through to
    // the source string even when a translator drops a .mo into
    // wp-content/languages/themes/navigate-peptides-<locale>.mo. Theme
    // currently ships no .pot, but wiring the loader unblocks future
    // i18n work and is required by the WP theme review checklist.
    load_theme_textdomain('navigate-peptides', NAV_THEME_DIR . '/languages');

    // Custom logo
    add_theme_support('custom-logo', [
        'height'      => 40,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ]);

    // Site icon (favicon) support
    add_theme_support('site-icon');

    // WooCommerce
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');

    // Menus
    register_nav_menus([
        'primary'     => __('Primary Navigation', 'navigate-peptides'),
        'footer-1'    => __('Footer Column 1', 'navigate-peptides'),
        'footer-2'    => __('Footer Column 2', 'navigate-peptides'),
        'footer-3'    => __('Footer Column 3', 'navigate-peptides'),
    ]);
});

/* ------------------------------------------------------------------
 * 2. Enqueue Assets
 * ----------------------------------------------------------------*/
/**
 * Asset cache-bust: file mtime when it's readable, NAV_THEME_VERSION fallback.
 * A static version string would require manual bumps after every edit and
 * serve stale CSS from CDN/browser caches.
 */
function nav_asset_version(string $relative_path): string {
    $abs = NAV_THEME_DIR . '/' . ltrim($relative_path, '/');
    // Don't silently swallow filemtime failures — a deploy dropping a
    // file would otherwise always fall through to NAV_THEME_VERSION
    // and serve stale cached CSS/JS forever. Log the miss so the next
    // diagnostics pass catches it.
    if (!file_exists($abs)) {
        static $logged = [];
        if (!isset($logged[$relative_path])) {
            $logged[$relative_path] = true;
            error_log(sprintf('[nav_asset_version] missing asset: %s', $relative_path));
        }
        return NAV_THEME_VERSION;
    }
    $mtime = filemtime($abs);
    return $mtime ? (string) $mtime : NAV_THEME_VERSION;
}

add_action('wp_enqueue_scripts', function () {
    // Google Fonts
    wp_enqueue_style(
        'nav-google-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&family=Playfair+Display:wght@400;500;600;700&display=swap',
        [],
        null
    );

    // Main stylesheet
    wp_enqueue_style(
        'nav-main',
        NAV_THEME_URI . '/assets/css/main.css',
        ['nav-google-fonts'],
        nav_asset_version('assets/css/main.css')
    );

    // WooCommerce overrides
    if (class_exists('WooCommerce')) {
        wp_enqueue_style(
            'nav-woocommerce',
            NAV_THEME_URI . '/assets/css/woocommerce.css',
            ['nav-main'],
            nav_asset_version('assets/css/woocommerce.css')
        );
    }

    // JavaScript. Declaring jQuery + wc-cart-fragments as deps so our
    // added_to_cart handlers and the minicart fragment-refresh path
    // run on pages where WC wouldn't otherwise enqueue them.
    $js_deps = [];
    $force_wc_scripts = false;
    if (class_exists('WooCommerce')) {
        $js_deps = ['jquery', 'wc-cart-fragments', 'wc-add-to-cart'];
        // Gate the force-enqueue of WC's ajax scripts to pages where they're
        // meaningful. Research articles, blog posts, and about pages don't
        // need cart-fragments polling / 80KB of extra JS.
        $force_wc_scripts = is_shop() || is_product() || is_product_category()
            || is_product_tag() || is_cart() || is_checkout() || is_front_page();
    }
    wp_enqueue_script(
        'nav-main',
        NAV_THEME_URI . '/assets/js/main.js',
        $js_deps,
        nav_asset_version('assets/js/main.js'),
        true
    );

    // Expose REST URL + nonce to the footer newsletter form. The nonce
    // protects /wp-json/nav/v1/subscribe from CSRF of logged-in admins.
    wp_localize_script('nav-main', 'navConfig', [
        'subscribeUrl' => esc_url_raw(rest_url('nav/v1/subscribe')),
        'restNonce'    => wp_create_nonce('wp_rest'),
    ]);

    // Only force-enqueue WC ajax scripts on pages that actually need
    // the cart-fragments XHR. Research articles / blog posts / static
    // pages don't render cart UI — loading ~80KB of extra JS and
    // polling cart-fragments there was pure waste.
    if (class_exists('WooCommerce') && $force_wc_scripts) {
        wp_enqueue_script('wc-cart-fragments');
        wp_enqueue_script('wc-add-to-cart');
    }
});

/*
 * Dequeue default WooCommerce stylesheets. Must run at priority 100
 * (AFTER WC's own enqueue callback at default priority 20) — at
 * priority 10 the queue entries don't exist yet and the dequeue calls
 * silently no-op. The visible result is fine today because
 * woocommerce.css overrides everything WC ships, but two stylesheets
 * end up parsed instead of one, wasting bytes + CSSOM work per page.
 */
add_action('wp_enqueue_scripts', function () {
    if (!class_exists('WooCommerce')) return;
    wp_dequeue_style('woocommerce-general');
    wp_dequeue_style('woocommerce-layout');
    wp_dequeue_style('woocommerce-smallscreen');
}, 100);

/* ------------------------------------------------------------------
 * 3. WooCommerce Configuration
 * ----------------------------------------------------------------*/
require_once NAV_THEME_DIR . '/inc/woocommerce.php';

/* ------------------------------------------------------------------
 * 4. Compliance & Disclaimers
 * ----------------------------------------------------------------*/
require_once NAV_THEME_DIR . '/inc/compliance.php';

/* ------------------------------------------------------------------
 * 5. SEO & Schema Markup
 * ----------------------------------------------------------------*/
require_once NAV_THEME_DIR . '/inc/seo.php';

/* ------------------------------------------------------------------
 * 6. Custom Post Types & Taxonomies
 * ----------------------------------------------------------------*/
require_once NAV_THEME_DIR . '/inc/custom-types.php';

/* ------------------------------------------------------------------
 * 6b. Contact Form Handler
 * ----------------------------------------------------------------*/
require_once NAV_THEME_DIR . '/inc/contact.php';

/* ------------------------------------------------------------------
 * 6c. Analytics (GA4 + WC ecommerce events)
 * ----------------------------------------------------------------*/
require_once NAV_THEME_DIR . '/inc/analytics.php';

/* ------------------------------------------------------------------
 * 6d. Minicart drawer
 * ----------------------------------------------------------------*/
require_once NAV_THEME_DIR . '/inc/minicart.php';

/* ------------------------------------------------------------------
 * 6e. Cookie consent banner (gates GA4 via Consent Mode v2)
 * ----------------------------------------------------------------*/
require_once NAV_THEME_DIR . '/inc/consent.php';

/* ------------------------------------------------------------------
 * 6f. Newsletter / subscriber capture (footer form + REST endpoint)
 * ----------------------------------------------------------------*/
require_once NAV_THEME_DIR . '/inc/subscribe.php';

/* ------------------------------------------------------------------
 * 6g. Arcana Operations Developers — attribution + advertising.
 *
 * Designed and developed by Arcana Operations — custom WordPress
 * sites, WooCommerce stores, and bespoke web builds.
 * https://arcanaoperations.com
 * ----------------------------------------------------------------*/
require_once NAV_THEME_DIR . '/inc/arcana-credit.php';

/* ------------------------------------------------------------------
 * 6h. Age + Research-Use-Only entry gate
 *
 * Full-screen modal at first visit confirming the visitor is 21+
 * and acknowledges the in-vitro research-only scope. Required for
 * processor compliance + RUO posture.
 * ----------------------------------------------------------------*/
require_once NAV_THEME_DIR . '/inc/age-gate.php';

/* ------------------------------------------------------------------
 * 6i. Business identity — single source of truth
 *
 * DBA (Navigate Peptides), shipping address, and support email
 * constants + helpers. Consumed by footer.php, inc/seo.php,
 * template-contact.php, and any legal page. Legal-entity name and
 * phone number are deliberately omitted from constants — see
 * inc/business.php for the redaction rationale (processor compliance
 * + privacy posture).
 * ----------------------------------------------------------------*/
require_once NAV_THEME_DIR . '/inc/business.php';

/* ------------------------------------------------------------------
 * 7. Security Headers
 * ----------------------------------------------------------------*/
add_action('send_headers', function () {
    if (is_admin()) return;

    // Bail cleanly if another plugin already flushed headers — header() would
    // warn silently otherwise.
    if (headers_sent($sent_file, $sent_line)) {
        error_log(sprintf('[nav_security] headers already sent at %s:%d', $sent_file, $sent_line));
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

    // form-action must stay same-origin. If admin_url()'s host differs from
    // home_url()'s (unusual — implies a filter messing with admin URLs), drop
    // the extra origin rather than trust it.
    $admin_host = wp_parse_url(admin_url(), PHP_URL_HOST);
    $site_host  = wp_parse_url(home_url(), PHP_URL_HOST);
    $form_action = ($admin_host && $admin_host === $site_host)
        ? "'self' " . esc_url(admin_url())
        : "'self'";

    // CSP notes:
    //   * 'wasm-unsafe-eval' — required by Google model-viewer 4.0 (it
    //     uses WebAssembly internally for draco/ktx2 decoding).
    //   * worker-src 'self' blob: — model-viewer spawns Web Workers from
    //     blob URLs it synthesizes; without this they silently fail and
    //     WebGL init errors, so the vial never renders.
    //   * blob: in img-src — model-viewer creates blob image URLs.
    //   * *.wp.com / *.wordpress.com — wordpress.com injects its own stats,
    //     metrics (bilmur), and font proxy (fonts-api.wp.com) into every
    //     page. Our generic WordPress CSP from Round 7 was blocking them,
    //     which on wp.com also intermittently breaks the font pipeline.
    //   * ajax.googleapis.com / fonts.googleapis.com — our own deps.
    //   * GA4 hosts — appended only when nav_ga4_id() is set.
    $script_src  = "'self' 'unsafe-inline' 'wasm-unsafe-eval' "
                 . "https://ajax.googleapis.com https://fonts.googleapis.com "
                 . "https://*.wp.com https://*.wordpress.com";

    // 'unsafe-eval' on product pages ONLY.
    //
    // WooCommerce's variation form calls wp.template() -> _.template()
    // (underscore), which compiles the template with `new Function()`.
    // 'wasm-unsafe-eval' permits WebAssembly compilation but NOT
    // new Function/eval, so on a variable product the browser threw
    //   Uncaught EvalError: ... 'unsafe-eval' is not an allowed source
    //   at m.template (underscore) <- wp-util.js <- VariationForm.onFoundVariation
    // That exception aborted onFoundVariation before it wrote
    // input[name="variation_id"], so picking a size left Add to Cart dead.
    //
    // Scoped to is_product() deliberately: only the PDP enqueues
    // wp-util/underscore/add-to-cart-variation (verified — no other
    // template loads them). Checkout, cart, account, and content pages —
    // where XSS would actually reach payment and PII — keep the strict
    // no-eval policy.
    if (function_exists('is_product') && is_product()) {
        $script_src .= " 'unsafe-eval'";
    }
    // connect-src INCLUDES ajax.googleapis.com — model-viewer's main script
    // XHRs for its runtime workers, WASM decoders, and HDR environment
    // textures. Without this, the canvas stays transparent because those
    // internal fetches get blocked silently (only the source-map fetch
    // error shows in console). Fonts + GA covered further down.
    $connect_src = "'self' blob: "
                 . "https://ajax.googleapis.com "
                 . "https://*.wp.com https://*.wordpress.com";
    $img_src     = "'self' data: blob: https:";
    $style_src   = "'self' 'unsafe-inline' "
                 . "https://fonts.googleapis.com "
                 . "https://*.wp.com https://*.wordpress.com";
    $font_src    = "'self' data: https://fonts.gstatic.com https://*.wp.com https://*.wordpress.com";
    $worker_src  = "'self' blob:";
    if (function_exists('nav_ga4_id') && nav_ga4_id() !== '') {
        $script_src  .= ' https://www.googletagmanager.com';
        $connect_src .= ' https://www.google-analytics.com https://*.analytics.google.com https://*.g.doubleclick.net';
    }
    $csp = "default-src 'self'; "
        . "script-src {$script_src}; "
        . "style-src {$style_src}; "
        . "font-src {$font_src}; "
        . "img-src {$img_src}; "
        . "connect-src {$connect_src}; "
        . "worker-src {$worker_src}; "
        . "frame-ancestors 'self'; "
        . "base-uri 'self'; "
        . "form-action {$form_action};";
    header('Content-Security-Policy: ' . $csp);
});

/* ------------------------------------------------------------------
 * 8. Admin Cleanup
 * ----------------------------------------------------------------*/
add_action('wp_head', function () {
    // Remove unnecessary meta
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wp_shortlink_wp_head');
}, 1);

// Remove emoji scripts
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');

/* ------------------------------------------------------------------
 * 8b. Admin surface hardening
 *
 * These close common WP admin-enumeration paths that aren't used by
 * the theme's front-end. If a plugin requires any of these, unhook in
 * wp-config.php via the provided filters.
 * ----------------------------------------------------------------*/

// Disable XML-RPC — brute-force / pingback amplifier. No theme feature uses it.
add_filter('xmlrpc_enabled', '__return_false');
add_filter('wp_headers', function (array $headers) {
    unset($headers['X-Pingback']);
    return $headers;
});

// Strip REST endpoints that expose the user list to anonymous requests.
// Logged-in admins still get /wp/v2/users for admin UI needs.
add_filter('rest_endpoints', function (array $endpoints) {
    if (is_user_logged_in() && current_user_can('list_users')) {
        return $endpoints;
    }
    foreach ([
        '/wp/v2/users',
        '/wp/v2/users/(?P<id>[\d]+)',
        '/wp/v2/users/me',
    ] as $path) {
        if (isset($endpoints[$path])) unset($endpoints[$path]);
    }
    return $endpoints;
});

// Redirect /author/<slug>/ to home — prevents username enumeration. Gates
// on capability (list_users) rather than login-state so subscribers /
// customers can't enumerate authors either.
add_action('template_redirect', function () {
    if (is_author() && !current_user_can('list_users')) {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
});

// Strip oEmbed author / avatar from REST response payloads.
add_filter('oembed_response_data', function ($data) {
    if (is_array($data)) {
        unset($data['author_name'], $data['author_url']);
    }
    return $data;
});

/* ------------------------------------------------------------------
 * 8c. Memoize category term URL lookups — homepage / header / footer
 * cumulatively trigger 20+ get_term_link() calls each render. Cache
 * the resolved URL for the request lifetime.
 * ----------------------------------------------------------------*/
function nav_get_product_cat_url(string $slug): string {
    static $cache = [];
    if (isset($cache[$slug])) return $cache[$slug];

    $link = get_term_link($slug, 'product_cat');
    $cache[$slug] = (is_wp_error($link) || !$link)
        ? home_url('/compounds/')
        : (string) $link;
    return $cache[$slug];
}

/* ------------------------------------------------------------------
 * 9. Widget Areas
 * ----------------------------------------------------------------*/
add_action('widgets_init', function () {
    register_sidebar([
        'name'          => __('Footer Widget Area', 'navigate-peptides'),
        'id'            => 'footer-widgets',
        'before_widget' => '<div class="nav-footer-widget">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="nav-footer-widget__title">',
        'after_title'   => '</h4>',
    ]);
});

/* ------------------------------------------------------------------
 * 10. Helper Functions
 * ----------------------------------------------------------------*/

/**
 * Stable per-visitor identifier for transient keying across redirects.
 *
 * Previously analytics + minicart keyed on wp_get_session_token() which
 * returns '' for anonymous guests — so the queued add_to_cart event and
 * the drawer auto-open both silently no-op'd for logged-out users (the
 * single largest cohort on a pre-account B2B site).
 *
 * Resolution order:
 *   1. WC customer session ID (cookie-backed, survives across page loads
 *      for guests as soon as any Woo session touchpoint fires)
 *   2. WP auth session token (logged-in users)
 *   3. A theme-owned 1st-party cookie set once per visitor
 *
 * Returned string is guaranteed non-empty + stable for the visitor.
 */
function nav_visitor_key(): string {
    // Prefer WC's customer session key — Woo sets a customer cookie as soon
    // as add_to_cart fires, so the redirect back to the product page has
    // the same key available.
    if (function_exists('WC') && WC()->session) {
        $cid = (string) WC()->session->get_customer_id();
        if ($cid !== '') return 'wc:' . $cid;
    }

    // Logged-in users get a per-session auth token.
    $token = wp_get_session_token();
    if ($token) return 'wp:' . $token;

    // Anonymous + no Woo session yet — fall back to a 1st-party cookie.
    // NAV_VISITOR cookie is httponly-false on purpose (some client scripts
    // may want to read it). Rotates every 180 days.
    if (!empty($_COOKIE['nav_visitor'])) {
        $cookie = (string) $_COOKIE['nav_visitor'];
        if (preg_match('/^[a-f0-9]{32}$/', $cookie)) return 'ck:' . $cookie;
    }

    if (!headers_sent()) {
        $fresh = wp_hash(uniqid('nav_visitor_', true));
        setcookie(
            'nav_visitor',
            $fresh,
            time() + 180 * DAY_IN_SECONDS,
            COOKIEPATH ?: '/',
            COOKIE_DOMAIN,
            is_ssl(),
            false
        );
        // Make the fresh cookie available to subsequent code in the same request.
        $_COOKIE['nav_visitor'] = $fresh;
        return 'ck:' . $fresh;
    }

    // Last resort — reached when headers are already sent (typically a
    // cached response) AND there's no nav_visitor cookie AND no WC
    // session. Previously we hashed (IP | UA) here, which bucketed every
    // visitor behind the same NAT / VPN / Cloudflare Warp into one key —
    // and since transients keyed on this value control cart-drawer auto-
    // open and GA4 add_to_cart dedup, that leaked user A's events onto
    // user B's next page load.
    //
    // Mix in random_bytes + microtime so the key is unique per request.
    // This degrades the UX — auto-open and GA4-dedup won't work across
    // requests for fully-cached anonymous traffic — but it prevents the
    // cross-user contamination that the harsh audit caught.
    $entropy = '';
    if (function_exists('random_bytes')) {
        try { $entropy = bin2hex(random_bytes(8)); } catch (Throwable $e) { $entropy = ''; }
    }
    if ($entropy === '') $entropy = (string) mt_rand();
    return 'fp:' . wp_hash(
        ($_SERVER['REMOTE_ADDR'] ?? '') . '|' .
        substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200) . '|' .
        microtime(true) . '|' . $entropy
    );
}

/**
 * Canonical URL for the "Contact / Request Access" page.
 *
 * Resolves (in order):
 *   1. A page saved in the `nav_contact_page_id` option (Customizer-editable)
 *   2. A page with slug 'contact' (matches WP's default page finder)
 *   3. The hardcoded default /about/contact/
 *
 * Lets marketing rename the slug without breaking header / footer / CTAs.
 */
function nav_get_contact_url(): string {
    $found  = false;
    $cached = wp_cache_get('nav_contact_url', 'navigate-peptides', false, $found);
    if ($found && is_string($cached) && $cached !== '') {
        return $cached;
    }

    $url = '';
    $page_id = (int) get_option('nav_contact_page_id', 0);
    if ($page_id && get_post_status($page_id) === 'publish') {
        $url = (string) get_permalink($page_id);
    }
    if ($url === '') {
        $page = get_page_by_path('about/contact');
        if ($page) $url = (string) get_permalink($page);
    }
    if ($url === '') {
        $page = get_page_by_path('contact');
        if ($page) $url = (string) get_permalink($page);
    }
    // Hard fallback — always a valid URL so the return type is honored.
    if ($url === '') {
        $url = home_url('/about/contact/');
    }

    wp_cache_set('nav_contact_url', $url, 'navigate-peptides', 300);
    return $url;
}

// Invalidate the cached contact URL when admin remaps the page.
add_action('update_option_nav_contact_page_id', function () {
    wp_cache_delete('nav_contact_url', 'navigate-peptides');
});
add_action('save_post_page',  function () { wp_cache_delete('nav_contact_url', 'navigate-peptides'); });
add_action('trashed_post',    function () { wp_cache_delete('nav_contact_url', 'navigate-peptides'); });

/**
 * Get category color by slug — locked to Stephie's spec sheet.
 * One color per category, applied to the cap-ring + mg text on the
 * label and the category pill in the UI. Muted tones only.
 */
function nav_get_category_color(string $slug): string {
    $colors = [
        'metabolic-research'           => '#3F6A8A',  // Blue
        'cellular-research'            => '#4A6F5A',  // Green
        'tissue-repair-research'       => '#A88E45',  // Gold
        'hormonal-signaling-research'  => '#6B5A7A',  // Lilac
        'cognitive-research'           => '#8A5D6A',  // Pink
        'dermal-research'              => '#5A2E36',  // Wine
    ];
    return $colors[$slug] ?? '#474C50';
}

/**
 * Get category color from WooCommerce product category term.
 */
function nav_get_product_category_color($product = null): string {
    if (!$product) {
        global $product;
    }
    if (!$product) return '#474C50';

    $terms = get_the_terms($product->get_id(), 'product_cat');
    if (!$terms || is_wp_error($terms)) return '#474C50';

    foreach ($terms as $term) {
        $color = nav_get_category_color($term->slug);
        if ($color !== '#474C50') return $color;
    }
    return '#474C50';
}

/**
 * wp_kses allowlist for inline SVG icons.
 * Use via nav_kses_svg($svg_string).
 */
function nav_svg_allowed_html(): array {
    // Base attributes that every SVG element can carry.
    $svg_attrs = [
        'xmlns' => true, 'viewbox' => true, 'fill' => true,
        'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true,
        'stroke-linejoin' => true, 'stroke-miterlimit' => true,
        'stroke-dasharray' => true, 'stroke-dashoffset' => true, 'stroke-opacity' => true,
        'fill-opacity' => true, 'fill-rule' => true, 'clip-path' => true,
        'mask' => true, 'opacity' => true, 'transform' => true,
        'vector-effect' => true, 'pointer-events' => true,
        'width' => true, 'height' => true, 'class' => true, 'style' => true,
        'aria-hidden' => true, 'aria-label' => true, 'role' => true, 'focusable' => true,
    ];
    return [
        'svg'      => $svg_attrs,
        'path'     => array_merge($svg_attrs, ['d' => true]),
        'circle'   => array_merge($svg_attrs, ['cx' => true, 'cy' => true, 'r' => true]),
        'ellipse'  => array_merge($svg_attrs, ['cx' => true, 'cy' => true, 'rx' => true, 'ry' => true]),
        'rect'     => array_merge($svg_attrs, ['x' => true, 'y' => true, 'rx' => true, 'ry' => true]),
        'line'     => array_merge($svg_attrs, ['x1' => true, 'y1' => true, 'x2' => true, 'y2' => true]),
        'polyline' => array_merge($svg_attrs, ['points' => true]),
        'polygon'  => array_merge($svg_attrs, ['points' => true]),
        'g'        => $svg_attrs,
        'use'      => array_merge($svg_attrs, ['href' => true, 'xlink:href' => true, 'x' => true, 'y' => true]),
        'defs'     => $svg_attrs,
        'symbol'   => array_merge($svg_attrs, ['id' => true]),
        'clippath' => array_merge($svg_attrs, ['id' => true]),
        'mask'     => array_merge($svg_attrs, ['id' => true, 'maskunits' => true]),
        'lineargradient' => array_merge($svg_attrs, ['id' => true, 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'gradientunits' => true]),
        'radialgradient' => array_merge($svg_attrs, ['id' => true, 'cx' => true, 'cy' => true, 'r' => true, 'fx' => true, 'fy' => true, 'gradientunits' => true]),
        'stop'     => array_merge($svg_attrs, ['offset' => true, 'stop-color' => true, 'stop-opacity' => true]),
        'title'    => ['id' => true],
        'desc'     => ['id' => true],
    ];
}

function nav_kses_svg(string $svg): string {
    return wp_kses($svg, nav_svg_allowed_html());
}

/**
 * Render SVG icon from a named set.
 */
function nav_icon(string $name, string $class = ''): string {
    $icons = [
        'menu' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>',
        'close' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>',
        'cart' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>',
        'arrow-right' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>',
        'check' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>',
        'flask' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M5 14.5l-.94 2.06A2.25 2.25 0 0 0 6.107 20h11.786a2.25 2.25 0 0 0 2.047-3.44L19 14.5"/></svg>',
        'shield' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>',
        'building' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>',
    ];
    $svg = $icons[$name] ?? '';
    $cls = $class ? ' class="' . esc_attr($class) . '"' : '';
    // Inline icons in this set are decorative — every callsite pairs
    // them with adjacent text (trust cards, badges, footer logo) or
    // wraps a labeled button (cart, search, mobile-toggle which carry
    // their own aria-label). Without aria-hidden, AT readers announce
    // "graphic" alongside the label, doubling the announcement. Add
    // aria-hidden + focusable="false" so the icon stays purely visual.
    return str_replace('<svg ', '<svg' . $cls . ' aria-hidden="true" focusable="false" ', $svg);
}

/* ------------------------------------------------------------------
 * 11. Category Placeholder Images
 * ----------------------------------------------------------------*/

/**
 * Get the placeholder SVG URL for a product category.
 */
/**
 * Revalidate a product's 3D model URL at render time. Admin-save logs
 * non-https URLs but still persists them — render should NEVER emit an
 * http:// GLB on an https page (mixed-content block + silent failure).
 * Returns an empty string if the URL is unsafe.
 */
function nav_safe_glb_url(?string $url, int $product_id = 0): string {
    $url = (string) $url;
    if ($url === '') return '';
    $scheme = wp_parse_url($url, PHP_URL_SCHEME);
    if ($scheme !== 'https') {
        error_log(sprintf(
            '[nav_product] refusing non-https GLB product=%d url=%s',
            $product_id,
            $url
        ));
        return '';
    }
    return $url;
}

function nav_get_category_placeholder(string $slug): string {
    $path = '/assets/images/categories/' . $slug . '.svg';
    if (file_exists(NAV_THEME_DIR . $path)) {
        return NAV_THEME_URI . $path;
    }
    return NAV_THEME_URI . '/assets/images/product-placeholder.svg';
}

/**
 * Resolve the WooCommerce product card image — prefers, in order:
 *   1. The product's WC featured image (admin can override per product)
 *   2. A compound-specific render baked into the theme, derived from
 *      the product's _nav_3d_model_url (vial-{slug}.glb → vial-{slug}-card.webp).
 *      WebP is shipped as the only format — browser support is now
 *      universal across the audience we care about (Safari 14+, all
 *      Chromium, all modern Firefox).
 *   3. The category SVG placeholder
 *
 * Returns a URL string. The source and its srcset-friendly variant are
 * encoded via nav_asset_version() so redeploys bust the browser cache.
 *
 * @return array{src: string, srcset: string|null, width: int, height: int}
 */
function nav_get_product_card_image(WC_Product $product): array {
    // 1. WC featured image (admin-uploaded)
    if (has_post_thumbnail($product->get_id())) {
        $thumb_id = get_post_thumbnail_id($product->get_id());
        $src = wp_get_attachment_image_url($thumb_id, 'product-card');
        if ($src) {
            return ['src' => $src, 'srcset' => wp_get_attachment_image_srcset($thumb_id, 'product-card'), 'width' => 600, 'height' => 750];
        }
    }

    // 2. Compound render derived from the 3D model URL
    $glb_url = (string) get_post_meta($product->get_id(), '_nav_3d_model_url', true);
    if ($glb_url) {
        // vial-ghkcu.glb -> ghkcu (preserving the naming convention we use
        // for both models and posters)
        $basename = pathinfo(wp_parse_url($glb_url, PHP_URL_PATH) ?: '', PATHINFO_FILENAME);
        if ($basename && str_starts_with($basename, 'vial-')) {
            $slug = substr($basename, 5);
            $webp_rel = "/assets/images/vial-{$slug}-card.webp";
            if (file_exists(NAV_THEME_DIR . $webp_rel)) {
                $ver = function_exists('nav_asset_version')
                    ? nav_asset_version(ltrim($webp_rel, '/')) : '';
                $src = NAV_THEME_URI . $webp_rel . ($ver ? '?v=' . $ver : '');
                return ['src' => $src, 'srcset' => null, 'width' => 1024, 'height' => 1280];
            }
        }
    }

    // 3. Category placeholder
    $terms = get_the_terms($product->get_id(), 'product_cat');
    $cat_slug = ($terms && !is_wp_error($terms)) ? $terms[0]->slug : '';
    return ['src' => nav_get_category_placeholder($cat_slug), 'srcset' => null, 'width' => 400, 'height' => 400];
}

/* ------------------------------------------------------------------
 * 12. Excerpt Length
 * ----------------------------------------------------------------*/
add_filter('excerpt_length', fn() => 20);
add_filter('excerpt_more', fn() => '&hellip;');
