#!/usr/bin/env bash
# Save a compressed copy of the live database and uploaded photos in ~/backups.
# Run from anywhere:  bash ~/odaamsterderm_web/deploy/backup-db.sh
set -euo pipefail

cd "$(dirname "$0")/.."
env_value() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"; }

mkdir -p "$HOME/backups"
stamp=$(date +%Y-%m-%d-%H%M)
db_file="$HOME/backups/db-$stamp.sql.gz"

MYSQL_PWD="$(env_value DB_PASSWORD)" mysqldump --single-transaction --no-tablespaces \
    -h "$(env_value DB_HOST)" -u "$(env_value DB_USERNAME)" "$(env_value DB_DATABASE)" | gzip > "$db_file"
tar -czf "$HOME/backups/photos-$stamp.tar.gz" -C storage/app public

ls -lh "$HOME/backups" | tail -4
echo "Backup saved: $db_file"
