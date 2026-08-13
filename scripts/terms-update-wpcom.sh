#!/usr/bin/env bash
# Publish docs/legal/terms-of-service.html to the live Terms page on the
# wordpress.com production site.
#
# Usage:
#   WPCOM_SSH_USER=xxx WPCOM_SSH_HOST=ssh.wp.com ./scripts/terms-update-wpcom.sh          # dry run
#   WPCOM_SSH_USER=xxx WPCOM_SSH_HOST=ssh.wp.com ./scripts/terms-update-wpcom.sh apply    # write
#
# Credentials: wordpress.com dashboard → Hosting → Server Settings →
# "SFTP/SSH credentials" (same account the deploy workflow uses). The host is
# ssh.wp.com — sftp.wp.com is the SFTP-only endpoint and this script needs a
# shell for WP-CLI. The user is the full site slug, e.g. example.wordpress.com.
#
# DRY RUN IS THE DEFAULT — this replaces the body of a published legal page,
# and that page is what the checkout "I accept the Terms and Conditions"
# checkbox links to. Before any write it takes a full `wp db export` on the
# server and prints the path; the update itself also dumps the previous page
# body to JSON. Both stay on the server for you to pull down or delete.
#
# The target page is resolved from woocommerce_terms_page_id, not hard-coded.
# Idempotent — re-running with unchanged content is a no-op.
#
# Auth: wordpress.com Atomic uses PASSWORD auth. This script makes three
# connections (two uploads and the run), so it opens one multiplexed master
# connection first — the password is typed ONCE and the others reuse it.
# Set WPCOM_SSH_PASSWORD (with sshpass installed) to skip the prompt entirely;
# otherwise the password is only ever typed interactively and never stored.
set -euo pipefail

USER="${WPCOM_SSH_USER:?set WPCOM_SSH_USER}"
HOST="${WPCOM_SSH_HOST:?set WPCOM_SSH_HOST}"
PORT="${WPCOM_SSH_PORT:-22}"

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HTML_SRC="$REPO_ROOT/docs/legal/terms-of-service.html"
PHP_SRC="$REPO_ROOT/scripts/terms-update.php"
for f in "$HTML_SRC" "$PHP_SRC"; do
  [[ -f "$f" ]] || { echo "ERROR: $f not found"; exit 1; }
done

MODE="dry-run"
for a in "$@"; do
  case "$a" in
    apply)   MODE="" ;;
    dry-run) MODE="dry-run" ;;
    *) echo "ERROR: unknown argument '$a' (expected: apply | dry-run)"; exit 1 ;;
  esac
done

CTL_DIR="$(mktemp -d "${TMPDIR:-/tmp}/terms-ssh.XXXXXX")"
# ssh takes -p for the port, scp takes -P (scp's -p means "preserve times").
SSH_OPTS=(-p "$PORT" -o "ControlPath=$CTL_DIR/ctl")
SCP_OPTS=(-P "$PORT" -o "ControlPath=$CTL_DIR/ctl")

cleanup() {
  ssh "${SSH_OPTS[@]}" -O exit "$USER@$HOST" 2>/dev/null || true
  rm -rf "$CTL_DIR"
}
trap cleanup EXIT

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

echo "==> Uploading Terms body + updater"
scp "${SCP_OPTS[@]}" "$HTML_SRC" "$USER@$HOST:/tmp/terms-of-service.html"
scp "${SCP_OPTS[@]}" "$PHP_SRC"  "$USER@$HOST:/tmp/terms-update.php"

echo "==> Running ${MODE:-apply}"
ssh "${SSH_OPTS[@]}" "$USER@$HOST" bash -s -- "$MODE" <<'REMOTE'
set -euo pipefail
MODE="${1:-}"
cd ~/htdocs 2>/dev/null || cd /srv/htdocs

if [[ "$MODE" != "dry-run" ]]; then
  DUMP="/tmp/pre-terms-update-$(date -u +%Y%m%d-%H%M%S).sql"
  echo "--- backing up the database to $DUMP ---"
  wp db export "$DUMP" --add-drop-table
  ls -lh "$DUMP"
fi

wp eval-file /tmp/terms-update.php /tmp/terms-of-service.html $MODE

if [[ "$MODE" != "dry-run" ]]; then
  wp cache flush || true
fi
rm -f /tmp/terms-update.php /tmp/terms-of-service.html
REMOTE

if [[ "$MODE" != "dry-run" ]]; then
  echo "==> Smoke-checking production"
  for path in "/terms/" "/refund-policy/" "/checkout/"; do
    code=$(curl -s -o /dev/null -w "%{http_code}" "https://navigatepeptides.com$path")
    echo "    $code  $path"
  done
  echo
  echo "==> Verifying the served Terms page"
  body=$(curl -sL "https://navigatepeptides.com/terms/")
  for probe in "Product and sale limitations" "research use only (RUO)" \
               "ACH and e-Check payment authorization" "Contact for RMA requests"; do
    printf '    %-42s %s\n' "$probe" \
      "$(grep -qF "$probe" <<<"$body" && echo present || echo MISSING)"
  done
  for bad in "Elytherion" "665-2694" "MerchantWebsite"; do
    printf '    %-42s %s\n' "must be absent: $bad" \
      "$(grep -qiF "$bad" <<<"$body" && echo FOUND || echo clean)"
  done
  echo
  echo "MISSING/FOUND above usually means the EDGE cache is still serving the"
  echo "old HTML — wp.com dashboard → Settings → Clear cache, then re-check."
  echo "Then confirm the checkout checkbox link resolves to /terms/."
fi
