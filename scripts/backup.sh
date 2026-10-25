#!/usr/bin/env bash
# MedXpert backup: a database dump and a copy of the uploads folder.
# Run it by hand or from cron, for example every night at 02:30:
#   30 2 * * * /path/to/medxpert/scripts/backup.sh >> /path/to/medxpert/logs/backup.log 2>&1
#
# Settings come from config/config.local.php, or from MX_DB_HOST, MX_DB_USER,
# MX_DB_PASS, MX_DB_NAME and MX_DB_PORT. Set KEEP to change how many backups stay (default 14).
# Copy the files in backups/ to another place too (another server or cloud storage).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
KEEP="${KEEP:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"
OUT="$ROOT/backups"

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

if [ -z "$DB_USER" ] || [ -z "$DB_NAME" ]; then
  echo "Database settings not found. Create config/config.local.php first." >&2
  exit 1
fi

DUMP="$(command -v mysqldump || command -v mariadb-dump || true)"
if [ -z "$DUMP" ]; then
  echo "mysqldump was not found. Install the MySQL or MariaDB client tools." >&2
  exit 1
fi

umask 077
mkdir -p "$OUT"

# The password goes through a private file so it never shows in the process list
CNF="$(mktemp)"
trap 'rm -f "$CNF"' EXIT
printf '[client]\nuser=%s\npassword=%s\nhost=%s\nport=%s\n' "$DB_USER" "$DB_PASS" "$DB_HOST" "$DB_PORT" > "$CNF"

"$DUMP" --defaults-extra-file="$CNF" --single-transaction --routines --no-tablespaces "$DB_NAME" | gzip -9 > "$OUT/db-$STAMP.sql.gz"
tar -czf "$OUT/uploads-$STAMP.tar.gz" -C "$ROOT" uploads

# Remove the oldest backups beyond KEEP
for prefix in db uploads; do
  ls -1t "$OUT"/"$prefix"-*.gz 2>/dev/null | tail -n +"$((KEEP + 1))" | xargs -r rm -f --
done

echo "Backup done: $OUT/db-$STAMP.sql.gz and uploads-$STAMP.tar.gz"
