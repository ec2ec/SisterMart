#!/usr/bin/env bash
# 从备份目录回滚。用法：bash scripts/deploy/rollback.sh backups/YYYYMMDD-HHMMSS
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SRC="${1:-}"
if [[ -z "$SRC" || ! -d "$SRC" ]]; then
  echo "usage: $0 backups/YYYYMMDD-HHMMSS" >&2
  exit 1
fi

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_NAME="${DB_NAME:-sistermart}"
DB_USER="${DB_USER:-sistermart}"
DB_PASS="${DB_PASS:-sistermart}"

if [[ -f "$SRC/db.sql.gz" ]]; then
  echo "==> restore DB"
  gunzip -c "$SRC/db.sql.gz" | mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME"
fi

if [[ -f "$SRC/apps.tgz" ]]; then
  echo "==> restore apps snapshot"
  tar -C "$ROOT" -xzf "$SRC/apps.tgz"
fi

echo "OK  rolled back from $SRC"
echo "请按需重启 php-fpm / nginx"
