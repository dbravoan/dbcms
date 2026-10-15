#!/usr/bin/env bash
set -euo pipefail

# LTS Watchdog: Scans for Laravel LTS releases and security advisories

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
cd "$ROOT_DIR"

echo "=== [LTS Watchdog Agent] Checking Laravel LTS Status & CVE Advisories ==="

# Sail dispatches from the host; existing containers execute their own tools.
if [[ -f /.dockerenv ]]; then
    php artisan system:check-lts || {
        echo "Artisan command failed. Falling back to composer audit..."
        composer audit
    }
else
    ./vendor/bin/sail artisan system:check-lts || {
        echo "Artisan command failed. Falling back to composer audit..."
        ./vendor/bin/sail composer audit
    }
fi

echo "=== LTS Audit Complete ==="
