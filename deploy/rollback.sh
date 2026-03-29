#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=deploy/common.sh
source "$SCRIPT_DIR/common.sh"

TARGET_HOST="${TARGET_HOST:-}"
FAILED_RELEASE="${1:-}"

if [[ -z "$TARGET_HOST" ]]; then
  echo "Set TARGET_HOST=avalanche or TARGET_HOST=be-web-01" >&2
  exit 1
fi

if [[ -z "$FAILED_RELEASE" ]]; then
  echo "Usage: TARGET_HOST=<host> $0 <failed-release-name>" >&2
  exit 1
fi

PREVIOUS_RELEASE="$(
  remote_sudo "$TARGET_HOST" "
    set -euo pipefail
    cd '$APP_ROOT/releases'
    ls -1dt */ 2>/dev/null | sed 's:/$::' | grep -vx '$FAILED_RELEASE' | head -n 1
  "
)"

if [[ -z "$PREVIOUS_RELEASE" ]]; then
  echo "No previous release found." >&2
  exit 1
fi

remote_sudo "$TARGET_HOST" "
  set -euo pipefail
  ln -sfn '$APP_ROOT/releases/$PREVIOUS_RELEASE' '$APP_ROOT/current'
  systemctl daemon-reload
  systemctl restart '$API_UNIT'
  systemctl restart '$WORKER_UNIT'
"

case "$TARGET_HOST" in
  be-web-01)
    "$SCRIPT_DIR/smoke-test-prod.sh" "$TARGET_HOST"
    ;;
  *)
    "$SCRIPT_DIR/smoke-test-staging.sh" "$TARGET_HOST"
    ;;
esac

echo "Rollback complete."
echo "Host: $TARGET_HOST"
echo "Active release: $APP_ROOT/releases/$PREVIOUS_RELEASE"
