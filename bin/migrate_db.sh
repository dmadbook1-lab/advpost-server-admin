#!/usr/bin/env bash
#
# Apply SQL files from private/sql/ to the PRODUCTION database.
#
# Usage:
#   bin/migrate_db.sh --list                      # show files + which are recorded as applied
#   bin/migrate_db.sh service_requests.sql        # apply one or more files (names in private/sql/)
#   bin/migrate_db.sh --dry-run service_requests.sql
#   bin/migrate_db.sh --mark-applied s3_schema_only.sql   # record a file as done without running it
#
# Reads DB_HOST / DB_USERNAME / DB_PASSWORD / DB_DATABASE from .env (production values).
# Steps: backup (mysqldump) -> show SQL -> confirm -> apply in a single run -> record in schema_migrations.
# Run it on the server, or from your machine if the host allows remote MySQL for your IP.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SQL_DIR="$ROOT/private/sql"
BACKUP_DIR="$ROOT/storage/db_backups"
ENV_FILE="$ROOT/.env"

DRY_RUN=0; MARK_ONLY=0; LIST=0; FILES=()
for a in "$@"; do
  case "$a" in
    --dry-run) DRY_RUN=1 ;;
    --mark-applied) MARK_ONLY=1 ;;
    --list) LIST=1 ;;
    -h|--help) sed -n '2,14p' "$0"; exit 0 ;;
    *) FILES+=("$a") ;;
  esac
done

[ -f "$ENV_FILE" ] || { echo "Missing $ENV_FILE" >&2; exit 1; }

# Read one KEY from .env without sourcing the file (values may contain special chars).
env_get() {
  local v
  v="$(grep -E "^[[:space:]]*$1=" "$ENV_FILE" | tail -1 | cut -d= -f2- || true)"
  v="${v%$'\r'}"
  v="${v#\"}"; v="${v%\"}"; v="${v#\'}"; v="${v%\'}"
  printf '%s' "$v"
}

DB_HOST="$(env_get DB_HOST)"
DB_USER="$(env_get DB_USERNAME)"
DB_PASS="$(env_get DB_PASSWORD)"
DB_NAME="$(env_get DB_DATABASE)"
DB_PORT="3306"
if [[ "$DB_HOST" == *:* ]]; then DB_PORT="${DB_HOST##*:}"; DB_HOST="${DB_HOST%%:*}"; fi

[ -n "$DB_HOST" ] && [ -n "$DB_USER" ] && [ -n "$DB_NAME" ] || { echo "DB_* production values missing in .env" >&2; exit 1; }
if [ "$DB_HOST" = "localhost" ] || [ "$DB_HOST" = "127.0.0.1" ]; then
  echo "Note: DB_HOST is $DB_HOST (fine if you're running this on the server)."
fi

export MYSQL_PWD="$DB_PASS"   # keeps the password off the command line
MYSQL=(mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME")

"${MYSQL[@]}" -e "SELECT 1" >/dev/null || { echo "Cannot connect to $DB_HOST/$DB_NAME" >&2; exit 1; }
"${MYSQL[@]}" -e "CREATE TABLE IF NOT EXISTS schema_migrations (
  filename VARCHAR(190) NOT NULL PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"

is_applied() {
  [ "$("${MYSQL[@]}" -N -e "SELECT COUNT(*) FROM schema_migrations WHERE filename='$1'")" != "0" ]
}

if [ "$LIST" = 1 ] || [ ${#FILES[@]} -eq 0 ]; then
  echo "Target: $DB_USER@$DB_HOST/$DB_NAME"
  for f in "$SQL_DIR"/*.sql; do
    n="$(basename "$f")"
    if is_applied "$n"; then echo "  [applied] $n"; else echo "  [pending] $n"; fi
  done
  exit 0
fi

for n in "${FILES[@]}"; do
  [ -f "$SQL_DIR/$n" ] || { echo "Not found: $SQL_DIR/$n" >&2; exit 1; }
done

if [ "$MARK_ONLY" = 1 ]; then
  for n in "${FILES[@]}"; do
    "${MYSQL[@]}" -e "INSERT IGNORE INTO schema_migrations (filename) VALUES ('$n')"
    echo "Marked applied: $n"
  done
  exit 0
fi

echo "Target database: $DB_USER@$DB_HOST/$DB_NAME  (PRODUCTION)"
for n in "${FILES[@]}"; do
  is_applied "$n" && echo "WARNING: $n is already recorded as applied."
  echo "----- $n -----"; cat "$SQL_DIR/$n"; echo
done

if [ "$DRY_RUN" = 1 ]; then echo "Dry run: nothing executed."; exit 0; fi

read -r -p "Type the database name ($DB_NAME) to confirm: " ans
[ "$ans" = "$DB_NAME" ] || { echo "Aborted."; exit 1; }

mkdir -p "$BACKUP_DIR"
BACKUP="$BACKUP_DIR/${DB_NAME}_$(date +%Y%m%d_%H%M%S).sql.gz"
echo "Backing up to $BACKUP ..."
mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" --single-transaction --routines --triggers "$DB_NAME" | gzip > "$BACKUP"
[ -s "$BACKUP" ] || { echo "Backup is empty, aborting." >&2; exit 1; }

for n in "${FILES[@]}"; do
  echo "Applying $n ..."
  "${MYSQL[@]}" < "$SQL_DIR/$n"
  "${MYSQL[@]}" -e "INSERT IGNORE INTO schema_migrations (filename) VALUES ('$n')"
  echo "  done"
done

echo "All done. Backup kept at $BACKUP"
echo "Restore if needed: gunzip -c $BACKUP | mysql -h $DB_HOST -u $DB_USER -p $DB_NAME"
