#!/usr/bin/env bash
set -euo pipefail

# Guard Script: Blocks execution of PHP/Composer/Artisan on the host OS.

if [ -f "/.dockerenv" ] || [ ! -d ".git" ]; then
    # Containers and standalone consumer packages are supported. Repository CI/deploy uses Docker.
    exit 0
fi

echo "======================================================================" >&2
echo " [SECURITY & REPRODUCIBILITY ERROR]: ZERO-HOST PHP POLICY VIOLATION" >&2
echo "======================================================================" >&2
echo " Direct execution of PHP, Composer, or Artisan on your host machine is" >&2
echo " strictly prohibited to prevent version skew, environment pollution, and" >&2
echo " security escapes." >&2
echo "" >&2
echo " Please start Laravel Sail and use its official commands:" >&2
echo "   ./vendor/bin/sail up -d" >&2
echo "   e.g. ./vendor/bin/sail artisan migrate" >&2
echo "   e.g. ./vendor/bin/sail pest" >&2
echo "   e.g. make test" >&2
echo "======================================================================" >&2
exit 1
