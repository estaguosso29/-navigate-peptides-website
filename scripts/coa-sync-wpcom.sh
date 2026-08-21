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
#   1. Splits the compiled PDF into one verified certificate per product,
#      then uploads those plus both PHP scripts
#   2. Imports each certificate into the Media Library (reused on re-runs) and
#      builds slug -> URL map; retires the old compiled catalogue PDF
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
SPLIT_DIR="$(mktemp -d "${TMPDIR:-/tmp}/coa-certs.XXXXXX")"

[[ -f "$PDF_SRC" ]] || { echo "ERROR: $PDF_SRC not found"; exit 1; }

# Split FIRST and locally: the splitter re-opens every file it writes and
# proves it holds the right certificate (sample name + lot + purity, or the
# page-image md5 for the scanned ones). Nothing is uploaded unless it passes.
echo "==> Splitting certificates (verified)"
python3 "$REPO_ROOT/scripts/coa-split-certificates.py" "$PDF_SRC" "$SPLIT_DIR" \
  || { echo "ERROR: certificate split failed verification — nothing uploaded."; exit 1; }

PUBLISH=""
[[ "$MODE" == "publish-imports" ]] && { PUBLISH="publish"; MODE=""; }

CTL_DIR="$(mktemp -d "${TMPDIR:-/tmp}/coa-ssh.XXXXXX")"
# ssh takes -p for the port, scp takes -P (scp's -p means "preserve times").
SSH_OPTS=(-p "$PORT" -o "ControlPath=$CTL_DIR/ctl")
SCP_OPTS=(-P "$PORT" -o "ControlPath=$CTL_DIR/ctl")

cleanup() {
  ssh "${SSH_OPTS[@]}" -O exit "$USER@$HOST" 2>/dev/null || true
  rm -rf "$CTL_DIR" "${SPLIT_DIR:-}"
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

echo "==> Uploading certificates + scripts"
ssh "${SSH_OPTS[@]}" "$USER@$HOST" 'rm -rf /tmp/coa-certs && mkdir -p /tmp/coa-certs'
scp "${SCP_OPTS[@]}" -q "$SPLIT_DIR"/*.pdf "$SPLIT_DIR/manifest.json" "$USER@$HOST:/tmp/coa-certs/"
scp "${SCP_OPTS[@]}" -q "$REPO_ROOT/scripts/coa-media-import.php" "$USER@$HOST:/tmp/coa-media-import.php"
scp "${SCP_OPTS[@]}" -q "$REPO_ROOT/scripts/coa-sync.php" "$USER@$HOST:/tmp/coa-sync.php"
scp "${SCP_OPTS[@]}" -q "$REPO_ROOT/scripts/coa-import-products.php" "$USER@$HOST:/tmp/coa-import-products.php"

echo "==> Running sync ${MODE:+($MODE)}${PUBLISH:+ (publishing imports)}"
ssh "${SSH_OPTS[@]}" "$USER@$HOST" bash -s -- "$MODE" "$PUBLISH" <<'REMOTE'
set -euo pipefail
MODE="${1:-}"
PUBLISH="${2:-}"
cd ~/htdocs 2>/dev/null || cd /srv/htdocs

echo "--- importing certificates into the Media Library ---"
wp eval-file /tmp/coa-media-import.php /tmp/coa-certs /tmp/coa-map.json $MODE

echo "--- wiring certificates onto existing products ---"
wp eval-file /tmp/coa-sync.php /tmp/coa-map.json $MODE

echo "--- importing new certified catalogue items ---"
wp eval-file /tmp/coa-import-products.php /tmp/coa-map.json $MODE $PUBLISH

# Retire LAST: the guard refuses to delete an attachment any product still
# points at, so this has to run after the wiring above has moved every product
# onto its own certificate — otherwise it always self-vetoes and the compiled
# catalogue (and its index page) stays reachable.
echo "--- retiring the compiled catalogue PDF ---"
wp eval-file /tmp/coa-media-import.php /tmp/coa-certs /tmp/coa-map.json $MODE retire-compiled

if [[ "$MODE" != "dry-run" ]]; then
  wp cache flush || true
fi
rm -rf /tmp/coa-sync.php /tmp/coa-import-products.php /tmp/coa-media-import.php /tmp/coa-certs /tmp/coa-map.json
REMOTE

if [[ "$MODE" != "dry-run" ]]; then
  echo "==> Smoke-checking production"
  for path in "/" "/quality/coa/?coa_search=261807" "/product/bpc-157/"; do
    code=$(curl -s -o /dev/null -w "%{http_code}" "https://navigatepeptides.com$path")
    echo "    $code  $path"
  done
  echo "Done. Manually verify: each product page's COA link opens ONLY that"
  echo "product's certificate, and GLP pages show coded names."
  echo "Also bust the edge cache: wp.com dashboard → Settings → Clear cache."
fi
