#!/usr/bin/env bash
set -euo pipefail

# Exports offline snapshots outside the web root; public routes remain dynamic.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
cd "$ROOT_DIR"

echo "=== Generating Generative Engine Optimization (GEO) Artifacts ==="

if [[ -f /.dockerenv ]]; then
    php artisan geo:generate-llms-txt
else
    ./vendor/bin/sail artisan geo:generate-llms-txt
fi

echo "=== Exported private GEO snapshots to storage/app/private/geo-export ==="
