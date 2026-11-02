#!/usr/bin/env bash
# 清理本機發佈產物（簽名包 .tar + VPS root .zip）。
# 建議在發佈通道「發佈成功」後立刻執行，避免 releases/ 堆積。
#
# 用法（倉庫根）：
#   bash scripts/deploy/cleanup-release-artifacts.sh
#   bash scripts/deploy/cleanup-release-artifacts.sh --keep-zip   # 僅刪 .tar，保留 sistermart-vps-root.zip
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
REL="$ROOT/releases"
KEEP_ZIP=0
for arg in "$@"; do
  case "$arg" in
    --keep-zip) KEEP_ZIP=1 ;;
    -h|--help)
      echo "用法: bash scripts/deploy/cleanup-release-artifacts.sh [--keep-zip]"
      exit 0
      ;;
    *)
      echo "未知參數: $arg" >&2
      exit 1
      ;;
  esac
done

mkdir -p "$REL"
removed=0
shopt -s nullglob
for f in "$REL"/sistermart-release-*.tar "$REL"/sistermart-release-*.tar.gz; do
  rm -f -- "$f"
  echo "removed $(basename "$f")"
  removed=$((removed + 1))
done
if [[ "$KEEP_ZIP" != "1" ]]; then
  for f in "$REL"/sistermart-vps-root.zip; do
    if [[ -f "$f" ]]; then
      rm -f -- "$f"
      echo "removed $(basename "$f")"
      removed=$((removed + 1))
    fi
  done
fi

echo "OK  cleaned $removed file(s) under releases/"
