<?php
/**
 * WooCommerce Single Product — 7-Point Structure
 *
 * 1. Product name
 * 2. Technical subtitle
 * 3. Short scientific description (1 line)
 * 4. Research focus (bullet points)
 * 5. Studies available
 * 6. Purity / COA information
 * 7. Research-use disclaimer
 *
 * @package NavigatePeptides
 */

defined('ABSPATH') || exit;

get_header();

while (have_posts()) : the_post();
    // WC core sets `global $product` inside the shop loop, but this template
    // runs via wc_get_template_part() and plugins (or an odd query_posts)
    // can land here with $product unset/invalid. Refetch defensively — an
    // invalid product would fatal on the ->get_id() calls below.
    global $product;
    if (!($product instanceof WC_Product) || !$product->exists()) {
        $product = wc_get_product(get_the_ID());
    }
    if (!($product instanceof WC_Product) || !$product->exists()) {
        continue;
    }

    $cat_color  = nav_get_product_category_color($product);
    $subtitle   = get_post_meta($product->get_id(), '_nav_technical_subtitle', true);
    $cas        = get_post_meta($product->get_id(), '_nav_cas_number', true);
    $mw         = get_post_meta($product->get_id(), '_nav_molecular_weight', true);
    $sequence   = get_post_meta($product->get_id(), '_nav_sequence', true);
    $purity     = get_post_meta($product->get_id(), '_nav_purity', true);
    $form       = get_post_meta($product->get_id(), '_nav_form', true);
    $storage    = get_post_meta($product->get_id(), '_nav_storage', true);
    $focus      = get_post_meta($product->get_id(), '_nav_research_focus', true);
    $batch      = get_post_meta($product->get_id(), '_nav_batch_number', true);
    $lab        = get_post_meta($product->get_id(), '_nav_testing_lab', true);
    $coa_url    = get_post_meta($product->get_id(), '_nav_coa_pdf', true);

    $terms = get_the_terms($product->get_id(), 'product_cat');
    $cat   = ($terms && !is_wp_error($terms)) ? $terms[0] : null;
?>

<!-- Breadcrumb -->
<?php woocommerce_breadcrumb(); ?>

<section class="nav-section nav-product-single" style="--cat-color: <?php echo esc_attr($cat_color); ?>">
    <div class="nav-container">
        <div class="nav-product-single__grid">

            <!-- Left: Product Image / 3D Model -->
            <div class="nav-product-single__media">
                <div class="nav-product-single__image">
                    <?php
                    // Check for 3D model GLB file. Revalidate scheme at
                    // render so an admin-saved http:// URL never ships
                    // and causes a mixed-content block on https.
                    $glb_url = nav_safe_glb_url(
                        get_post_meta($product->get_id(), '_nav_3d_model_url', true),
                        $product->get_id()
                    );
                    ?>
                    <?php if ($glb_url) : ?>
                        <model-viewer
                            src="<?php echo esc_url($glb_url); ?>"
                            alt="<?php echo esc_attr(get_the_title()); ?> — 3D interactive model"
                            camera-controls
                            interaction-prompt="none"
                            camera-orbit="0deg 78deg 140%"
                            min-camera-orbit="auto auto 110%"
                            max-camera-orbit="auto auto 220%"
                            field-of-view="25deg"
                            environment-image="neutral"
                            shadow-intensity="0.6"
                            exposure="1.15"
                            loading="eager"
                            reveal="auto"
                        >
                            <?php if (has_post_thumbnail()) : ?>
                                <img slot="poster" src="<?php echo esc_url(get_the_post_thumbnail_url(null, 'product-hero')); ?>" alt="<?php the_title_attribute(); ?>">
                            <?php endif; ?>
                        </model-viewer>
                    <?php elseif (has_post_thumbnail()) : ?>
                        <?php the_post_thumbnail('product-hero'); ?>
                    <?php else : ?>
                        <!-- No product photo, no GLB — fall back to the Navigate Peptides
                             branded research vial. The SVG label panel shows GHK-Cu specs,
                             which is a reasonable category-agnostic default for
                             research-peptide products. -->
                        <img
                            src="<?php echo esc_url(get_template_directory_uri() . '/assets/images/vial-brand.svg'); ?>"
                            alt="<?php echo esc_attr(sprintf(
                                /* translators: %s: product name */
                                __('%s — Navigate Peptides branded research vial placeholder', 'navigate-peptides'),
                                wp_strip_all_tags(get_the_title())
                            )); ?>"
                            class="nav-product-single__placeholder-img"
                            width="420"
                            height="640"
                            decoding="async"
                        >
                    <?php endif; ?>
                </div>
                <?php if ($product->get_gallery_image_ids()) : ?>
                    <div class="nav-product-single__gallery">
                        <?php foreach ($product->get_gallery_image_ids() as $img_id) : ?>
                            <div class="nav-product-single__thumb">
                                <?php echo wp_get_attachment_image($img_id, 'product-card'); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right: Product Details (7-Point Structure) -->
            <div class="nav-product-single__details">

                <!-- Category badge — bg uses the cat color at 20% alpha,
                     but text stays white for AA contrast. Putting the cat
                     color in both fg and bg yields a 1.4-2.9:1 same-hue
                     pairing that fails 4.5:1 across the whole palette. -->
                <?php if ($cat) : ?>
                    <span class="nav-product-single__cat-badge" style="background-color: <?php echo esc_attr($cat_color); ?>33; border: 1px solid <?php echo esc_attr($cat_color); ?>; color: #fff;">
                        <?php echo esc_html($cat->name); ?>
                    </span>
                <?php endif; ?>

                <!-- 1. Product Name -->
                <h1 class="nav-product-single__title"><?php echo esc_html(get_the_title()); ?></h1>

                <!-- 2. Technical Subtitle -->
                <?php if ($subtitle) : ?>
                    <p class="nav-product-single__subtitle"><?php echo esc_html($subtitle); ?></p>
                <?php endif; ?>

                <!-- 3. Short Scientific Description -->
                <div class="nav-product-single__desc">
                    <?php
                    // Use the WooCommerce-standard filter so shortcodes + Gutenberg
                    // blocks + wpautop + wptexturize all run. Previously we called
                    // wp_kses_post + wpautop directly, which dropped shortcode
                    // expansion and block rendering.
                    echo apply_filters('woocommerce_short_description', $product->get_short_description());
                    ?>
                </div>

                <!-- Technical Specifications -->
                <?php if ($cas || $mw || $sequence || $form || $storage) : ?>
                    <div class="nav-product-single__specs">
                        <?php if ($cas) : ?>
                            <div class="nav-spec">
                                <span class="nav-spec__label"><?php esc_html_e('CAS Number', 'navigate-peptides'); ?></span>
                                <span class="nav-spec__value"><?php echo esc_html($cas); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($mw) : ?>
                            <div class="nav-spec">
                                <span class="nav-spec__label"><?php esc_html_e('Molecular Weight', 'navigate-peptides'); ?></span>
                                <span class="nav-spec__value"><?php echo esc_html($mw); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($sequence) : ?>
                            <div class="nav-spec nav-spec--full">
                                <span class="nav-spec__label"><?php esc_html_e('Sequence', 'navigate-peptides'); ?></span>
                                <span class="nav-spec__value"><?php echo esc_html($sequence); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($form) : ?>
                            <div class="nav-spec">
                                <span class="nav-spec__label"><?php esc_html_e('Form', 'navigate-peptides'); ?></span>
                                <span class="nav-spec__value"><?php echo esc_html($form); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($storage) : ?>
                            <div class="nav-spec">
                                <span class="nav-spec__label"><?php esc_html_e('Storage', 'navigate-peptides'); ?></span>
                                <span class="nav-spec__value"><?php echo esc_html($storage); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- 4. Research Focus -->
                <?php if ($focus) : ?>
                    <div class="nav-product-single__focus">
                        <h2><?php esc_html_e('Research Focus', 'navigate-peptides'); ?></h2>
                        <ul>
                            <?php
                            $items = array_filter(array_map('trim', explode("\n", $focus)));
                            foreach ($items as $item) :
                            ?>
                                <li>
                                    <span class="nav-bullet" style="background-color: <?php echo esc_attr($cat_color); ?>"></span>
                                    <?php echo esc_html($item); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- 6. Purity / COA Information -->
                <?php if ($purity || $batch || $lab || $coa_url) : ?>
                    <div class="nav-product-single__coa">
                        <h2><?php esc_html_e('Purity & Verification', 'navigate-peptides'); ?></h2>
                        <div class="nav-product-single__coa-grid">
                            <?php if ($purity) : ?>
                                <span class="nav-mono"><?php echo esc_html($purity); ?></span>
                            <?php endif; ?>
                            <?php if ($coa_url) : ?>
                                <a href="<?php echo esc_url($coa_url); ?>" class="nav-product-single__coa-link" target="_blank" rel="noopener">
                                    <?php esc_html_e('View COA →', 'navigate-peptides'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Batch + COA trust strip — the single biggest purchase
                     signal in this industry; surfaced right at the buy button -->
                <?php if ($batch || $purity || $coa_url) : ?>
                    <div class="nav-product-single__trust-strip">
                        <?php if ($batch) : ?>
                            <div class="nav-trust-strip__item">
                                <span class="nav-trust-strip__label"><?php esc_html_e('Current Batch', 'navigate-peptides'); ?></span>
                                <span class="nav-trust-strip__value"><?php echo esc_html($batch); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($purity) : ?>
                            <div class="nav-trust-strip__item">
                                <span class="nav-trust-strip__label"><?php esc_html_e('HPLC Purity', 'navigate-peptides'); ?></span>
                                <span class="nav-trust-strip__value"><?php echo esc_html($purity); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($coa_url) : ?>
                            <a href="<?php echo esc_url($coa_url); ?>" class="nav-trust-strip__coa" target="_blank" rel="noopener">
                                <?php esc_html_e('View COA', 'navigate-peptides'); ?>
                                <span aria-hidden="true">↗</span>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Price + Add to Cart.
                     Variable products render their own price + qty +
                     ATC inside the variations form once a variation
                     is selected, so suppress the parent's static
                     "$X – $Y" range (CSS :has() also hides it in
                     modern browsers — this is the PHP belt). -->
                <div class="nav-product-single__purchase">
                    <?php if (!$product->is_type('variable')) : ?>
                        <span class="nav-product-single__price"><?php echo $product->get_price_html(); ?></span>
                    <?php endif; ?>
                    <?php woocommerce_template_single_add_to_cart(); ?>
                </div>

                <!-- 7. Research-Use Disclaimer -->
                <div class="nav-product-single__disclaimer">
                    <p class="nav-disclaimer--strong"><?php echo esc_html(nav_get_disclaimer('product')); ?></p>
                    <p><?php echo esc_html(nav_get_disclaimer('sitewide')); ?></p>
                </div>
            </div>
        </div>

        <!-- Product Tabs (Description / Research Focus / COA) -->
        <div class="nav-product-single__tabs">
            <?php woocommerce_output_product_data_tabs(); ?>
        </div>

        <!-- Related Products -->
        <?php woocommerce_output_related_products(); ?>
    </div>
</section>

<?php endwhile; ?>

<?php get_footer(); ?>
