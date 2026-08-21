<?php
/**
 * Create the certified Azoth catalogue items that are not yet on the site.
 *
 * Run via WP-CLI:
 *   wp eval-file scripts/coa-import-products.php <coa-map.json> [dry-run] [publish]
 *
 * <coa-map.json> maps product slug -> that product's OWN certificate URL, so
 * each page links only its own certificate (client 2026-08-10).
 *
 * Products are created as DRAFTS and published by re-running with `publish` —
 * but ONLY items that carry a 'price' (client's list, 2026-08-09). A product
 * without a price is not purchasable, so publishing it would show a broken
 * listing; unpriced items (currently GLP-1 S) stay draft no matter what.
 *
 * Idempotent: products are addressed by slug, existing ones are updated in
 * place rather than duplicated, and re-running changes nothing on its own.
 *
 * Deliberately NOT set: `_nav_cas_number` and `_nav_sequence`. The
 * certificates do not carry them and inventing chemical identifiers on a
 * storefront a payment processor audits is worse than leaving them blank —
 * the theme omits empty fields. Fill them from the supplier's spec sheet.
 */

/** @var array $args WP-CLI eval-file injects positional args into scope. */
if (!isset($args) || !is_array($args)) {
    fwrite(STDERR, "Run via WP-CLI: wp eval-file scripts/coa-import-products.php <url> [dry-run]\n");
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
$dry     = in_array('dry-run', $opts, true);
$publish = in_array('publish', $opts, true);

$TAIL = "\n\nSupplied as a lyophilized solid for reconstitution by qualified "
      . "laboratory personnel. Each batch is independently HPLC-verified with "
      . "identity confirmed by mass spectrometry.";
$FORM    = 'Lyophilized solid for laboratory reconstitution.';
$STORAGE = 'Store at -20 °C. Protect from light and moisture. Reconstitute under sterile laboratory conditions. Single-use vial.';

/**
 * slug => product definition. lab/lot/purity/size come from the certificate;
 * see docs/Azoth_Catalog_COAs_Compiled.md for the source page of each.
 */
$products = [

'ss-31' => [
  'title' => 'SS-31', 'cat' => 'cellular-research', 'size' => '50mg',
  'price' => '137.99',
  'lab' => 'BioViridian', 'lot' => '07022026-046', 'purity' => '99.73%', 'mw' => '639.8 g·mol⁻¹',
  'subtitle' => 'Mitochondria-Targeted Tetrapeptide',
  'excerpt' => 'Cardiolipin-binding tetrapeptide supplied lyophilized for in-vitro mitochondrial membrane and bioenergetics research.',
  'body' => 'SS-31 is a synthetic tetrapeptide that associates with cardiolipin in the inner mitochondrial membrane. Researchers use the compound in cell-culture and isolated-mitochondria preparations to investigate electron-transport-chain organisation, cristae membrane architecture, and reactive-oxygen-species signalling.',
  'focus' => ["Mitochondrial membrane and cardiolipin interaction studies", "Electron-transport-chain organisation research", "Reactive-oxygen-species signalling in cultured cells", "Mass-spec identity verification per batch"],
],

'aod-9604' => [
  'title' => 'AOD-9604', 'cat' => 'metabolic-research', 'size' => '5mg',
  'price' => '59.99',
  'lab' => 'Freedom Diagnostics', 'lot' => '261807', 'purity' => '99.11%',
  'subtitle' => 'Modified hGH Fragment 177-191',
  'excerpt' => 'C-terminal fragment analog of human growth hormone supplied lyophilized for in-vitro lipolytic-pathway research.',
  'body' => 'AOD-9604 is a modified fragment corresponding to residues 177-191 of human growth hormone with an added N-terminal tyrosine. Researchers use the compound in adipocyte culture and biochemical preparations to investigate lipolytic signalling cascades and β3-adrenergic receptor interactions in model systems.',
  'focus' => ["Lipolytic signalling pathway research", "β3-adrenergic receptor interaction studies", "Adipocyte culture model investigation", "Mass-spec identity verification per batch"],
],

'cjc-1295-ipamorelin' => [
  'title' => 'CJC-1295 (No DAC) & Ipamorelin', 'cat' => 'hormonal-signaling-research', 'size' => '5mg/5mg',
  'price' => '69.99',
  'lab' => 'Optiq Health Labs', 'lot' => '0335.OPT.260729', 'purity' => '99.99%',
  'subtitle' => 'Dual Secretagogue Research Blend',
  'excerpt' => 'Combination of a modified GRF(1-29) analog and a selective pentapeptide secretagogue, supplied lyophilized for in-vitro receptor research.',
  'body' => 'This preparation combines CJC-1295 (No DAC), a tetrasubstituted analog of growth-hormone-releasing hormone (1-29), with Ipamorelin, a selective pentapeptide ghrelin-receptor agonist. Researchers use the combination in receptor-binding and pituitary cell-culture preparations to investigate GHRH-receptor and GHS-R1a signalling in parallel within one model system.',
  'focus' => ["GHRH-receptor signalling research", "GHS-R1a receptor activation studies", "Parallel-pathway model investigation", "Mass-spec identity verification per batch"],
],

'ghrp-6' => [
  'title' => 'GHRP-6', 'cat' => 'hormonal-signaling-research', 'size' => '5mg',
  'price' => '34.99',
  'lab' => 'BioViridian', 'lot' => '07022026-024', 'purity' => '99.82%', 'mw' => '873.3 g·mol⁻¹',
  'subtitle' => 'Hexapeptide GH Secretagogue',
  'excerpt' => 'Synthetic hexapeptide ghrelin-receptor agonist supplied lyophilized for in-vitro GHS-R1a signaling research.',
  'body' => 'GHRP-6 is a synthetic hexapeptide that binds the growth-hormone secretagogue receptor GHS-R1a. Researchers use the compound in receptor-binding assays and pituitary cell-culture preparations to investigate secretagogue-receptor pharmacology and downstream calcium-signalling cascades.',
  'focus' => ["GHS-R1a receptor-binding research", "Calcium-signalling cascade studies", "Secretagogue structure-activity investigation", "Mass-spec identity verification per batch"],
],

'igf-1-lr3' => [
  'title' => 'IGF-1 LR3', 'cat' => 'hormonal-signaling-research', 'size' => '1mg',
  'price' => '89.99',
  'lab' => 'Freedom Diagnostics', 'lot' => '261807', 'purity' => '99.87%',
  'subtitle' => 'Long R3 Insulin-Like Growth Factor-1 Analog',
  'excerpt' => 'IGF-1 analog with reduced binding-protein affinity supplied lyophilized for in-vitro IGF-receptor research.',
  'body' => 'IGF-1 LR3 is an analog of insulin-like growth factor 1 carrying an arginine substitution at position 3 and a 13-residue N-terminal extension, which reduces association with IGF-binding proteins. Researchers use the compound in cell-culture preparations to investigate IGF-1 receptor activation, PI3K/Akt cascade signalling, and myoblast differentiation in tissue-model systems.',
  'focus' => ["IGF-1 receptor activation research", "PI3K/Akt cascade signalling studies", "Myoblast differentiation model investigation", "Mass-spec identity verification per batch"],
],

'ipamorelin' => [
  'title' => 'Ipamorelin', 'cat' => 'hormonal-signaling-research', 'size' => '10mg',
  'price' => '74.99',
  'lab' => 'Freedom Diagnostics', 'lot' => 'Clear Cap/Silver Crimp', 'purity' => '99.79%',
  'subtitle' => 'Selective Pentapeptide GH Secretagogue',
  'excerpt' => 'Selective pentapeptide ghrelin-receptor agonist supplied lyophilized for in-vitro GHS-R1a signaling research.',
  'body' => 'Ipamorelin is a synthetic pentapeptide acting as a selective agonist at the growth-hormone secretagogue receptor. Researchers use the compound in receptor-selectivity assays and pituitary cell-culture preparations to investigate GHS-R1a activation without the cross-reactivity characteristic of earlier secretagogue series.',
  'focus' => ["GHS-R1a receptor selectivity research", "Pituitary cell-culture model studies", "Secretagogue cross-reactivity investigation", "Mass-spec identity verification per batch"],
],

'sermorelin' => [
  'title' => 'Sermorelin', 'cat' => 'hormonal-signaling-research', 'size' => '10mg',
  'price' => '49.99',
  'lab' => 'Freedom Diagnostics', 'lot' => '261807', 'purity' => '99.14%',
  'subtitle' => 'GHRH (1-29) Amide',
  'excerpt' => '29-residue N-terminal fragment of growth-hormone-releasing hormone supplied lyophilized for in-vitro GHRH-receptor research.',
  'body' => 'Sermorelin corresponds to the first 29 amino acids of human growth-hormone-releasing hormone, the shortest fragment retaining full receptor activity. Researchers use the compound in receptor-binding and pituitary cell-culture preparations to investigate GHRH-receptor structure-activity relationships and cAMP signalling.',
  'focus' => ["GHRH-receptor structure-activity research", "cAMP signalling cascade studies", "Fragment-activity comparison investigation", "Mass-spec identity verification per batch"],
],

'tb-500' => [
  'title' => 'TB-500', 'cat' => 'tissue-repair-research', 'size' => '10mg',
  'price' => '117.00',
  'lab' => 'Freedom Diagnostics', 'lot' => '261807', 'purity' => '99.85%',
  'subtitle' => 'Thymosin β4 Fragment — Actin-Binding Peptide',
  'excerpt' => 'Synthetic actin-sequestering fragment of thymosin β4 supplied lyophilized for in-vitro cytoskeletal research.',
  'body' => 'TB-500 is a synthetic peptide corresponding to the actin-binding domain of thymosin β4. Researchers use the compound in cell-culture and biochemical preparations to investigate actin sequestration and polymerisation kinetics, cell-migration assays, and angiogenic signalling in tissue-model systems.',
  'focus' => ["Actin sequestration and polymerisation research", "Cell-migration assay investigation", "Angiogenic signalling in tissue models", "Mass-spec identity verification per batch"],
],

'5-amino-1mq' => [
  'title' => '5-Amino-1MQ', 'cat' => 'metabolic-research', 'size' => '10mg',
  'price' => '49.99',
  'lab' => 'Freedom Diagnostics', 'lot' => '261807', 'purity' => '99.73%',
  'subtitle' => 'NNMT Inhibitor — Methylquinolinium Salt',
  'excerpt' => 'Small-molecule nicotinamide N-methyltransferase inhibitor supplied lyophilized for in-vitro NAD-pathway research.',
  'body' => '5-Amino-1MQ is a membrane-permeable quinolinium compound that inhibits nicotinamide N-methyltransferase (NNMT). Researchers use the compound in adipocyte and hepatocyte culture preparations to investigate NNMT enzymology, nicotinamide salvage, and regulation of cellular NAD+ pools.',
  'focus' => ["NNMT enzyme inhibition research", "Nicotinamide salvage pathway studies", "Cellular NAD+ pool regulation investigation", "Mass-spec identity verification per batch"],
],

'n-acetyl-selank' => [
  'title' => 'N-Acetyl Selank', 'cat' => 'cognitive-research', 'size' => '10mg',
  'price' => '54.99',
  'lab' => 'BioViridian', 'lot' => '07022026-031', 'purity' => '99.84%', 'mw' => '793.9 g·mol⁻¹',
  'subtitle' => 'N-Acetylated Tuftsin Analog — Heptapeptide',
  'excerpt' => 'N-acetylated analog of the tuftsin-derived heptapeptide Selank, supplied lyophilized for in-vitro neuropeptide research.',
  'body' => 'N-Acetyl Selank is an N-terminally acetylated form of Selank, a synthetic heptapeptide derived from the endogenous tetrapeptide tuftsin. Acetylation increases resistance to aminopeptidase cleavage. Researchers use the compound in neuronal cell-culture and receptor-binding preparations to investigate GABAergic and monoaminergic signalling and BDNF expression in model systems.',
  'focus' => ["GABAergic signalling research in cultured neurons", "BDNF expression studies", "Peptide stability and aminopeptidase resistance investigation", "Mass-spec identity verification per batch"],
],

'n-acetyl-semax' => [
  'title' => 'N-Acetyl Semax', 'cat' => 'cognitive-research', 'size' => '10mg',
  'price' => '54.99',
  'lab' => 'Freedom Diagnostics', 'lot' => '261807', 'purity' => '99.65%',
  'subtitle' => 'N-Acetylated ACTH(4-7) Analog — Heptapeptide',
  'excerpt' => 'N-acetylated analog of the ACTH(4-7)-derived heptapeptide Semax, supplied lyophilized for in-vitro neuropeptide research.',
  'body' => 'N-Acetyl Semax is an N-terminally acetylated form of Semax, a synthetic heptapeptide based on the ACTH(4-7) fragment extended with Pro-Gly-Pro. Acetylation increases resistance to enzymatic degradation. Researchers use the compound in neuronal cell-culture preparations to investigate BDNF and NGF expression and neurotrophic cascade activation in model systems.',
  'focus' => ["BDNF and NGF expression research", "Neurotrophic cascade activation studies", "Peptide stability investigation", "Mass-spec identity verification per batch"],
],

'kisspeptin' => [
  'title' => 'Kisspeptin', 'cat' => 'hormonal-signaling-research', 'size' => '10mg',
  'price' => '49.99',
  'lab' => 'Freedom Diagnostics', 'lot' => '261807', 'purity' => '99.55%',
  'subtitle' => 'KISS1-Derived Decapeptide — GPR54 Ligand',
  'excerpt' => 'Decapeptide product of the KISS1 gene supplied lyophilized for in-vitro GPR54 receptor research.',
  'body' => 'Kisspeptin-10 is the C-terminal decapeptide fragment of the KISS1 gene product and the endogenous ligand of the G-protein-coupled receptor GPR54 (KISS1R). Researchers use the compound in receptor-binding and hypothalamic cell-culture preparations to investigate GPR54 activation and GnRH-neuron signalling in model systems.',
  'focus' => ["GPR54 receptor-binding research", "GnRH-neuron signalling studies", "Hypothalamic cell-culture model investigation", "Mass-spec identity verification per batch"],
],

'pt-141' => [
  'title' => 'PT-141', 'cat' => 'dermal-research', 'size' => '10mg',
  'price' => '44.99',
  'lab' => 'Freedom Diagnostics', 'lot' => '261807', 'purity' => '99.74%',
  'subtitle' => 'Synthetic Melanocortin Analog',
  'excerpt' => 'Cyclic heptapeptide melanocortin-receptor agonist supplied lyophilized for in-vitro MC receptor research.',
  'body' => 'PT-141 is a cyclic heptapeptide analog of α-melanocyte-stimulating hormone and a metabolite of Melanotan 2. Researchers use the compound in receptor-binding and cell-culture preparations to investigate melanocortin receptor subtype selectivity, particularly MC3R and MC4R signalling.',
  'focus' => ["Melanocortin receptor subtype selectivity research", "MC3R and MC4R signalling studies", "Cyclic peptide structure-activity investigation", "Mass-spec identity verification per batch"],
],

'glow' => [
  'title' => 'GLOW', 'cat' => 'tissue-repair-research', 'size' => '70mg',
  'price' => '143.99',
  'lab' => 'Freedom Diagnostics', 'lot' => '261807', 'purity' => '99.92%',
  'subtitle' => 'GHK-Cu / TB-500 / BPC-157 Research Blend',
  'excerpt' => 'Three-component research blend of a copper tripeptide and two synthetic fragments, supplied lyophilized for in-vitro tissue-model research.',
  'body' => 'GLOW combines GHK-Cu (copper tripeptide complex), TB-500 (thymosin β4 actin-binding fragment) and BPC-157 (synthetic pentadecapeptide) in a single lyophilized preparation. Researchers use the combination in extracellular-matrix and cell-migration assays to investigate the three signalling pathways in parallel within one tissue-model system.',
  'focus' => ["Extracellular-matrix protein expression research", "Parallel-pathway signalling studies", "Cell-migration assay investigation", "Mass-spec identity verification per batch"],
],

'klow' => [
  'title' => 'KLOW', 'cat' => 'tissue-repair-research', 'size' => '80mg',
  'price' => '129.00',
  'lab' => 'Freedom Diagnostics', 'lot' => '261807', 'purity' => '99.96%',
  'subtitle' => 'GHK-Cu / KPV / TB-500 / BPC-157 Research Blend',
  'excerpt' => 'Four-component research blend supplied lyophilized for in-vitro extracellular-matrix and signalling research.',
  'body' => 'KLOW combines GHK-Cu (copper tripeptide complex), KPV (α-MSH C-terminal tripeptide), TB-500 (thymosin β4 fragment) and BPC-157 (synthetic pentadecapeptide) in a single lyophilized preparation. Researchers use the combination in cell-culture and biochemical preparations to investigate matrix protein expression, melanocortin-pathway signalling and cytoskeletal dynamics within one model system.',
  'focus' => ["Matrix protein expression research", "Melanocortin-pathway signalling studies", "Cytoskeletal dynamics investigation", "Mass-spec identity verification per batch"],
],

'melanotan-1' => [
  'title' => 'Melanotan 1', 'cat' => 'dermal-research', 'size' => '10mg',
  'price' => '49.99',
  'lab' => 'Freedom Diagnostics', 'lot' => '261807', 'purity' => '99.31%',
  'subtitle' => 'Linear α-MSH Analog',
  'excerpt' => 'Linear α-melanocyte-stimulating hormone analog supplied lyophilized for in-vitro melanocortin-1 receptor research.',
  'body' => 'Melanotan 1 is a synthetic linear analog of α-melanocyte-stimulating hormone carrying substitutions that increase resistance to enzymatic degradation. Researchers use the compound in melanocyte culture and receptor-binding preparations to investigate MC1R activation and eumelanin synthesis pathways in model systems.',
  'focus' => ["MC1R receptor activation research", "Eumelanin synthesis pathway studies", "Melanocyte culture model investigation", "Mass-spec identity verification per batch"],
],

'melanotan-2' => [
  'title' => 'Melanotan 2', 'cat' => 'dermal-research', 'size' => '10mg',
  'price' => '39.99',
  'lab' => 'Freedom Diagnostics', 'lot' => '261807', 'purity' => '99.83%',
  'subtitle' => 'Cyclic α-MSH Analog',
  'excerpt' => 'Cyclic lactam analog of α-melanocyte-stimulating hormone supplied lyophilized for in-vitro melanocortin-receptor research.',
  'body' => 'Melanotan 2 is a cyclic lactam heptapeptide analog of α-melanocyte-stimulating hormone with broad activity across melanocortin receptor subtypes. Researchers use the compound in receptor-binding and melanocyte culture preparations to investigate MC1R, MC3R and MC4R subtype pharmacology and structure-activity relationships.',
  'focus' => ["Melanocortin receptor subtype pharmacology research", "Structure-activity relationship studies", "Cyclic lactam stability investigation", "Mass-spec identity verification per batch"],
],

'dsip' => [
  'title' => 'DSIP', 'cat' => 'cognitive-research', 'size' => '15mg',
  'price' => '39.99',
  'lab' => 'Freedom Diagnostics', 'lot' => '10B', 'purity' => '99.56%',
  'subtitle' => 'Nonapeptide — Delta-Wave Correlate',
  'excerpt' => 'Nonapeptide first isolated from mammalian cerebral venous blood, supplied lyophilized for in-vitro neuropeptide research.',
  'body' => 'DSIP is a nonapeptide first isolated from mammalian cerebral venous blood. Researchers use the compound in neuronal cell-culture and electrophysiology preparations to investigate delta-wave EEG correlates, neuromodulatory signalling and peptide transport across model barrier systems.',
  'focus' => ["Delta-wave EEG correlate research", "Neuromodulatory signalling studies", "Peptide transport across model barrier systems", "Mass-spec identity verification per batch"],
],

'glp-1-s' => [
  // Sizes/prices are peptideclub.com's own listing (client: store is
  // authoritative). The certificate is for a 10mg vial — same accepted
  // size-vs-cert situation as Epithalon (50mg cert on a 10mg product).
  'title' => 'GLP-1 S', 'cat' => 'metabolic-research', 'size' => '2mg / 5mg / 15mg',
  'variations' => ['2mg' => '22.99', '5mg' => '32.99', '15mg' => '65.00'],
  'lab' => 'Freedom Diagnostics', 'lot' => '10B', 'purity' => '99.93%',
  'subtitle' => 'GLP-1 Receptor Agonist — Single Regulator',
  'excerpt' => 'Long-acting GLP-1 receptor agonist research peptide supplied lyophilized for in-vitro incretin-pathway research.',
  'body' => 'GLP-1 S is a long-acting glucagon-like peptide-1 receptor agonist research peptide carrying a C18 fatty-diacid side chain that increases albumin association. Researchers use the compound in receptor-binding and beta-cell culture preparations to investigate class-B GPCR activation, cAMP signalling and incretin-pathway pharmacology.',
  'focus' => ["Class-B GPCR activation research", "cAMP signalling cascade studies", "Incretin-pathway pharmacology investigation", "Mass-spec identity verification per batch"],
],

];

$log = function (string $m) use ($dry) { WP_CLI::log(($dry ? '[dry-run] ' : '') . $m); };
$created = $updated = $skipped = 0;
$problems = [];

foreach ($products as $slug => $p) {
    $existing = get_posts([
        'name' => $slug, 'post_type' => 'product',
        'post_status' => ['publish', 'draft', 'pending', 'private'], 'numberposts' => 1,
    ]);
    $existing = $existing[0] ?? null;

    // Publishing is gated on price — an unpriced product must never go live.
    $has_price   = !empty($p['price']) || !empty($p['variations']);
    $can_publish = $publish && $has_price;
    if ($publish && !$has_price) {
        $log("skip-publish  {$slug}: no price — staying draft");
    }
    $status = $can_publish ? 'publish' : ($existing ? $existing->post_status : 'draft');
    $postarr = [
        'post_title'   => $p['title'],
        'post_name'    => $slug,
        'post_type'    => 'product',
        'post_status'  => $status,
        'post_content' => $p['body'] . $TAIL,
        'post_excerpt' => $p['excerpt'],
    ];

    if ($existing) {
        $postarr['ID'] = $existing->ID;
        $log("update  {$slug} (#{$existing->ID})");
        $updated++;
    } else {
        $log("create  {$slug} — {$p['title']} ({$p['size']}, {$p['lab']}, {$p['purity']}) [{$status}]");
        $created++;
    }
    if ($dry) continue;

    $id = wp_insert_post($postarr, true);
    if (is_wp_error($id)) { $problems[] = "{$slug}: " . $id->get_error_message(); continue; }

    wp_set_object_terms($id, empty($p['variations']) ? 'simple' : 'variable', 'product_type');
    $term = get_term_by('slug', $p['cat'], 'product_cat');
    if ($term) {
        wp_set_object_terms($id, [(int) $term->term_id], 'product_cat');
    } else {
        $problems[] = "{$slug}: product_cat '{$p['cat']}' not found";
    }

    $meta = [
        '_nav_technical_subtitle' => $p['subtitle'],
        '_nav_size_label'         => $p['size'],
        '_nav_purity'             => $p['purity'],
        '_nav_testing_lab'        => $p['lab'],
        '_nav_batch_number'       => $p['lot'],
        '_nav_coa_pdf'            => esc_url_raw($coa_map[$slug] ?? ''),
        '_nav_form'               => $FORM,
        '_nav_storage'            => $STORAGE,
        '_nav_research_focus'     => implode("\n", $p['focus']),
    ];
    if (!empty($p['mw'])) $meta['_nav_molecular_weight'] = $p['mw'];
    foreach ($meta as $k => $v) update_post_meta($id, $k, $v);

    update_post_meta($id, '_visibility', 'visible');

    // Variable products: custom "Amount" attribute + one variation per size,
    // same shape as the existing GLP pair. Idempotent — variations are found
    // by their attribute_amount value and updated in place.
    if (!empty($p['variations'])) {
        update_post_meta($id, '_product_attributes', ['amount' => [
            'name' => 'Amount', 'value' => implode(' | ', array_keys($p['variations'])),
            'position' => 0, 'is_visible' => 1, 'is_variation' => 1, 'is_taxonomy' => 0,
        ]]);
        $children = get_posts(['post_type' => 'product_variation', 'post_parent' => $id,
                               'post_status' => ['publish', 'private'], 'numberposts' => -1]);
        $by_amount = [];
        foreach ($children as $c) {
            $by_amount[(string) get_post_meta($c->ID, 'attribute_amount', true)] = $c->ID;
        }
        foreach ($p['variations'] as $amount => $price) {
            $vid = $by_amount[$amount] ?? wp_insert_post([
                'post_type' => 'product_variation', 'post_parent' => $id,
                'post_status' => 'publish', 'post_title' => "{$p['title']} - {$amount}",
            ]);
            update_post_meta($vid, 'attribute_amount', $amount);
            nav_coa_set_price($vid, $price);
        }
        if (class_exists('WC_Product_Variable')) WC_Product_Variable::sync($id);
    }

    nav_coa_set_price($id, empty($p['variations']) ? ($p['price'] ?? '') : null);
}

/**
 * Set price + stock via WC CRUD, then make sure wc_product_meta_lookup
 * agrees. save() refreshes the lookup row only when the postmeta write
 * actually changed the stored value — if an earlier code path already put
 * this price in postmeta while the lookup row lagged, save() no-ops and the
 * row stays stale (price sorting/filters read it). In that one case the two
 * derived columns are repaired directly; verification asserts the end state.
 * $price null = leave prices alone (variable parents derive from children).
 */
function nav_coa_set_price(int $id, ?string $price): void {
    if (!function_exists('wc_get_product') || !($prod = wc_get_product($id))) return;
    if ($price !== null && $price !== '') {
        $prod->set_regular_price($price);
        $prod->set_price($price);
    }
    $prod->set_stock_status('instock');
    $prod->save();
    if ($price === null || $price === '') return;

    global $wpdb;
    $table = $wpdb->prefix . 'wc_product_meta_lookup';
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT min_price, max_price FROM {$table} WHERE product_id = %d", $id));
    if (!$row || (float) $row->min_price !== (float) $price
              || (float) $row->max_price !== (float) $price) {
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (product_id, min_price, max_price) VALUES (%d, %f, %f)
             ON DUPLICATE KEY UPDATE min_price = VALUES(min_price), max_price = VALUES(max_price)",
            $id, $price, $price));
    }
}

if ($problems) {
    WP_CLI::error("Problems:\n  - " . implode("\n  - ", $problems));
}

if (!$dry) {
    $missing = [];
    foreach ($products as $slug => $p) {
        $found = get_posts([
            'name' => $slug, 'post_type' => 'product',
            'post_status' => ['publish', 'draft', 'pending', 'private'], 'numberposts' => 1,
        ]);
        if (!$found) { $missing[] = "{$slug}: not found after import"; continue; }
        $id = $found[0]->ID;
        $want_url = esc_url_raw($coa_map[$slug] ?? '');
        $got_url  = get_post_meta($id, '_nav_coa_pdf', true);
        if ($want_url === '' || $got_url !== $want_url) {
            $missing[] = "{$slug}: _nav_coa_pdf is '{$got_url}', expected '{$want_url}'";
        }
        if (get_post_meta($id, '_nav_purity', true) !== $p['purity']) {
            $missing[] = "{$slug}: purity mismatch";
        }
        if (!has_term($p['cat'], 'product_cat', $id)) {
            $missing[] = "{$slug}: category {$p['cat']} not assigned";
        }
        // Real compound names must not leak on the coded GLP product.
        if ($slug === 'glp-1-s' && stripos($found[0]->post_content . $found[0]->post_excerpt
            . $found[0]->post_title, 'semaglutide') !== false) {
            $missing[] = "glp-1-s: real compound name present in visible text";
        }
        // Price/publish invariants: a published import must be priced, and an
        // unpriced item must never be published. Prices are asserted in BOTH
        // stores — postmeta AND wc_product_meta_lookup. The shop's sorting and
        // filtering read the lookup table, and it once went stale while
        // postmeta (and this verification) looked correct.
        global $wpdb;
        $lookup = $wpdb->get_row($wpdb->prepare(
            "SELECT min_price, max_price FROM {$wpdb->prefix}wc_product_meta_lookup WHERE product_id = %d", $id));
        if (!empty($p['price'])
            && (!$lookup || (float) $lookup->min_price !== (float) $p['price'])) {
            $missing[] = "{$slug}: lookup min_price is '"
                . ($lookup->min_price ?? 'MISSING') . "', expected '{$p['price']}'";
        }
        if (!empty($p['variations']) && $lookup) {
            $want_min = min(array_map('floatval', $p['variations']));
            $want_max = max(array_map('floatval', $p['variations']));
            if ((float) $lookup->min_price !== $want_min || (float) $lookup->max_price !== $want_max) {
                $missing[] = "{$slug}: lookup range {$lookup->min_price}-{$lookup->max_price}, "
                    . "expected {$want_min}-{$want_max}";
            }
        }
        $stored_price = get_post_meta($id, '_price', true);
        if (!empty($p['price']) && $stored_price !== $p['price']) {
            $missing[] = "{$slug}: price is '{$stored_price}', expected '{$p['price']}'";
        }
        if ($found[0]->post_status === 'publish' && $stored_price === '' && empty($p['variations'])) {
            $missing[] = "{$slug}: PUBLISHED WITHOUT A PRICE";
        }
        if (!empty($p['variations'])) {
            $have = [];
            foreach (get_posts(['post_type' => 'product_variation', 'post_parent' => $id,
                                'post_status' => ['publish', 'private'], 'numberposts' => -1]) as $c) {
                $have[(string) get_post_meta($c->ID, 'attribute_amount', true)]
                    = (string) get_post_meta($c->ID, '_price', true);
            }
            foreach ($p['variations'] as $amount => $price) {
                if (($have[$amount] ?? null) !== $price) {
                    $missing[] = "{$slug} {$amount}: variation price is '"
                        . ($have[$amount] ?? 'MISSING') . "', expected '{$price}'";
                }
            }
        }
    }
    if ($missing) WP_CLI::error("Verification failed:\n  - " . implode("\n  - ", $missing));
    WP_CLI::success(sprintf('%d created, %d updated, %d products verified.',
        $created, $updated, count($products)));
} else {
    WP_CLI::success(sprintf('Dry run: %d would be created, %d updated.', $created, $updated));
}
