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
#
# Auth: wordpress.com Atomic uses PASSWORD auth. This script makes three
# connections (two uploads and the run), so it opens one multiplexed master
# connection first — the password is typed ONCE and the other two reuse it.
# Set WPCOM_SSH_PASSWORD (with sshpass installed) to skip the prompt entirely;
# otherwise the password is only ever typed interactively and never stored.
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

CTL_DIR="$(mktemp -d "${TMPDIR:-/tmp}/coa-ssh.XXXXXX")"
# ssh takes -p for the port, scp takes -P (scp's -p means "preserve times").
SSH_OPTS=(-p "$PORT" -o "ControlPath=$CTL_DIR/ctl")
SCP_OPTS=(-P "$PORT" -o "ControlPath=$CTL_DIR/ctl")

cleanup() {
  ssh "${SSH_OPTS[@]}" -O exit "$USER@$HOST" 2>/dev/null || true
  rm -rf "$CTL_DIR"
}
trap cleanup EXIT

# Only the master connection authenticates; the uploads and the run reuse it
# through the control socket, so they never prompt.
SSH_MASTER=(ssh)
if [[ -n "${WPCOM_SSH_PASSWORD:-}" ]]; then
  if command -v sshpass >/dev/null; then
    export SSHPASS="$WPCOM_SSH_PASSWORD"
    SSH_MASTER=(sshpass -e ssh)
  else
    echo "NOTE: WPCOM_SSH_PASSWORD is set but sshpass is not installed;"
    echo "      you will get one interactive prompt instead. (brew install sshpass)"
  fi
fi

echo "==> Opening connection to $HOST — password prompt, once"
"${SSH_MASTER[@]}" "${SSH_OPTS[@]}" -o ControlMaster=yes -o ControlPersist=300 \
  -N -f "$USER@$HOST"
ssh "${SSH_OPTS[@]}" -O check "$USER@$HOST" >/dev/null 2>&1 \
  || { echo "ERROR: could not open the connection — check user, host and password."; exit 1; }

echo "==> Uploading PDF + scripts"
scp "${SCP_OPTS[@]}" "$PDF_SRC" "$USER@$HOST:/tmp/$PDF_NAME"
scp "${SCP_OPTS[@]}" "$REPO_ROOT/scripts/coa-sync.php" "$USER@$HOST:/tmp/coa-sync.php"
scp "${SCP_OPTS[@]}" "$REPO_ROOT/scripts/coa-import-products.php" "$USER@$HOST:/tmp/coa-import-products.php"

echo "==> Importing PDF + running sync ${MODE:+($MODE)}${PUBLISH:+ (publishing imports)}"
ssh "${SSH_OPTS[@]}" "$USER@$HOST" bash -s -- "$MODE" "$PUBLISH" <<'REMOTE'
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
