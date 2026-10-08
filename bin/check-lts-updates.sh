#!/usr/bin/env bash
set -euo pipefail

# LTS Watchdog: Scans for Laravel LTS releases and security advisories

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

echo "=== [LTS Watchdog Agent] Checking Laravel LTS Status & CVE Advisories ==="

# Execute check inside container
"${ROOT_DIR}/bin/sail-run" php artisan system:check-lts || {
    echo "Artisan command failed. Falling back to composer audit..."
    "${ROOT_DIR}/bin/sail-run" composer audit
}

echo "=== LTS Audit Complete ==="
