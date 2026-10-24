#!/usr/bin/env bash
# SisterMart：構建前端，可選打 VPS 根目錄覆蓋 zip。
#
# 用法（倉庫根目錄）：
#   pnpm build          # 只構建 H5 + Admin
#   pnpm build zip      # 構建 + 打 releases/sistermart-vps-root.zip
#   pnpm zip            # 用已有 dist 只打包（不重建）
#   pnpm build zip -- --with-static-assets   # 本次含新 mock/products 圖時再帶上
#
# 覆蓋安裝約定：
#   默認不打包 /mock、/products（服務器已有且未更新，避免白傳）。
#   僅當本次更新涉及新的靜態資源圖時，設 INCLUDE_STATIC_ASSETS=1 或傳 --with-static-assets。
#
# 環境變量：
#   VITE_API_BASE              預設 https://sistermart.vip/api/index.php
#   ZIP_OUT                    預設 releases/sistermart-vps-root.zip
#   INCLUDE_STATIC_ASSETS      1=打入 mock/ + products/（默認 0）
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

API_BASE="${VITE_API_BASE:-https://sistermart.vip/api/index.php}"
ZIP_OUT="${ZIP_OUT:-$ROOT/releases/sistermart-vps-root.zip}"
STAGING="${TMPDIR:-/tmp}/sistermart-vps-pack-$$"
DO_BUILD=1
DO_ZIP=0
INCLUDE_STATIC_ASSETS="${INCLUDE_STATIC_ASSETS:-0}"

# pnpm build zip  → $1=zip
# pnpm zip        → 腳本名/第一參為 zip-only
# pnpm build      → 只構建
MODE="${1:-}"
shift || true
while [[ $# -gt 0 ]]; do
  case "$1" in
    --with-static-assets|with-static-assets)
      INCLUDE_STATIC_ASSETS=1
      shift
      ;;
    --no-static-assets|no-static-assets)
      INCLUDE_STATIC_ASSETS=0
      shift
      ;;
    --) shift ;;
    zip|build|pack|release)
      # pnpm 多餘參數
      shift
      ;;
    *)
      echo "未知參數: $1（可用 --with-static-assets）" >&2
      exit 1
      ;;
  esac
done

case "$MODE" in
  zip)
    DO_ZIP=1
    # 若由「pnpm zip」單獨調用，可設 SKIP_BUILD=1；預設仍構建以保證完整
    if [[ "${npm_lifecycle_event:-}" == "zip" && "${SISTERMART_ZIP_ONLY:-}" == "1" ]]; then
      DO_BUILD=0
    fi
    # `pnpm zip` 生命週期事件名為 zip：只打包；`pnpm build zip` 事件為 build 且帶參 zip：先構建再打包
    if [[ "${npm_lifecycle_event:-}" == "zip" ]]; then
      DO_BUILD=0
    fi
    ;;
  ""|build)
    DO_ZIP=0
    ;;
  *)
    echo "用法: pnpm build | pnpm build zip | pnpm zip [-- --with-static-assets]" >&2
    exit 1
    ;;
esac

build_frontends() {
  export CI="${CI:-1}"
  local app_version="${VITE_APP_VERSION:-}"
  if [[ -z "$app_version" && -n "${RELEASE_VERSION:-}" ]]; then
    app_version="$(printf '%s' "$RELEASE_VERSION" | sed -E 's/^(v?[0-9]+\.[0-9]+\.[0-9]+).*/\1/')"
  fi
  app_version="${app_version:-v1.30.14}"
  echo "==> H5 build (VITE_API_BASE=$API_BASE VITE_APP_VERSION=$app_version)"
  cd "$ROOT/apps/h5"
  printf 'VITE_API_BASE=%s\nVITE_APP_LOCALE=zh-HK\nVITE_APP_VERSION=%s\n' "$API_BASE" "$app_version" > .env.production
  pnpm install --frozen-lockfile 2>/dev/null || pnpm install --force
  pnpm build

  echo "==> Admin build"
  cd "$ROOT/apps/admin"
  printf 'VITE_API_BASE=%s\nVITE_APP_LOCALE=zh-HK\n' "$API_BASE" > .env.production
  pnpm install --frozen-lockfile 2>/dev/null || pnpm install --force
  pnpm build
  cd "$ROOT"
  echo "OK  dist → apps/h5/dist  apps/admin/dist"
}

pack_zip() {
  local h5_dist="$ROOT/apps/h5/dist"
  local admin_dist="$ROOT/apps/admin/dist"
  local api="$ROOT/apps/api"
  local mock_src="$ROOT/docs/design-assets/mock-images"
  local products_src="$ROOT/apps/h5/public/products"

  [[ -d "$h5_dist" ]] || { echo "缺少 $h5_dist，請先 pnpm build" >&2; exit 1; }
  [[ -d "$admin_dist" ]] || { echo "缺少 $admin_dist，請先 pnpm build" >&2; exit 1; }
  [[ -d "$api/vendor" ]] || { echo "缺少 apps/api/vendor，請在 apps/api 執行 composer install" >&2; exit 1; }

  rm -rf "$STAGING"
  mkdir -p "$STAGING/admin" "$STAGING/api" "$STAGING/sm-backend" "$STAGING/vps-spa"

  echo "==> Stage H5 → zip root"
  cp -a "$h5_dist"/. "$STAGING/"

  # 全站禁止爬蟲：確保根目錄 robots.txt（vite 會從 public 帶入 dist）
  if [[ ! -f "$STAGING/robots.txt" && -f "$ROOT/apps/h5/public/robots.txt" ]]; then
    cp "$ROOT/apps/h5/public/robots.txt" "$STAGING/robots.txt"
  fi
  if [[ ! -f "$STAGING/admin/robots.txt" && -f "$ROOT/apps/admin/public/robots.txt" ]]; then
    mkdir -p "$STAGING/admin"
    cp "$ROOT/apps/admin/public/robots.txt" "$STAGING/admin/robots.txt"
  fi

  # 覆蓋安裝：默認不帶 mock/products（dist 可能已從 public 拷入，需剔除）
  if [[ "$INCLUDE_STATIC_ASSETS" == "1" ]]; then
    echo "==> INCLUDE_STATIC_ASSETS=1 → 打入 /mock + /products"
    mkdir -p "$STAGING/mock" "$STAGING/products"
    if [[ -d "$h5_dist/mock" && -n "$(ls -A "$h5_dist/mock" 2>/dev/null || true)" ]]; then
      cp -a "$h5_dist/mock"/. "$STAGING/mock/"
    elif [[ -d "$mock_src" ]]; then
      cp -a "$mock_src"/. "$STAGING/mock/"
    fi
    if [[ -d "$products_src" ]]; then
      cp -a "$products_src"/. "$STAGING/products/"
    fi
  else
    echo "==> 跳過 /mock、/products（覆蓋安裝約定；需要時加 --with-static-assets）"
    rm -rf "$STAGING/mock" "$STAGING/products"
  fi

  echo "==> Stage Admin → /admin"
  cp -a "$admin_dist"/. "$STAGING/admin/"
  if [[ -f "$ROOT/apps/admin/public/.htaccess" ]]; then
    cp "$ROOT/apps/admin/public/.htaccess" "$STAGING/admin/.htaccess"
  fi

  echo "==> Stage API → /sm-backend + /api/index.php"
  # ThinkPHP 應用（排除 runtime / .env 密鑰；無 rsync 時用 tar）
  mkdir -p "$STAGING/sm-backend"
  tar -C "$api" \
    --exclude='./runtime' \
    --exclude='./.env' \
    --exclude='./.env.*' \
    --exclude='./node_modules' \
    --exclude='./.git' \
    --exclude='./public/storage' \
    -cf - . | tar -C "$STAGING/sm-backend" -xf -
  mkdir -p "$STAGING/sm-backend/runtime/"{cache,log,session}
  if [[ -f "$api/.example.env" ]]; then
    cp "$api/.example.env" "$STAGING/sm-backend/.env.example"
  elif [[ -f "$api/.env.example" ]]; then
    cp "$api/.env.example" "$STAGING/sm-backend/.env.example"
  fi
  # 確保打包裡沒有誤帶的 .env
  rm -f "$STAGING/sm-backend/.env"

  # 入口：不寫入資料庫密碼，僅引導已有 .env
  cat > "$STAGING/api/index.php" <<'PHP'
<?php
namespace think;

$backend = __DIR__ . '/../sm-backend';
$pc = $backend . '/vendor/composer/platform_check.php';
if (is_file($pc)) {
    $src = (string) file_get_contents($pc);
    if (str_contains($src, '80200') || str_contains($src, '8.2.0')) {
        file_put_contents($pc, "<?php\n// Disabled for shared-host PHP 8.1; prefer panel PHP >= 8.2\n");
    }
}
$env = $backend . '/.env';
if (!is_file($env) || filesize($env) < 10) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Missing sm-backend/.env — copy .env.example and fill DB credentials.\n";
    exit(1);
}

require $backend . '/vendor/autoload.php';
$http = (new App($backend . '/'))->http;
$response = $http->run();
$response->send();
$http->end($response);
PHP

  # SPA 備註
  if [[ -d "$ROOT/releases/vps-spa" ]]; then
    cp -a "$ROOT/releases/vps-spa"/. "$STAGING/vps-spa/" 2>/dev/null || true
  fi

  # 不打包 _releases/：由服務端 ReleaseService 首次使用時自建（含 .htaccess）。
  # 若打進包會命中黑名單 _releases/pending 等，且與運行時狀態衝突。
  rm -rf "$STAGING/_releases"

  local assets_note="mock/products: OMITTED (server already has them)"
  if [[ "$INCLUDE_STATIC_ASSETS" == "1" ]]; then
    assets_note="mock/products: INCLUDED"
  fi

  cat > "$STAGING/README-DEPLOY.txt" <<EOF
SisterMart VPS root overlay — $(date -u +%Y-%m-%dT%H:%MZ)

Contains: H5 (/) + Admin (/admin) + API (/api/index.php → sm-backend)
Static:   $assets_note

unzip -o $(basename "$ZIP_OUT")   # at website ROOT (overlay; does not delete existing mock/products)
Then hard refresh. Ensure sm-backend/.env exists on server (not shipped).

Default pack skips /mock and /products. New image assets:
  INCLUDE_STATIC_ASSETS=1 pnpm build zip
  # or: pnpm build zip -- --with-static-assets

Release channel: set RELEASE_ENABLED=true and RELEASE_HMAC_SECRET in sm-backend/.env
Pack: RELEASE_HMAC_SECRET=... pnpm release pack

H5:    https://your-domain/
Admin: https://your-domain/admin/
API:   https://your-domain/api/index.php/api/v1/health
EOF

  mkdir -p "$(dirname "$ZIP_OUT")"
  rm -f "$ZIP_OUT"
  echo "==> Zip $ZIP_OUT"
  (cd "$STAGING" && zip -r -q "$ZIP_OUT" .)
  rm -rf "$STAGING"

  local size
  size=$(du -h "$ZIP_OUT" | awk '{print $1}')
  echo "OK  $ZIP_OUT ($size)"
  echo "    解壓到網站根: unzip -o $(basename "$ZIP_OUT")"
}

if [[ "$DO_BUILD" == "1" ]]; then
  build_frontends
fi

if [[ "$DO_ZIP" == "1" ]]; then
  pack_zip
elif [[ "$DO_BUILD" == "1" ]]; then
  echo "提示: 完整打包請執行  pnpm build zip"
fi
