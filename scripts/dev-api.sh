#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../apps/api"
if [[ ! -f .env ]]; then
  cp .example.env .env
  echo "created apps/api/.env from .example.env"
fi
# 評價圖上傳需 ≥5MB；用 -d 直接餵給內建伺服器（think run 子進程不繼承 -c）
PORT="${API_PORT:-8000}"
exec php -d upload_max_filesize=8M -d post_max_size=12M -d memory_limit=256M \
  -S "0.0.0.0:${PORT}" -t public public/router.php
