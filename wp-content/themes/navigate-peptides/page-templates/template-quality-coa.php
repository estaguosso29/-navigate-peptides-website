<?php
/**
 * Template Name: Quality — COA Lookup
 *
 * @package NavigatePeptides
 */

get_header();
?>

<?php
// Parse + sanitize the search input ONCE at the top so the input field
// and the WP_Query never diverge (previous code read $_GET twice).
$raw_q        = isset($_GET['coa_search']) ? wp_unslash((string) $_GET['coa_search']) : '';
$search_query = sanitize_text_field($raw_q);
// Cap length — very long strings are just DoS fuel.
$search_query = mb_substr($search_query, 0, 80);

// Cheap rate limit — 20 COA searches per IP per minute. Prevents drive-by
// DoS on the meta_query LIKE scan. Uses the same client-IP resolver as the
// contact form (respects NAV_TRUSTED_PROXIES).
$coa_ratelimit_hit = false;
if ($search_query !== '') {
    $rl_key = 'nav_coa_rl_' . md5(
        (function_exists('nav_contact_client_ip') ? nav_contact_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? ''))
    );
    $hits = (int) get_transient($rl_key);
    if ($hits >= 20) {
        $coa_ratelimit_hit = true;
        $search_query = '';
    } else {
        set_transient($rl_key, $hits + 1, 60);
    }
}
?>

<section class="nav-page-hero">
    <div class="nav-container">
        <h1 class="nav-page-hero__title"><?php esc_html_e('Certificates of Analysis', 'navigate-peptides'); ?></h1>
        <p class="nav-page-hero__subtitle"><?php esc_html_e('Every compound ships with a batch-specific certificate of analysis. Search by product name or batch number.', 'navigate-peptides'); ?></p>
    </div>
</section>

<section class="nav-section">
    <div class="nav-container nav-section--center">
        <div class="nav-coa-lookup">
            <h2 class="nav-coa-lookup__title"><?php esc_html_e('COA Lookup', 'navigate-peptides'); ?></h2>
            <form class="nav-coa-lookup__form" method="get">
                <label for="nav-coa-search" class="nav-form-label"><?php esc_html_e('Batch Number or Product Name', 'navigate-peptides'); ?></label>
                <input
                    type="text"
                    id="nav-coa-search"
                    name="coa_search"
                    class="nav-form-input"
                    maxlength="80"
                    placeholder="<?php esc_attr_e('e.g. BPC-157 or NP-2024-0142', 'navigate-peptides'); ?>"
                    value="<?php echo esc_attr($search_query); ?>"
                >
                <button type="submit" class="nav-btn nav-btn--primary nav-btn--full"><?php esc_html_e('Search Certificates', 'navigate-peptides'); ?></button>
            </form>

            <?php if ($coa_ratelimit_hit) : ?>
                <div class="nav-coa-no-results">
                    <p><?php esc_html_e("You've searched many times recently. Please wait a minute and try again.", 'navigate-peptides'); ?></p>
                </div>
            <?php endif; ?>

            <?php
            // Require >= 3 chars to avoid wildly-expensive LIKE scans on 1-char queries.
            $query_long_enough = strlen($search_query) >= 3;

            if ($search_query !== '' && !$query_long_enough) : ?>
                <div class="nav-coa-no-results">
                    <p><?php esc_html_e('Please enter at least 3 characters.', 'navigate-peptides'); ?></p>
                </div>
            <?php endif;

            // WC offline branch — tell the visitor the search is
            // temporarily unavailable instead of silently returning
            // empty results. The previous behavior looked identical
            // to "no batches match", which is misleading.
            if ($search_query !== '' && $query_long_enough && !class_exists('WooCommerce')) : ?>
                <div class="nav-coa-no-results">
                    <p><?php esc_html_e('Certificate of Analysis lookup is temporarily unavailable. Please try again shortly or contact us for direct COA access.', 'navigate-peptides'); ?></p>
                </div>
            <?php endif;

            if ($search_query !== '' && $query_long_enough && class_exists('WooCommerce')) :
                // Search by batch number (LIKE meta match) OR product name / content.
                // WP_Query ANDs `s` with `meta_query` (the 'relation' key only
                // relates clauses *inside* meta_query), so a single combined
                // query returns results only when BOTH match — i.e. never.
                // Run the two searches separately and merge by ID.
                $base_args = [
                    'post_type'      => 'product',
                    'posts_per_page' => 20,
                    'post_status'    => 'publish',
                    'fields'         => 'ids',
                    'no_found_rows'  => true,
                ];

                $by_name  = new WP_Query($base_args + ['s' => $search_query]);
                $by_batch = new WP_Query($base_args + ['meta_query' => [[
                    'key'     => '_nav_batch_number',
                    'value'   => $search_query,
                    'compare' => 'LIKE',
                ]]]);

                $matched_ids = array_slice(
                    array_unique(array_merge($by_name->posts, $by_batch->posts)),
                    0,
                    20
                );

                $results = new WP_Query([
                    'post_type'      => 'product',
                    'post_status'    => 'publish',
                    'posts_per_page' => 20,
                    'post__in'       => $matched_ids ?: [0],
                    'orderby'        => 'post__in',
                ]);

                if ($results->have_posts()) :
            ?>
                <div class="nav-coa-results">
                    <h3 class="nav-coa-results__title">
                        <?php
                        printf(
                            /* translators: %s: user-supplied search query */
                            esc_html__('Results for "%s"', 'navigate-peptides'),
                            esc_html($search_query)
                        );
                        ?>
                    </h3>
                    <div class="nav-coa-results__grid">
                        <?php while ($results->have_posts()) : $results->the_post();
                            $product_obj = wc_get_product(get_the_ID());
                            if (! $product_obj) continue;

                            $batch   = get_post_meta(get_the_ID(), '_nav_batch_number', true);
                            $purity  = get_post_meta(get_the_ID(), '_nav_purity', true);
                            $lab     = get_post_meta(get_the_ID(), '_nav_testing_lab', true);
                            $coa_url = get_post_meta(get_the_ID(), '_nav_coa_pdf', true);
                        ?>
                        <div class="nav-coa-result-card">
                            <h4 class="nav-coa-result-card__name"><?php echo esc_html($product_obj->get_name()); ?></h4>
                            <?php if ($batch) : ?>
                                <p class="nav-coa-result-card__meta"><span><?php esc_html_e('Batch:', 'navigate-peptides'); ?></span> <?php echo esc_html($batch); ?></p>
                            <?php endif; ?>
                            <?php if ($purity) : ?>
                                <p class="nav-coa-result-card__meta"><span><?php esc_html_e('Purity:', 'navigate-peptides'); ?></span> <?php echo esc_html($purity); ?></p>
                            <?php endif; ?>
                            <?php if ($lab) : ?>
                                <p class="nav-coa-result-card__meta"><span><?php esc_html_e('Lab:', 'navigate-peptides'); ?></span> <?php echo esc_html($lab); ?></p>
                            <?php endif; ?>
                            <?php if ($coa_url) : ?>
                                <a href="<?php echo esc_url($coa_url); ?>" class="nav-btn nav-btn--outline nav-btn--sm" target="_blank" rel="noopener">
                                    <?php esc_html_e('View Certificate', 'navigate-peptides'); ?>
                                </a>
                            <?php else : ?>
                                <p class="nav-text-muted"><?php esc_html_e('Certificate pending publication', 'navigate-peptides'); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; wp_reset_postdata(); ?>
                    </div>
                </div>
            <?php else : ?>
                <div class="nav-coa-no-results">
                    <p>
                        <?php
                        printf(
                            /* translators: %s: user-supplied search query */
                            esc_html__('No certificates found for "%s". Please verify your batch number or contact our team for assistance.', 'navigate-peptides'),
                            esc_html($search_query)
                        );
                        ?>
                    </p>
                </div>
            <?php endif;

            elseif ($search_query !== '' && $query_long_enough) : ?>
                <p class="nav-coa-lookup__note"><?php esc_html_e('WooCommerce is required for COA lookup functionality.', 'navigate-peptides'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php get_footer(); ?>
