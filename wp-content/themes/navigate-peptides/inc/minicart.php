<?php
/**
 * Minicart drawer — slides in from the right on add-to-cart.
 * Rendered server-side in wp_footer and refreshed via WC fragments.
 *
 * @package NavigatePeptides
 */

defined('ABSPATH') || exit;

/**
 * Render the minicart drawer markup.
 *
 * Uses Woo's woocommerce_mini_cart template for the item rows so all the
 * existing product-type / variation logic continues to work, then wraps
 * it in our own drawer chrome. Fragment refresh targets the inner list
 * div so the drawer chrome stays in place.
 */
function nav_render_minicart(): void {
    if (!function_exists('WC')) return;
    ?>
    <aside
        class="nav-minicart"
        id="nav-minicart"
        role="dialog"
        aria-modal="true"
        aria-labelledby="nav-minicart-title"
        aria-hidden="true"
    >
        <button type="button" class="nav-minicart__scrim" id="nav-minicart-scrim" aria-label="<?php esc_attr_e('Close cart', 'navigate-peptides'); ?>" tabindex="-1"></button>

        <div class="nav-minicart__panel">
            <header class="nav-minicart__header">
                <h2 id="nav-minicart-title" class="nav-minicart__title"><?php esc_html_e('Cart', 'navigate-peptides'); ?></h2>
                <button type="button" class="nav-minicart__close" id="nav-minicart-close" aria-label="<?php esc_attr_e('Close cart', 'navigate-peptides'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="22" height="22"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </header>

            <div class="nav-minicart__body widget_shopping_cart_content">
                <?php woocommerce_mini_cart(); ?>
            </div>

            <footer class="nav-minicart__footer">
                <p class="nav-minicart__disclaimer"><?php echo esc_html(nav_get_disclaimer('product')); ?></p>
            </footer>
        </div>
    </aside>
    <?php
}

// Render once per page in the footer. Skip when the drawer would be
// redundant (cart and checkout pages already show the full cart).
add_action('wp_footer', function () {
    if (function_exists('is_cart') && (is_cart() || is_checkout())) return;
    nav_render_minicart();
}, 10);

/**
 * Refresh the drawer body via WC fragments so add/remove/update reflects
 * without a full reload. Gated on wp_doing_ajax() — without the guard
 * this filter ran on every non-AJAX page render too, double-invoking
 * woocommerce_mini_cart() and doubling cart-totalization work per request.
 *
 * The wrapper's class list MUST stay byte-identical to the server render in
 * nav_render_minicart() above. WC's cart-fragments.js applies fragments with
 * $(key).replaceWith(value) — on the AJAX response AND on the sessionStorage
 * cached path that runs on every page load — so the element here REPLACES the
 * drawer body wholesale. Emitting only `widget_shopping_cart_content` dropped
 * `nav-minicart__body`, and every drawer style in woocommerce.css is scoped to
 * that class, so the whole drawer rendered unstyled. Change one, change both.
 */
add_filter('woocommerce_add_to_cart_fragments', function (array $fragments) {
    if (!wp_doing_ajax()) {
        return $fragments;
    }
    ob_start();
    ?>
    <div class="nav-minicart__body widget_shopping_cart_content"><?php woocommerce_mini_cart(); ?></div>
    <?php
    $fragments['div.widget_shopping_cart_content'] = ob_get_clean();
    return $fragments;
});

/**
 * Auto-open the drawer when an item lands in the cart. Transient keyed by
 * nav_visitor_key() so guests (the only cohort pre-account) get the UX
 * too — previously keyed on wp_get_session_token() which returns '' for
 * guests, silently breaking the advertised auto-open.
 */
add_action('woocommerce_add_to_cart', function () {
    set_transient(
        'nav_minicart_autoopen_' . md5(nav_visitor_key()),
        1,
        5 * MINUTE_IN_SECONDS
    );
}, 99);

add_action('wp_footer', function () {
    // Don't pop a drawer over pages where it would be confusing:
    //   - Cart / checkout already show the full cart UI.
    //   - Order-received (thankyou) shows order details; cart is empty anyway.
    if (function_exists('is_cart') && (is_cart() || is_checkout())) return;
    if (function_exists('is_order_received_page') && is_order_received_page()) return;

    $key = 'nav_minicart_autoopen_' . md5(nav_visitor_key());
    if (!get_transient($key)) return;
    delete_transient($key);

    // Bail out if the cart ended up empty (edge case — item removed mid-flow).
    if (function_exists('WC') && WC()->cart && WC()->cart->is_empty()) return;
    ?>
    <script>
      (function () {
        function openDrawer() {
            if (typeof window.navMinicartOpen === 'function') window.navMinicartOpen();
        }
        // main.js may or may not have bound its handler by the time this runs.
        // Use document.readyState instead of betting on DOMContentLoaded —
        // if the event has already fired, the listener never re-fires.
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', openDrawer);
        } else {
            // Defer one tick so main.js has a chance to attach navMinicartOpen
            // if we raced it at scripts-in-head parse order.
            setTimeout(openDrawer, 0);
        }
      })();
    </script>
    <?php
}, 101);

/**
 * Hijack the header cart link to open the drawer instead of navigating
 * to /cart/. The PHP href remains as a no-JS fallback, and we only
 * swallow PRIMARY-button clicks without modifier keys so cmd-click,
 * middle-click, ctrl-click still open the cart in a new tab as expected.
 * Also skipped on the cart page itself, where the user is already there.
 */
add_action('wp_footer', function () {
    if (function_exists('is_cart') && (is_cart() || is_checkout())) return;
    ?>
    <script>
      (function () {
        var cartLink = document.querySelector('.nav-header__cart');
        if (!cartLink) return;
        cartLink.addEventListener('click', function (e) {
            // Honor standard open-in-new-tab / -window modifiers.
            if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            // Also honor the semantic case where the user is ALREADY on
            // the cart page — bail so the browser doesn't interrupt a
            // same-page-anchor navigation.
            if (typeof window.navMinicartOpen !== 'function') return;
            e.preventDefault();
            window.navMinicartOpen();
        });
      })();
    </script>
    <?php
}, 102);
