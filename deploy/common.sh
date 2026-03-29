#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP_ROOT="/opt/bens-intake"
ENV_FILE="/etc/bens-intake.env"
PRIMARY_BACKUP_ROOT="/opt/backups/bens-intake"
FALLBACK_BACKUP_ROOT="/home/steveadmin/backups/bens-intake"
API_UNIT="bens-intake-api.service"
WORKER_UNIT="bens-crm-worker.service"
VENV_DIR="$APP_ROOT/shared/venv"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
SERVICE_USER="${SERVICE_USER:-steveadmin}"
SERVICE_GROUP="${SERVICE_GROUP:-steveadmin}"
RUNTIME_FILES=(
  app/config.py
  app/db.py
  app/suitecrm.py
  app/alerts.py
  app/intake_api.py
  app/crm_worker.py
  app/requirements.txt
)
SYSTEMD_FILES=(
  systemd/bens-intake-api.service
  systemd/bens-crm-worker.service
)

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "Missing required command: $1" >&2
    exit 1
  }
}

remote_sudo() {
  local host="$1"
  shift
  local cmd="$*"
  ssh "$host" "sudo -n bash -lc $(printf '%q' "$cmd")"
}

make_release_name() {
  printf '%s\n' "$(date +%Y%m%d-%H%M%S)"
}

choose_remote_backup_root() {
  local host="$1"
  remote_sudo "$host" "
    set -euo pipefail
    if install -d -m 0750 '$PRIMARY_BACKUP_ROOT' 2>/dev/null; then
      echo '$PRIMARY_BACKUP_ROOT'
    else
      install -d -m 0750 '$FALLBACK_BACKUP_ROOT'
      echo '$FALLBACK_BACKUP_ROOT'
    fi
  "
}

preflight_local() {
  require_cmd ssh
  require_cmd tar
  require_cmd python3

  local file
  for file in "${RUNTIME_FILES[@]}" "${SYSTEMD_FILES[@]}"; do
    if [[ ! -f "$REPO_ROOT/$file" ]]; then
      echo "Missing required file: $REPO_ROOT/$file" >&2
      exit 1
    fi
  done
}

preflight_remote() {
  local host="$1"
  remote_sudo "$host" "
    set -euo pipefail
    command -v systemctl >/dev/null 2>&1
    command -v python3 >/dev/null 2>&1
    command -v curl >/dev/null 2>&1
    test -f '$ENV_FILE'
    install -d -m 0755 '$APP_ROOT'
    install -d -m 0755 '$APP_ROOT/releases'
    install -d -m 0750 '$APP_ROOT/shared'
    install -d -m 0750 '$APP_ROOT/shared/logs'
    install -d -m 0750 '$APP_ROOT/shared/tmp'
    chown root:root '$APP_ROOT'
    chown root:root '$APP_ROOT/releases'
    chown '$SERVICE_USER:$SERVICE_GROUP' '$APP_ROOT/shared'
    chown '$SERVICE_USER:$SERVICE_GROUP' '$APP_ROOT/shared/logs'
    chown '$SERVICE_USER:$SERVICE_GROUP' '$APP_ROOT/shared/tmp'
    chmod 0755 '$APP_ROOT'
    chmod 0755 '$APP_ROOT/releases'
    chmod 0750 '$APP_ROOT/shared'
    chmod 0750 '$APP_ROOT/shared/logs'
    chmod 0750 '$APP_ROOT/shared/tmp'
  "
}

backup_remote_state() {
  local host="$1"
  local backup_root="$2"
  local release_name="$3"

  remote_sudo "$host" "
    set -euo pipefail
    backup_dir='$backup_root/$release_name'
    install -d -m 0750 \"\$backup_dir\"
    archive_path=\"\$backup_dir/opt-bens-intake.tgz\"

    if [ -e '$APP_ROOT' ]; then
      tar -C /opt -czf \"\$archive_path\" bens-intake
    else
      tar -czf \"\$archive_path\" --files-from /dev/null
    fi

    if [ -f '/etc/systemd/system/$API_UNIT' ]; then
      cp '/etc/systemd/system/$API_UNIT' \"\$backup_dir/\"
    fi

    if [ -f '/etc/systemd/system/$WORKER_UNIT' ]; then
      cp '/etc/systemd/system/$WORKER_UNIT' \"\$backup_dir/\"
    fi

    if [ -f '$ENV_FILE' ]; then
      cp '$ENV_FILE' \"\$backup_dir/bens-intake.env\"
    fi

    if [ ! -f \"\$archive_path\" ]; then
      echo 'Remote backup archive was not created.' >&2
      exit 1
    fi

    if [ ! -s \"\$archive_path\" ]; then
      echo 'Remote backup archive is empty.' >&2
      exit 1
    fi

    echo \"\$backup_dir\"
  "
}

prepare_remote_release_dir() {
  local host="$1"
  local release_dir="$2"

  remote_sudo "$host" "
    set -euo pipefail
    install -d -m 0755 '$release_dir'
    chown root:root '$release_dir'
    chmod 0755 '$release_dir'
  "
}

stream_repo_dirs_to_remote_release() {
  local host="$1"
  local release_dir="$2"

  prepare_remote_release_dir "$host" "$release_dir"

  (
    cd "$REPO_ROOT"
    tar -cf - "${RUNTIME_FILES[@]}"
  ) | ssh "$host" "sudo -n tar -xf - -C '$release_dir'"

  (
    cd "$REPO_ROOT"
    tar -cf - "${SYSTEMD_FILES[@]}"
  ) | ssh "$host" "sudo -n mkdir -p /tmp/bens-intake-systemd && sudo -n tar -xf - -C /tmp/bens-intake-systemd --strip-components=1"

  remote_sudo "$host" "
    set -euo pipefail
    chown -R root:root '$release_dir'
    find '$release_dir' -type d -exec chmod 0755 {} +
    find '$release_dir' -type f -exec chmod 0644 {} +
  "
}

install_remote_systemd_units() {
  local host="$1"
  remote_sudo "$host" "
    set -euo pipefail
    install -m 0644 /tmp/bens-intake-systemd/$API_UNIT /etc/systemd/system/$API_UNIT
    install -m 0644 /tmp/bens-intake-systemd/$WORKER_UNIT /etc/systemd/system/$WORKER_UNIT
    rm -rf /tmp/bens-intake-systemd
    systemctl daemon-reload
  "
}

install_remote_venv() {
  local host="$1"
  local release_dir="$2"

  remote_sudo "$host" "
    set -euo pipefail
    install -d -m 0750 '$APP_ROOT/shared'
    install -d -m 0750 '$APP_ROOT/shared/logs'
    install -d -m 0750 '$APP_ROOT/shared/tmp'
    if [ ! -x '$VENV_DIR/bin/python' ]; then
      python3 -m venv '$VENV_DIR'
    fi
    chown -R '$SERVICE_USER:$SERVICE_GROUP' '$VENV_DIR'
    chown '$SERVICE_USER:$SERVICE_GROUP' '$APP_ROOT/shared'
    chown '$SERVICE_USER:$SERVICE_GROUP' '$APP_ROOT/shared/logs'
    chown '$SERVICE_USER:$SERVICE_GROUP' '$APP_ROOT/shared/tmp'
    chmod 0750 '$APP_ROOT/shared'
    chmod 0750 '$APP_ROOT/shared/logs'
    chmod 0750 '$APP_ROOT/shared/tmp'
    '$VENV_DIR/bin/pip' install -r '$release_dir/app/requirements.txt'
  "
}

validate_remote_release() {
  local host="$1"
  local release_dir="$2"

  remote_sudo "$host" "
    set -euo pipefail
    '$VENV_DIR/bin/python' -m py_compile \
      '$release_dir/app/config.py' \
      '$release_dir/app/db.py' \
      '$release_dir/app/suitecrm.py' \
      '$release_dir/app/alerts.py' \
      '$release_dir/app/intake_api.py' \
      '$release_dir/app/crm_worker.py'
  "
}

activate_remote_release() {
  local host="$1"
  local release_dir="$2"

  remote_sudo "$host" "
    set -euo pipefail
    ln -sfn '$release_dir' '$APP_ROOT/current'
    systemctl daemon-reload
    systemctl restart '$API_UNIT'
    systemctl restart '$WORKER_UNIT'
  "
}

prune_old_releases() {
  local host="$1"
  remote_sudo "$host" "
    set -euo pipefail
    cd '$APP_ROOT/releases'
    current_target=\$(readlink -f '$APP_ROOT/current' || true)
    ls -1dt */ 2>/dev/null | sed 's:/$::' | awk 'NR>$KEEP_RELEASES' | while read -r old; do
      old_path='$APP_ROOT/releases/'\"\$old\"
      if [ \"\$old_path\" != \"\$current_target\" ]; then
        rm -rf \"\$old_path\"
      fi
    done
  "
}
