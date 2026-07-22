</main><!-- /.nav-main -->

<footer class="nav-footer">
    <div class="nav-container">

        <!-- Footer Navigation Grid -->
        <div class="nav-footer__grid">
            <div class="nav-footer__col">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="nav-footer__logo">
                    <svg viewBox="0 0 288 312.52" preserveAspectRatio="xMidYMid meet" fill="none" xmlns="http://www.w3.org/2000/svg" class="nav-footer__logo-icon" aria-hidden="true" focusable="false"><g transform="translate(0 313) scale(0.1 -0.1)" fill="currentColor"><path d="M0 3123c0 -3 50 -113 111 -242 61 -130 124 -263 139 -296 15 -33 101 -215 190 -405 89 -190 171 -364 181 -387 10 -24 21 -43 24 -43 16 0 210 143 207 153 -3 10 -110 247 -227 502 -20 44 -43 94 -50 110 -8 17 -43 95 -79 174 -37 79 -66 145 -66 147 0 2 357 4 794 4l794 0 111 -206c61 -113 111 -208 111 -212 0 -3 -20 -45 -45 -93 -25 -48 -45 -90 -45 -93 0 -2 25 -10 56 -16 31 -7 81 -21 112 -31 30 -10 61 -19 68 -19 14 0 144 248 144 273 0 8 -41 90 -91 183 -50 93 -127 239 -171 324 -44 85 -85 161 -91 168 -7 9 -238 12 -1093 12 -596 0 -1084 -3 -1084 -7z"/><path d="M2460 3121c0 -14 213 -371 221 -371 3 0 37 57 74 128 37 70 81 150 97 179 15 29 28 57 28 62 0 8 -69 11 -210 11 -125 0 -210 -4 -210 -9z"/><path d="M1885 2124c-254 -30 -455 -90 -680 -204 -456 -230 -817 -593 -941 -947 -20 -56 -11 -83 28 -83 12 0 99 80 231 213 117 118 255 247 307 288 249 197 491 325 761 404 101 29 150 38 322 60l37 5 -14 -32c-21 -47 -102 -229 -180 -403 -90 -199 -132 -292 -206 -460 -129 -296 -144 -326 -151 -313 -12 22 -114 246 -178 393 -86 194 -125 275 -133 275 -12 0 -208 -125 -208 -132 0 -15 517 -1169 529 -1181 7 -7 17 5 32 39 11 27 38 87 59 134 43 97 231 520 345 780 143 323 385 861 391 869 7 8 167 -63 232 -103l69 -43 57 74c31 41 56 78 56 82 0 5 -27 34 -61 65 -173 161 -455 248 -704 220z"/></g></svg>
                    <span class="nav-footer__logo-name"><?php echo esc_html(get_bloginfo('name')); ?></span>
                </a>
                <p class="nav-footer__tagline">
                    Research peptide compounds with verified certificates of analysis. Supplied for controlled laboratory environments.
                </p>
            </div>

            <div class="nav-footer__col">
                <h4 class="nav-footer__heading">
                    <span class="nav-footer__heading-mark" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M7 3h6M8 3v5L4 16a2 2 0 001.6 3h8.8a2 2 0 001.6-3L12 8V3"/><path d="M6 13h8"/></svg>
                    </span>
                    <?php esc_html_e('Compounds', 'navigate-peptides'); ?>
                </h4>
                <ul class="nav-footer__links">
                    <li><a href="<?php echo esc_url(home_url('/compounds/')); ?>"><?php esc_html_e('All Categories', 'navigate-peptides'); ?></a></li>
                    <?php
                    $cats = ['metabolic-research', 'cellular-research', 'tissue-repair-research', 'hormonal-signaling-research', 'cognitive-research', 'dermal-research'];
                    $cat_names = [__('Metabolic Research', 'navigate-peptides'), __('Cellular Research', 'navigate-peptides'), __('Tissue Repair Research', 'navigate-peptides'), __('Hormonal Signaling Research', 'navigate-peptides'), __('Cognitive Research', 'navigate-peptides'), __('Dermal Research', 'navigate-peptides')];
                    foreach ($cats as $i => $slug) :
                    ?>
                        <li><a href="<?php echo esc_url(nav_get_product_cat_url($slug)); ?>"><?php echo esc_html($cat_names[$i]); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="nav-footer__col">
                <h4 class="nav-footer__heading">
                    <span class="nav-footer__heading-mark" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M4 4h12v12H4z"/><path d="M8 8h4v4H8z"/><path d="M4 10h4M12 10h4M10 4v4M10 12v4"/></svg>
                    </span>
                    <?php esc_html_e('Research', 'navigate-peptides'); ?>
                </h4>
                <ul class="nav-footer__links">
                    <li><a href="<?php echo esc_url(home_url('/research/')); ?>"><?php esc_html_e('Research Hub', 'navigate-peptides'); ?></a></li>
                    <li><a href="<?php echo esc_url(home_url('/research/topic/intelligence/')); ?>"><?php esc_html_e('Research Intelligence', 'navigate-peptides'); ?></a></li>
                    <li><a href="<?php echo esc_url(home_url('/research/topic/library/')); ?>"><?php esc_html_e('Research Library', 'navigate-peptides'); ?></a></li>
                    <li><a href="<?php echo esc_url(home_url('/research/topic/framework/')); ?>"><?php esc_html_e('Research Framework', 'navigate-peptides'); ?></a></li>
                    <li><a href="<?php echo esc_url(home_url('/research/topic/emerging/')); ?>"><?php esc_html_e('Emerging Research', 'navigate-peptides'); ?></a></li>
                </ul>
            </div>

            <div class="nav-footer__col">
                <h4 class="nav-footer__heading">
                    <span class="nav-footer__heading-mark" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M10 2l7 4v8l-7 4-7-4V6l7-4z"/><path d="M10 10l7-4M10 10l-7-4M10 10v8"/></svg>
                    </span>
                    <?php esc_html_e('Quality', 'navigate-peptides'); ?>
                </h4>
                <ul class="nav-footer__links">
                    <li><a href="<?php echo esc_url(home_url('/quality/')); ?>"><?php esc_html_e('Quality Overview', 'navigate-peptides'); ?></a></li>
                    <li><a href="<?php echo esc_url(home_url('/quality/testing/')); ?>"><?php esc_html_e('Testing & Verification', 'navigate-peptides'); ?></a></li>
                    <li><a href="<?php echo esc_url(home_url('/quality/coa/')); ?>"><?php esc_html_e('Lab Results / COA', 'navigate-peptides'); ?></a></li>
                    <li><a href="<?php echo esc_url(home_url('/quality/manufacturing/')); ?>"><?php esc_html_e('Manufacturing Standards', 'navigate-peptides'); ?></a></li>
                    <li><a href="<?php echo esc_url(home_url('/quality/handling/')); ?>"><?php esc_html_e('Handling & Storage', 'navigate-peptides'); ?></a></li>
                </ul>
            </div>

            <div class="nav-footer__col">
                <h4 class="nav-footer__heading">
                    <span class="nav-footer__heading-mark" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" width="14" height="14"><path d="M3 17h14M4 4h12v13H4z"/><path d="M7 9h2M7 13h2M11 9h2M11 13h2M9 4v3h2V4"/></svg>
                    </span>
                    <?php esc_html_e('Company', 'navigate-peptides'); ?>
                </h4>
                <ul class="nav-footer__links">
                    <li><a href="<?php echo esc_url(home_url('/about/')); ?>"><?php esc_html_e('About', 'navigate-peptides'); ?></a></li>
                    <li><a href="<?php echo esc_url(home_url('/about/standards/')); ?>"><?php esc_html_e('Standards', 'navigate-peptides'); ?></a></li>
                    <li><a href="<?php echo esc_url(nav_get_contact_url()); ?>"><?php esc_html_e('Contact / Request Access', 'navigate-peptides'); ?></a></li>
                </ul>
            </div>
        </div>

        <!-- Newsletter signup — posts to /wp-json/nav/v1/subscribe -->
        <section class="nav-newsletter" aria-labelledby="nav-newsletter-title">
            <div class="nav-newsletter__copy">
                <h3 id="nav-newsletter-title" class="nav-newsletter__title">
                    <?php esc_html_e('Research briefing', 'navigate-peptides'); ?>
                </h3>
                <p class="nav-newsletter__lede">
                    <?php esc_html_e('New compound additions, COA releases, and research commentary — no promotions, no hype.', 'navigate-peptides'); ?>
                </p>
            </div>
            <form class="nav-newsletter__form" data-nav-subscribe novalidate>
                <label class="screen-reader-text" for="nav-newsletter-email"><?php esc_html_e('Email address', 'navigate-peptides'); ?></label>
                <input
                    id="nav-newsletter-email"
                    type="email"
                    name="email"
                    class="nav-newsletter__input"
                    placeholder="<?php esc_attr_e('you@lab.edu', 'navigate-peptides'); ?>"
                    required
                    autocomplete="email"
                    inputmode="email"
                >
                <!-- Honeypot — hidden from humans; bots often fill every text field -->
                <input
                    type="text"
                    name="nav_hp"
                    tabindex="-1"
                    autocomplete="off"
                    aria-hidden="true"
                    class="nav-newsletter__hp"
                >
                <button type="submit" class="nav-newsletter__submit">
                    <span class="nav-newsletter__submit-label"><?php esc_html_e('Subscribe', 'navigate-peptides'); ?></span>
                    <span aria-hidden="true">→</span>
                </button>
                <p class="nav-newsletter__feedback" role="status" aria-live="polite"></p>
                <p class="nav-newsletter__disclaimer">
                    <?php
                    $privacy_url = function_exists('nav_privacy_url') ? nav_privacy_url() : '';
                    if ($privacy_url) {
                        echo wp_kses(
                            sprintf(
                                /* translators: %s: privacy policy link */
                                __('By subscribing you agree to receive occasional research emails and our %s. Unsubscribe anytime.', 'navigate-peptides'),
                                '<a href="' . esc_url($privacy_url) . '">' . esc_html__('Privacy Policy', 'navigate-peptides') . '</a>'
                            ),
                            ['a' => ['href' => []]]
                        );
                    } else {
                        esc_html_e('By subscribing you agree to receive occasional research emails. Unsubscribe anytime.', 'navigate-peptides');
                    }
                    ?>
                </p>
            </form>
        </section>

        <!-- Divider -->
        <hr class="nav-footer__divider">

        <!-- Accepted Payment Methods — trust signal, compliance-safe.
             Real brand marks read as more legitimate than text labels. -->
        <div class="nav-footer__payments" aria-label="<?php esc_attr_e('Accepted payment methods', 'navigate-peptides'); ?>">
            <?php
            $nav_payments = [
                'visa'       => __('Visa', 'navigate-peptides'),
                // Mastercard mark hidden per processor underwriting feedback
                // (July 2026) — do not re-enable without processor sign-off.
                // 'mastercard' => __('Mastercard', 'navigate-peptides'),
                'amex'       => __('American Express', 'navigate-peptides'),
                'discover'   => __('Discover', 'navigate-peptides'),
                'bitcoin'    => __('Bitcoin', 'navigate-peptides'),
                'ethereum'   => __('Ethereum', 'navigate-peptides'),
                'usdc'       => __('USD Coin', 'navigate-peptides'),
                'ach'        => __('ACH bank transfer', 'navigate-peptides'),
            ];
            $pay_ver = function_exists('nav_asset_version')
                ? nav_asset_version('assets/images/payments/visa.svg') : '';
            foreach ($nav_payments as $slug => $label) :
                $src = get_template_directory_uri() . '/assets/images/payments/' . $slug . '.svg'
                     . ($pay_ver ? '?v=' . $pay_ver : '');
            ?>
                <span class="nav-footer__payment-method" role="img" aria-label="<?php echo esc_attr($label); ?>">
                    <img
                        src="<?php echo esc_url($src); ?>"
                        alt="<?php echo esc_attr($label); ?>"
                        loading="lazy"
                        decoding="async"
                        width="52"
                        height="32"
                    >
                </span>
            <?php endforeach; ?>
        </div>

        <!-- Sitewide Compliance Disclaimer -->
        <div class="nav-footer__disclaimer">
            <p><?php echo esc_html(nav_get_disclaimer('sitewide')); ?></p>
        </div>


        <!-- Bottom Bar -->
        <div class="nav-footer__bottom">
            <p>&copy; <?php echo esc_html(wp_date('Y')); ?>
                <?php echo esc_html(defined('NAV_BIZ_DBA') ? NAV_BIZ_DBA : get_bloginfo('name')); ?>.
                <?php esc_html_e('All rights reserved.', 'navigate-peptides'); ?>
            </p>
            <nav class="nav-footer__legal" aria-label="<?php esc_attr_e('Legal', 'navigate-peptides'); ?>">
                <?php
                $privacy  = function_exists('nav_privacy_url')  ? nav_privacy_url()  : '';
                $terms    = function_exists('nav_terms_url')    ? nav_terms_url()    : '';
                $refund   = function_exists('nav_refund_url')   ? nav_refund_url()   : home_url('/refund-policy/');
                $shipping = function_exists('nav_shipping_url') ? nav_shipping_url() : home_url('/shipping-policy/');
                if ($privacy) : ?>
                    <a href="<?php echo esc_url($privacy); ?>"><?php esc_html_e('Privacy', 'navigate-peptides'); ?></a>
                <?php endif;
                if ($terms) : ?>
                    <a href="<?php echo esc_url($terms); ?>"><?php esc_html_e('Terms', 'navigate-peptides'); ?></a>
                <?php endif;
                if ($refund) : ?>
                    <a href="<?php echo esc_url($refund); ?>"><?php esc_html_e('Refund Policy', 'navigate-peptides'); ?></a>
                <?php endif;
                if ($shipping) : ?>
                    <a href="<?php echo esc_url($shipping); ?>"><?php esc_html_e('Shipping Policy', 'navigate-peptides'); ?></a>
                <?php endif; ?>
                <a href="<?php echo esc_url(nav_get_contact_url()); ?>"><?php esc_html_e('Contact', 'navigate-peptides'); ?></a>
            </nav>
            <p class="nav-footer__ruo"><?php esc_html_e('Research Use Only', 'navigate-peptides'); ?></p>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
