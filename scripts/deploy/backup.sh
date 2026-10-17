#!/usr/bin/env bash
# 备份 MySQL + 应用目录。用法：bash scripts/deploy/backup.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
STAMP="$(date +%Y%m%d-%H%M%S)"
OUT="${BACKUP_DIR:-$ROOT/backups}/$STAMP"
mkdir -p "$OUT"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_NAME="${DB_NAME:-sistermart}"
DB_USER="${DB_USER:-sistermart}"
DB_PASS="${DB_PASS:-sistermart}"

echo "==> dump $DB_NAME"
mysqldump -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" --single-transaction --routines --no-tablespaces "$DB_NAME" | gzip > "$OUT/db.sql.gz"

echo "==> archive apps"
tar -C "$ROOT" -czf "$OUT/apps.tgz" apps/api apps/h5/dist apps/admin/dist 2>/dev/null || \
  tar -C "$ROOT" -czf "$OUT/apps.tgz" apps/api

echo "$STAMP" > "$OUT/meta.txt"
echo "OK  $OUT"
echo "$OUT"
