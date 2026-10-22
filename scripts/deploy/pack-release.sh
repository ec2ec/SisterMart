#!/usr/bin/env bash
# 打簽名發佈包（窄版 Release Channel）
#
# 用法（倉庫根）：
#   RELEASE_HMAC_SECRET=... pnpm release pack
#   RELEASE_HMAC_SECRET=... bash scripts/deploy/pack-release.sh [--migrate path.sql] [--skip-build]
#   INCLUDE_STATIC_ASSETS=1 … pnpm release pack   # 本次有新 mock/products 圖時
#
# 產出：
#   releases/sistermart-release-<stamp>.tar
#   內含 package.zip + manifest.json + 可選 migrate.sql
#
# 覆蓋約定：默認 package 不含 /mock、/products（與 build-zip 一致）。
#
# 環境變量：
#   RELEASE_HMAC_SECRET   必填（與 VPS sm-backend/.env 相同）
#   RELEASE_TITLE / RELEASE_DESC / RELEASE_VERSION
#   ZIP_OUT               預設先跑 build-zip 的產物
#   INCLUDE_STATIC_ASSETS 傳給 build-zip（默認 0）
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

MIGRATE=""
SKIP_BUILD=0
while [[ $# -gt 0 ]]; do
  case "$1" in
    pack|release|-- ) # pnpm release pack [-- --skip-build]
      shift
      ;;
    --migrate)
      MIGRATE="${2:-}"
      shift 2
      ;;
    --skip-build|skip-build)
      SKIP_BUILD=1
      shift
      ;;
    *)
      echo "未知參數: $1" >&2
      exit 1
      ;;
  esac
done

SECRET="${RELEASE_HMAC_SECRET:-}"
if [[ -z "$SECRET" || ${#SECRET} -lt 16 ]]; then
  echo "請設置 RELEASE_HMAC_SECRET（至少 16 字符，須與 VPS 一致）" >&2
  exit 1
fi

ZIP_OUT="${ZIP_OUT:-$ROOT/releases/sistermart-vps-root.zip}"
if [[ "$SKIP_BUILD" != "1" ]]; then
  # 透傳靜態資源開關（默認不帶 mock/products）
  if [[ "${INCLUDE_STATIC_ASSETS:-0}" == "1" ]]; then
    bash "$ROOT/scripts/deploy/build-zip.sh" zip --with-static-assets
  else
    bash "$ROOT/scripts/deploy/build-zip.sh" zip
  fi
fi
[[ -f "$ZIP_OUT" ]] || { echo "缺少 $ZIP_OUT" >&2; exit 1; }

STAMP="$(date -u +%Y%m%d-%H%M%S)"
VERSION="${RELEASE_VERSION:-$STAMP}"
TITLE="${RELEASE_TITLE:-SisterMart release $VERSION}"
DESC="${RELEASE_DESC:-}"
REL_ID="rel_${STAMP//-/_}_pack"
STAGE="${TMPDIR:-/tmp}/sistermart-release-pack-$$"
mkdir -p "$STAGE"
cleanup() { rm -rf "$STAGE"; }
trap cleanup EXIT

cp "$ZIP_OUT" "$STAGE/package.zip"
PKG_SHA="$(sha256sum "$STAGE/package.zip" | awk '{print $1}')"
MIG_SHA=""
if [[ -n "$MIGRATE" ]]; then
  [[ -f "$MIGRATE" ]] || { echo "migrate 不存在: $MIGRATE" >&2; exit 1; }
  cp "$MIGRATE" "$STAGE/migrate.sql"
  MIG_SHA="$(sha256sum "$STAGE/migrate.sql" | awk '{print $1}')"
fi

# 用 PHP 生成與伺服器一致的 canonical HMAC（鍵排序）
export SM_REL_SECRET="$SECRET"
export SM_REL_ID="$REL_ID"
export SM_REL_VERSION="$VERSION"
export SM_REL_TITLE="$TITLE"
export SM_REL_DESC="$DESC"
export SM_REL_PKG_SHA="$PKG_SHA"
export SM_REL_MIG_SHA="$MIG_SHA"

php <<'PHP' > "$STAGE/manifest.json"
<?php
$manifest = [
    'schema' => 1,
    'release_id' => getenv('SM_REL_ID'),
    'version' => getenv('SM_REL_VERSION'),
    'title' => getenv('SM_REL_TITLE'),
    'description' => getenv('SM_REL_DESC') ?: '',
    'created_at' => gmdate('c'),
    'package_sha256' => getenv('SM_REL_PKG_SHA'),
    'migrate_sha256' => getenv('SM_REL_MIG_SHA') !== '' ? getenv('SM_REL_MIG_SHA') : null,
    'targets' => ['h5', 'admin', 'api', 'mock', 'products'],
    'path_whitelist' => [
        'index.html', 'favicon.svg', 'favicon.ico', 'robots.txt', '.htaccess',
        'site.webmanifest', 'icons.svg',
        'apple-touch-icon.png', 'apple-touch-icon-180x180.png',
        'android-chrome-192x192.png', 'android-chrome-512x512.png',
        'assets/', 'admin/', 'api/', 'sm-backend/', 'mock/', 'products/', 'vps-spa/',
        'README-DEPLOY.txt',
    ],
    'path_blacklist' => [
        'sm-backend/.env',
        'sm-backend/runtime/',
        'sm-backend/public/storage/',
        '_releases/pending/',
        '_releases/staging/',
        '_releases/snapshots/',
        '_releases/logs/',
        '_releases/state.json',
        '.env',
    ],
    'requires_backup_db' => getenv('SM_REL_MIG_SHA') !== '',
];

function ksort_recursive(array &$data): void
{
    ksort($data);
    foreach ($data as &$v) {
        if (is_array($v)) {
            $isList = array_keys($v) === range(0, count($v) - 1);
            if (!$isList) {
                ksort_recursive($v);
            }
        }
    }
}

$copy = $manifest;
ksort_recursive($copy);
$canonical = json_encode($copy, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$manifest['signature'] = hash_hmac('sha256', $canonical, (string) getenv('SM_REL_SECRET'));
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
PHP

OUT="$ROOT/releases/sistermart-release-${STAMP}.tar"
rm -f "$OUT"
# 用 tar 打包（無壓縮，上傳後再校驗；體積與 zip 接近）
tar -C "$STAGE" -cf "$OUT" package.zip manifest.json
if [[ -f "$STAGE/migrate.sql" ]]; then
  tar -C "$STAGE" -rf "$OUT" migrate.sql
fi

SIZE="$(du -h "$OUT" | awk '{print $1}')"
echo "OK  $OUT ($SIZE)"
echo "    version=$VERSION"
echo "    package_sha256=$PKG_SHA"
[[ -n "$MIG_SHA" ]] && echo "    migrate_sha256=$MIG_SHA"
echo "    後台上傳此 .tar → 校驗簽名 → 點發佈"
