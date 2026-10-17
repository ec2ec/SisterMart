#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../apps/api"
if [[ ! -f .env ]]; then
  cp .example.env .env
  echo "created apps/api/.env from .example.env"
fi
exec php think run -H 0.0.0.0 -p "${API_PORT:-8000}"
