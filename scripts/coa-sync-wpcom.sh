#!/usr/bin/env bash
# Run the COA catalog sync against the wordpress.com production site.
#
# Usage:
#   WPCOM_SSH_USER=xxx WPCOM_SSH_HOST=sftp.wp.com ./scripts/coa-sync-wpcom.sh [dry-run]
#
# Credentials: wordpress.com dashboard → Hosting → Server Settings →
# "SFTP/SSH credentials" (same account the deploy workflow uses).
#
# What it does, over SSH:
#   1. Uploads the compiled COA PDF (neutral filename) and both PHP scripts
#   2. Imports the PDF into the Media Library (skipped if already imported)
#   3. coa-sync.php      — wires certificates onto existing products, hides the
#                          uncertified ones, applies coded GLP names
#   4. coa-import-products.php — creates the 19 certified catalogue items that
#                          are not yet on the site, as DRAFTS (no prices yet)
#   5. Flushes the object cache
#
# Idempotent — safe to re-run. Pass "dry-run" to preview without writing.
# Pass "publish-imports" to publish the imported drafts (only once prices are in).
set -euo pipefail

USER="${WPCOM_SSH_USER:?set WPCOM_SSH_USER}"
HOST="${WPCOM_SSH_HOST:?set WPCOM_SSH_HOST}"
PORT="${WPCOM_SSH_PORT:-22}"
MODE="${1:-}"

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PDF_SRC="$REPO_ROOT/docs/Azoth_Catalog_COAs_Compiled.pdf"
PDF_NAME="certificates-of-analysis.pdf"   # neutral name — no supplier in the public URL

[[ -f "$PDF_SRC" ]] || { echo "ERROR: $PDF_SRC not found"; exit 1; }

PUBLISH=""
[[ "$MODE" == "publish-imports" ]] && { PUBLISH="publish"; MODE=""; }

echo "==> Uploading PDF + scripts to $HOST"
scp -P "$PORT" "$PDF_SRC" "$USER@$HOST:/tmp/$PDF_NAME"
scp -P "$PORT" "$REPO_ROOT/scripts/coa-sync.php" "$USER@$HOST:/tmp/coa-sync.php"
scp -P "$PORT" "$REPO_ROOT/scripts/coa-import-products.php" "$USER@$HOST:/tmp/coa-import-products.php"

echo "==> Importing PDF + running sync ${MODE:+($MODE)}${PUBLISH:+ (publishing imports)}"
ssh -p "$PORT" "$USER@$HOST" bash -s -- "$MODE" "$PUBLISH" <<'REMOTE'
set -euo pipefail
MODE="${1:-}"
PUBLISH="${2:-}"
cd ~/htdocs 2>/dev/null || cd /srv/htdocs

# Reuse the existing attachment on re-runs instead of importing a duplicate.
EXISTING_ID=$(wp post list --post_type=attachment --field=ID \
  --meta_key=_wp_attached_file --format=ids 2>/dev/null | tr ' ' '\n' | while read -r id; do
    [[ -n "$id" ]] || continue
    f=$(wp post meta get "$id" _wp_attached_file 2>/dev/null || true)
    [[ "$f" == *certificates-of-analysis*.pdf ]] && { echo "$id"; break; }
  done)

if [[ -n "${EXISTING_ID:-}" ]]; then
  echo "PDF already in Media Library (attachment #$EXISTING_ID) — reusing."
  ATT_ID="$EXISTING_ID"
else
  ATT_ID=$(wp media import "/tmp/certificates-of-analysis.pdf" \
    --title="Certificates of Analysis" --porcelain)
  echo "Imported PDF as attachment #$ATT_ID"
fi

COA_URL=$(wp eval "echo wp_get_attachment_url($ATT_ID);")
echo "COA URL: $COA_URL"

echo "--- wiring certificates onto existing products ---"
wp eval-file /tmp/coa-sync.php "$COA_URL" $MODE

echo "--- importing new certified catalogue items ---"
wp eval-file /tmp/coa-import-products.php "$COA_URL" $MODE $PUBLISH

if [[ "$MODE" != "dry-run" ]]; then
  wp cache flush || true
fi
rm -f /tmp/coa-sync.php /tmp/coa-import-products.php /tmp/certificates-of-analysis.pdf
REMOTE

if [[ "$MODE" != "dry-run" ]]; then
  echo "==> Smoke-checking production"
  for path in "/" "/quality/coa/?coa_search=261807" "/product/bpc-157/"; do
    code=$(curl -s -o /dev/null -w "%{http_code}" "https://navigatepeptides.com$path")
    echo "    $code  $path"
  done
  echo "Done. Manually verify: COA search shows 7 results for 261807,"
  echo "product pages show 'Download COA', GLP pages show coded names."
  echo "Also bust the edge cache: wp.com dashboard → Settings → Clear cache."
fi
