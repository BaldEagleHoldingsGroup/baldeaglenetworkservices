#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=deploy/common.sh
source "$SCRIPT_DIR/common.sh"

TARGET_HOST="${TARGET_HOST:-be-web-01}"
RELEASE_NAME="$(make_release_name)"
RELEASE_DIR="$APP_ROOT/releases/$RELEASE_NAME"

preflight_local
preflight_remote "$TARGET_HOST"

BACKUP_ROOT="$(choose_remote_backup_root "$TARGET_HOST")"
BACKUP_DIR="$(backup_remote_state "$TARGET_HOST" "$BACKUP_ROOT" "$RELEASE_NAME")"

stream_repo_dirs_to_remote_release "$TARGET_HOST" "$RELEASE_DIR"
install_remote_systemd_units "$TARGET_HOST"
install_remote_venv "$TARGET_HOST" "$RELEASE_DIR"
validate_remote_release "$TARGET_HOST" "$RELEASE_DIR"
activate_remote_release "$TARGET_HOST" "$RELEASE_DIR"

if ! "$SCRIPT_DIR/smoke-test-prod.sh" "$TARGET_HOST"; then
  TARGET_HOST="$TARGET_HOST" "$SCRIPT_DIR/rollback.sh" "$RELEASE_NAME"
  exit 1
fi

prune_old_releases "$TARGET_HOST"

echo "Production deploy complete."
echo "Host: $TARGET_HOST"
echo "Release: $RELEASE_DIR"
echo "Backup: $BACKUP_DIR"
echo "Rollback: TARGET_HOST=$TARGET_HOST $SCRIPT_DIR/rollback.sh $RELEASE_NAME"
