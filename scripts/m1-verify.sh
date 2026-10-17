#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
API="${API_BASE:-http://127.0.0.1:8000}"

echo "==> API health: $API/health"
curl -fsS "$API/health" | tee /tmp/sistermart-health.json
python3 - <<'PY'
import json,sys
d=json.load(open("/tmp/sistermart-health.json"))
assert d.get("ok") is True, d
assert d.get("db",{}).get("ok") is True, d
print("health OK")
PY

echo "==> H5 build"
(cd "$ROOT/apps/h5" && pnpm build)

echo "==> Admin build"
(cd "$ROOT/apps/admin" && pnpm build)

echo "M1 verify passed"
