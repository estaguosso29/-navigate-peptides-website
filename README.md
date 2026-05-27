# Navigate Peptides — WordPress Theme

Custom WordPress/WooCommerce storefront for Navigate Peptides — research-grade peptide compounds, hosted on wp.com Atomic Business.

Live site: https://navigatepeptides.com

> **New developer? Start at [`docs/HANDOFF`](docs/HANDOFF) — that's the single entry point.** It points at the other canonical docs in the right reading order.

## Stack

- **WordPress** 6.5+ (`Requires at least: 6.5`), tested through 6.9
- **PHP** 8.1+
- **WooCommerce** required (declared via `Requires Plugins: woocommerce` in `style.css`)
- **Host:** WordPress.com Atomic Business
- **Deploy:** GitHub Actions → SSH to wp.com on push to `main` (see `.github/workflows/deploy-wpcom-sftp.yml`)

## Repo layout

```
wp-content/themes/navigate-peptides/
├── style.css                         — Theme header + version
├── functions.php                     — Bootstrap, theme support, enqueues, headers
├── header.php / footer.php / front-page.php / single-product.php / archive-product.php / etc.
├── inc/
│   ├── compliance.php                — Canonical RUO disclaimer + nav_redact / nav_admin_warn helpers
│   ├── seo.php                       — Meta, OG, Twitter, JSON-LD (Org, WebSite, Product, Article, Collection, Breadcrumb)
│   ├── woocommerce.php               — Cart, PDP tabs, custom meta save, prohibited terms
│   ├── analytics.php                 — GA4 + ecommerce events (consent-gated)
│   ├── subscribe.php                 — Newsletter REST endpoint + nav_subscriber CPT + GDPR retention cron
│   ├── contact.php                   — Contact form handler + auto-ack
│   ├── consent.php                   — Cookie consent banner + nav_privacy_url() / nav_terms_url()
│   ├── age-gate.php                  — RUO + 21+ entry gate
│   ├── custom-types.php              — nav_research CPT + research_category taxonomy + term seeding
│   ├── business.php                  — DBA / address / email constants (NO legal entity name, NO phone)
│   ├── arcana-credit.php             — Footer attribution
│   ├── minicart.php                  — Cart drawer
│   └── seo.php                       — (large; 1,500+ LOC of SEO surface)
├── page-templates/                   — Custom page templates (about, quality, COA, contact, etc.)
├── template-parts/                   — Reusable components (product-card, category-grid)
├── woocommerce/                      — Woo template overrides (cart, checkout, single-product, archive)
├── assets/
│   ├── css/main.css                  — Site styles (~6K LOC)
│   ├── css/woocommerce.css           — Woo-specific overrides (~2K LOC, source of truth for product-card)
│   ├── js/main.js                    — Site JS (~600 LOC, single file)
│   ├── models/vial-*.glb             — 18 product 3D models
│   └── images/                       — WebP posters, wordmark, OG share
├── languages/                        — Translation files (currently empty; .gitkeep doc'd)
└── setup.sh                          — Idempotent WP-CLI provisioning script
```

## Local setup

This repo only contains the custom theme + a `setup.sh` that provisions pages, terms, and seed products via WP-CLI. You need a WordPress install elsewhere; this directory mirrors `wp-content/themes/navigate-peptides/`.

For a fresh local site:
```bash
# 1. Install WP however you do that locally (LocalWP, wp-env, ddev, …)
# 2. Symlink or rsync this theme into wp-content/themes/navigate-peptides
# 3. Activate the theme + WooCommerce
# 4. From the WP root:
bash wp-content/themes/navigate-peptides/setup.sh
```

`setup.sh` is **idempotent** — running it twice does NOT create duplicate pages (re-runs of the upsert_page helper detect existing slugs first). Safe to re-run after pulling new copy or template changes.

## Deploy

Pushing to `main` triggers `.github/workflows/deploy-wpcom-sftp.yml`:
- Required secrets: `WPCOM_SSH_HOST`, `WPCOM_SSH_USER`, `WPCOM_SSH_KEY`, `WPCOM_THEME_PATH`
- Workflow is gated on the `production` GitHub environment — add required reviewers in repo Settings → Environments if you want a manual approval step
- Concurrency-locked (`deploy-wpcom-production`) so simultaneous merges queue instead of racing
- Dry-run available via `workflow_dispatch` with `dry_run: true`

After a deploy, smoke-check these (live site, no login needed):
- `/` → hero vial renders, no JS errors in console
- `/compounds/` → product grid renders, RUO banner visible above the fold
- `/quality/coa/` → COA lookup form renders
- `/wp-sitemap.xml` → returns 200
- `/robots.txt` → contains `Sitemap: …/wp-sitemap.xml`

## Maintenance notes

### Critical invariants (do not break)

- **No legal entity name** (`Elytherion LLC`) anywhere user-facing. `inc/business.php` deliberately omits `NAV_BIZ_LEGAL_NAME`. PHP comments referencing the entity have been redacted; if you re-add it, ensure it's redacted again before commit.
- **No phone number** user-facing. `NAV_BIZ_PHONE_*` constants are absent by design.
- **Canonical RUO disclaimer** is the only source of truth. Fetch via `nav_get_disclaimer('sitewide')` from `inc/compliance.php`. No paraphrases.
- **No "human consumption / dosing / injection / ingestion"** outside the canonical disclaimer block. `nav_seo_scrub()` rewrites these and other processor-flagged terms; expand the map in `inc/seo.php` rather than coding around it.

### Operator tools

- **wp-admin notices** — `inc/compliance.php::nav_admin_warn()` pushes a transient-backed warning queue that surfaces backend failures (JSON-LD encode drops, COA meta save failures, missing legal pages, etc.) to admins via `admin_notices`. Watch the WP dashboard after deploys.
- **PII redaction** — `inc/compliance.php::nav_redact($value)` returns a 12-char SHA-256 token salted by `AUTH_SALT`. Used for IP/email correlation in `error_log` without spilling raw PII.
- **Subscriber retention** — `inc/subscribe.php` schedules `nav_subscriber_pii_scrub` daily; deletes `_nav_ip_hash` / `_nav_user_agent_hash` meta on `nav_subscriber` rows older than 365 days (filter: `nav_subscriber_pii_retention_days`).
- **Search engine verification** — set `NAV_GSC_VERIFICATION` / `NAV_BING_VERIFICATION` / `NAV_YANDEX_VERIFICATION` constants in `inc/business.php` (currently empty placeholders) after claiming each console property.

### Common admin tasks

- **Edit a product's compliance copy** → wp-admin → Products → Edit → "SEO — Navigate Peptides" meta box has Meta Description + Social Share Image overrides per product.
- **Update batch number / COA URL** → wp-admin → Products → Edit → "Product Data" → check the Navigate Peptides meta fields (\_nav_batch_number, \_nav_testing_lab, \_nav_coa_pdf). Admin notices fire if the DB write fails.
- **Add a research article** → wp-admin → Research Articles (CPT `nav_research`). Article schema, OG `article:*`, breadcrumb, and meta description all auto-generate.
- **Pause newsletter signups** → unhook `nav_subscribe_notify_admin` filter, or set the REST endpoint return early. Currently rate-limited at 5/hour/IP.

### When something goes wrong

- **Site looks broken after deploy** → check wp-admin for `nav_admin_warn` notices first.
- **JSON-LD validation fails on Google rich-results test** → `nav_seo_json_ld()` logs encode failures and surfaces them to admins. Check the @type cited.
- **WC checkout shows literal `%1$s`** → `nav_privacy_url()` returned empty. Verify the privacy-policy page exists with slug `privacy-policy` or `privacy`.
- **Model-viewer canvas is transparent on a PDP** → check browser console; CSP-blocked fetches show up there. Likely a regression in `inc/business.php`'s CSP allowlist; verify `ajax.googleapis.com` is still in `connect-src`.

## Audit history

Comprehensive multi-dimensional audit ran 2026-05; punch list lives in conversation history. Critical/High items closed:

- **PRs #92–#98** — compliance polish, critical perf+a11y, PII security, silent-failure surfacing, SEO completeness + deepfix, WP runtime polish.

Open items tracked in the broader audit punch list:
- `.nav-product-single` CSS dedup (C3 deferred — overlap risk, needs visual regression check)
- CSP `'unsafe-inline'` removal (M-Sec-1 — requires nonce-or-hash on age-gate / consent / analytics / minicart inline scripts)
- JS scroll listener consolidation (H-Perf-2)
- FAQPage / HowTo schema expansion (SEO opportunity)

## Credits

Designed + built by **Arcana Operations Developers** — custom WordPress sites, WooCommerce stores, and bespoke web builds. https://arcanaoperations.com

## License

Proprietary. Not for redistribution.
