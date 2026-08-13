#!/usr/bin/env bash
# Scrub the registered entity name and the company phone number out of the
# legal pages on the wordpress.com production site.
#
# Usage:
#   WPCOM_SSH_USER=xxx WPCOM_SSH_HOST=ssh.wp.com ./scripts/legal-entity-scrub-wpcom.sh            # dry run
#   WPCOM_SSH_USER=xxx WPCOM_SSH_HOST=ssh.wp.com ./scripts/legal-entity-scrub-wpcom.sh apply      # write
#   ... ./scripts/legal-entity-scrub-wpcom.sh apply revisions                                     # write, incl. revisions
#
# Credentials: wordpress.com dashboard → Hosting → Server Settings →
# "SFTP/SSH credentials" (same account the deploy workflow uses). The host is
# ssh.wp.com — sftp.wp.com is the SFTP-only endpoint and this script needs a
# shell for WP-CLI. The user is the full site slug, e.g. example.wordpress.com.
#
# DRY RUN IS THE DEFAULT — this edits published page content, so writing is
# opt-in. Before any write it takes a full `wp db export` on the server and
# prints the path; the scrub itself also dumps the original page bodies to a
# JSON file. Both stay on the server for you to pull down or delete.
#
# Enforces docs/COMPLIANCE invariants 1 and 2. Idempotent — safe to re-run.
#
# Auth: wordpress.com Atomic uses PASSWORD auth. This script makes two
# connections (the upload and the run), so it opens one multiplexed master
# connection first — the password is typed ONCE and the other reuses it.
# Set WPCOM_SSH_PASSWORD (with sshpass installed) to skip the prompt entirely;
# otherwise the password is only ever typed interactively and never stored.
set -euo pipefail

USER="${WPCOM_SSH_USER:?set WPCOM_SSH_USER}"
HOST="${WPCOM_SSH_HOST:?set WPCOM_SSH_HOST}"
PORT="${WPCOM_SSH_PORT:-22}"

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_SRC="$REPO_ROOT/scripts/legal-entity-scrub.php"
[[ -f "$PHP_SRC" ]] || { echo "ERROR: $PHP_SRC not found"; exit 1; }

# Default to dry-run; "apply" is the only thing that turns on writes.
MODE="dry-run"
REVISIONS=""
for a in "$@"; do
  case "$a" in
    apply)     MODE="" ;;
    dry-run)   MODE="dry-run" ;;
    revisions) REVISIONS="revisions" ;;
    *) echo "ERROR: unknown argument '$a' (expected: apply | dry-run | revisions)"; exit 1 ;;
  esac
done

CTL_DIR="$(mktemp -d "${TMPDIR:-/tmp}/legal-scrub-ssh.XXXXXX")"
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

echo "==> Uploading scrub script"
scp "${SCP_OPTS[@]}" "$PHP_SRC" "$USER@$HOST:/tmp/legal-entity-scrub.php"

echo "==> Running ${MODE:-apply}${REVISIONS:+ (incl. revisions)}"
ssh "${SSH_OPTS[@]}" "$USER@$HOST" bash -s -- "$MODE" "$REVISIONS" <<'REMOTE'
set -euo pipefail
MODE="${1:-}"
REVISIONS="${2:-}"
cd ~/htdocs 2>/dev/null || cd /srv/htdocs

if [[ "$MODE" != "dry-run" ]]; then
  DUMP="/tmp/pre-legal-scrub-$(date -u +%Y%m%d-%H%M%S).sql"
  echo "--- backing up the database to $DUMP ---"
  wp db export "$DUMP" --add-drop-table
  ls -lh "$DUMP"
fi

wp eval-file /tmp/legal-entity-scrub.php $MODE $REVISIONS

if [[ "$MODE" != "dry-run" ]]; then
  wp cache flush || true
fi
rm -f /tmp/legal-entity-scrub.php
REMOTE

if [[ "$MODE" != "dry-run" ]]; then
  echo "==> Smoke-checking production"
  for path in "/privacy-policy/" "/terms/" "/shipping-policy/"; do
    code=$(curl -s -o /dev/null -w "%{http_code}" "https://navigatepeptides.com$path")
    echo "    $code  $path"
  done
  echo
  echo "==> Verifying the scrubbed text is actually served"
  for path in "/privacy-policy/" "/terms/" "/shipping-policy/"; do
    body=$(curl -s "https://navigatepeptides.com$path")
    ent=$(grep -ic 'elytherion' <<<"$body" || true)
    tel=$(grep -c '665-2694' <<<"$body" || true)
    printf '    %-20s entity:%s phone:%s\n' "$path" "$ent" "$tel"
  done
  echo
  echo "Non-zero counts above usually mean the EDGE cache is still serving the"
  echo "old HTML — wp.com dashboard → Settings → Clear cache, then re-check."
  echo "Also check wp-admin for nav_admin_warn notices."
fi
