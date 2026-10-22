#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

# Support windows and dependency advisories are independent checks.
support_status=0
audit_status=0
if [[ -f /.dockerenv ]]; then
    php artisan system:check-framework-support "$@" || support_status=$?
    composer audit --locked || audit_status=$?
else
    ./vendor/bin/sail artisan system:check-framework-support "$@" || support_status=$?
    ./vendor/bin/sail composer audit --locked || audit_status=$?
fi
if (( support_status != 0 )); then exit "$support_status"; fi
exit "$audit_status"
