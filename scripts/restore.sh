#!/usr/bin/env bash
# Restore a database backup made by backup.sh.
#   scripts/restore.sh backups/db-20261018-023000.sql.gz
# This replaces the data in the database named in your settings. Make a fresh backup first.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FILE="${1:-}"
if [ -z "$FILE" ] || [ ! -f "$FILE" ]; then
  echo "Usage: scripts/restore.sh backups/db-YYYYMMDD-HHMMSS.sql.gz" >&2
  exit 1
fi

read_setting() {
  local key="$1" env_name="MX_$(echo "$1" | tr '[:lower:]' '[:upper:]')"
  if [ -n "${!env_name:-}" ]; then echo "${!env_name}"; return; fi
  php -r '$c = is_file($argv[1]) ? require $argv[1] : []; echo $c[$argv[2]] ?? "";' "$ROOT/config/config.local.php" "$key"
}

DB_HOST="$(read_setting db_host)"; DB_HOST="${DB_HOST:-localhost}"
DB_USER="$(read_setting db_user)"
DB_PASS="$(read_setting db_pass)"
DB_NAME="$(read_setting db_name)"
DB_PORT="$(read_setting db_port)"; DB_PORT="${DB_PORT:-3306}"

echo "This replaces the data in database '$DB_NAME' on $DB_HOST."
read -r -p "Type the database name to continue: " ANSWER
if [ "$ANSWER" != "$DB_NAME" ]; then
  echo "Cancelled."
  exit 1
fi

CLIENT="$(command -v mysql || command -v mariadb || true)"
[ -n "$CLIENT" ] || { echo "The mysql client was not found." >&2; exit 1; }

CNF="$(mktemp)"
trap 'rm -f "$CNF"' EXIT
chmod 600 "$CNF"
printf '[client]\nuser=%s\npassword=%s\nhost=%s\nport=%s\n' "$DB_USER" "$DB_PASS" "$DB_HOST" "$DB_PORT" > "$CNF"

gunzip -c "$FILE" | "$CLIENT" --defaults-extra-file="$CNF" "$DB_NAME"
echo "Restored $FILE into $DB_NAME. To restore uploads, unpack uploads-*.tar.gz in the project folder."
