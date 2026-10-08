#!/usr/bin/env bash
set -euo pipefail

# Exports offline snapshots outside the web root; public routes remain dynamic.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

echo "=== Generating Generative Engine Optimization (GEO) Artifacts ==="

"${ROOT_DIR}/bin/sail-run" php artisan geo:generate-llms-txt

echo "=== Exported private GEO snapshots to storage/app/private/geo-export ==="
