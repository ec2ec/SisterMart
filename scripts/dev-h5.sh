#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../apps/h5"
if [[ ! -f .env ]]; then
  cp .env.example .env
fi
exec pnpm dev --host 0.0.0.0 --port "${H5_PORT:-5173}"
