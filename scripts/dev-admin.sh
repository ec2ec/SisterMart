#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../apps/admin"
if [[ ! -f .env ]]; then
  cp .env.example .env
fi
exec pnpm dev --host 0.0.0.0 --port "${ADMIN_PORT:-5174}"
