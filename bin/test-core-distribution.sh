#!/usr/bin/env bash
set -euo pipefail
if [ ! -f /.dockerenv ] || [ "${DBCMS_TEST_ISOLATED:-}" != 1 ]; then
    echo 'Core distribution verification requires an explicitly isolated Docker runtime.' >&2
    exit 1
fi
cd "$(dirname "${BASH_SOURCE[0]}")/.."
php tests/Release/core-distribution.php
