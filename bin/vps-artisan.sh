#!/usr/bin/env bash
set -euo pipefail
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
project_root="$(cd "$script_dir/.." && pwd)"
release_dir="${VPS_RELEASE_DIR:-$project_root}"
php_binary="${VPS_PHP_BINARY:-}"
if [ -z "$php_binary" ] && [ -f "$release_dir/.php-binary" ]; then php_binary="$(cat "$release_dir/.php-binary")"; fi
php_binary="${php_binary:-/usr/bin/php}"
[[ "$php_binary" =~ ^/[a-zA-Z0-9_./-]+$ ]] && test -x "$php_binary" || { echo 'VPS_PHP_BINARY must be an executable absolute PHP CLI path.' >&2; exit 1; }
test -f "$release_dir/artisan"
cd "$release_dir"
exec "$php_binary" "$release_dir/artisan" "$@"
