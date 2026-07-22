<?php
/**
 * Compliance & Disclaimers
 *
 * Processor-mandated exact text. DO NOT modify without legal review.
 * AllayPay/NMI and Mastercard BRAM require these disclaimers verbatim.
 *
 * @package NavigatePeptides
 */

defined('ABSPATH') || exit;

/**
 * Redact PII to a short stable token suitable for error_log / audit
 * trails. SHA-256 truncated to 12 hex chars gives ~48 bits of entropy
 * — enough to spot a repeated offender across logs while losing the
 * raw value entirely, so the log line is no longer a PII spill.
 *
 * Empty input returns empty string (so call-sites can short-circuit
 * without nesting). Salted by AUTH_SALT so logs from one site can't
 * be brute-forced against an email/IP list pulled from another.
 */
function nav_redact(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $salt = defined('AUTH_SALT') ? AUTH_SALT : 'nav-fallback-salt';
    return substr(hash('sha256', $salt . '|' . $value), 0, 12);
}

/**
 * Push an admin-facing warning onto a transient queue. The
 * `nav_admin_notices` action below drains the queue on every admin
 * page load and renders accumulated warnings as a standard WP notice.
 *
 * This is the "loop closer" the audit asked for: most failure branches
 * in inc/seo.php, inc/woocommerce.php, etc. already error_log() with
 * good context, but no human ever reads PHP error logs on wp.com
 * Atomic — so silent UX/compliance degradation only surfaces during
 * an underwriter audit. Surfacing the same message to wp-admin makes
 * the failure visible to the operator the next time they log in.
 *
 * @param string $code    Stable key used for dedup (e.g. 'json_ld_encode').
 * @param string $message Plain-text message rendered to the admin.
 */
function nav_admin_warn(string $code, string $message): void {
    $queue = get_transient('nav_admin_warnings');
    if (!is_array($queue)) {
        $queue = [];
    }
    // Bounded — last 20 unique codes, no infinite growth on a hot path.
    $queue[$code] = $message;
    if (count($queue) > 20) {
        $queue = array_slice($queue, -20, null, true);
    }
    // 7-day TTL so warnings are visible long enough for the operator to
    // see them, but auto-clear if the underlying issue resolves.
    set_transient('nav_admin_warnings', $queue, 7 * DAY_IN_SECONDS);
}

add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) {
        return;
    }
    $queue = get_transient('nav_admin_warnings');
    if (empty($queue) || !is_array($queue)) {
        return;
    }
    foreach ($queue as $code => $message) {
        printf(
            '<div class="notice notice-warning is-dismissible"><p><strong>%s</strong> %s <em>(%s)</em></p></div>',
            esc_html__('Navigate Peptides:', 'navigate-peptides'),
            esc_html((string) $message),
            esc_html((string) $code)
        );
    }
    delete_transient('nav_admin_warnings');
});

/**
 * Get compliance disclaimer by key.
 */
function nav_get_disclaimer(string $key = 'sitewide'): string {
    // 'fda' is the full FDA disclaimer mandated verbatim by processor
    // underwriting feedback (July 2026) — see docs/PROCESSOR_FEEDBACK_2026-07.
    // It intentionally contains terms ("diagnose", "treat", "cure",
    // "prevent") that are banned everywhere else on the site — they are
    // permitted ONLY inside this canonical string. Do not edit or
    // paraphrase either disclaimer.
    if ($key === 'fda') {
        return 'The statements made regarding these products have not been evaluated by the Food and Drug Administration. The efficacy of these products has not been confirmed by FDA-approved research. These products are not intended to diagnose, treat, cure, or prevent any disease.';
    }
    // Single canonical RUO line. Every other key resolves to the same text
    // so messaging stays consistent across product cards, PDP tabs,
    // cart, footer, and the compliance scanner block.
    return 'All products sold on this website are intended for research and identification purposes only. These products are not intended for human dosing, injection, or ingestion.';
}

/**
 * Render product disclaimer block.
 */
function nav_product_disclaimer(): void {
    ?>
    <div class="nav-disclaimer nav-disclaimer--product">
        <p><?php echo esc_html(nav_get_disclaimer('product')); ?></p>
    </div>
    <?php
}

/**
 * Render sitewide disclaimer block.
 */
function nav_sitewide_disclaimer(): void {
    ?>
    <div class="nav-disclaimer nav-disclaimer--sitewide">
        <p><?php echo esc_html(nav_get_disclaimer('sitewide')); ?></p>
    </div>
    <?php
}

/**
 * Add structured data for compliance (visible to Mastercard scanners).
 */
add_action('wp_footer', function () {
    ?>
    <div class="nav-compliance-footer" aria-label="Research use disclaimer">
        <div class="nav-container">
            <p class="nav-compliance-footer__text">
                <?php echo esc_html(nav_get_disclaimer('sitewide')); ?>
            </p>
        </div>
    </div>
    <?php
}, 99);
