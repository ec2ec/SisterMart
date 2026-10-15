#!/usr/bin/env bash
# 测试机安装骨架（Ubuntu 系）。需 root 或 sudo。
# 用法：sudo bash scripts/deploy/install.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
TARGET="${DEPLOY_ROOT:-/var/www/sistermart}"

echo "==> SisterMart install → $TARGET"
mkdir -p "$TARGET"
rsync -a --exclude node_modules --exclude vendor --exclude .git "$ROOT/" "$TARGET/" || cp -a "$ROOT/." "$TARGET/"

if [[ ! -f "$TARGET/apps/api/.env" ]]; then
  cp "$TARGET/apps/api/.example.env" "$TARGET/apps/api/.env"
  sed -i 's/APP_DEBUG = true/APP_DEBUG = false/' "$TARGET/apps/api/.env" || true
fi

echo "==> composer"
cd "$TARGET/apps/api"
composer install --no-dev --optimize-autoloader

echo "==> seed ops + demo (若库已存在)"
php database/m8_seed.php || true
php database/m9_seed.php || true

echo "==> frontend build"
VITE_API_BASE="${VITE_API_BASE:-http://127.0.0.1:8000}" bash "$TARGET/scripts/deploy/build-frontends.sh"

echo "==> nginx example: $TARGET/scripts/deploy/nginx.sistermart.conf.example"
echo "完成。请配置 Nginx/SSL 与 DNS，然后填写 docs/验收/M9-验收记录.md 中的公网地址。"
