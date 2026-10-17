#!/usr/bin/env bash
# 生产构建 H5 + Admin。用法：
#   VITE_API_BASE=https://api.example.com bash scripts/deploy/build-frontends.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
API_BASE="${VITE_API_BASE:-http://127.0.0.1:8000}"

echo "==> H5 build (VITE_API_BASE=$API_BASE)"
cd "$ROOT/apps/h5"
printf 'VITE_API_BASE=%s\nVITE_APP_LOCALE=zh-HK\n' "$API_BASE" > .env.production
pnpm install --frozen-lockfile 2>/dev/null || pnpm install
pnpm build

echo "==> Admin build"
cd "$ROOT/apps/admin"
printf 'VITE_API_BASE=%s\nVITE_APP_LOCALE=zh-HK\n' "$API_BASE" > .env.production
pnpm install --frozen-lockfile 2>/dev/null || pnpm install
pnpm build

echo "OK  dist → apps/h5/dist  apps/admin/dist"
