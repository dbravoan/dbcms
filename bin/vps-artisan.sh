#!/usr/bin/env bash
set -euo pipefail
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
project_root="$(cd "$script_dir/.." && pwd)"
release_dir="${VPS_RELEASE_DIR:-$project_root}"
app_dir="${VPS_APP_DIR:-$project_root}"
if [ -z "${VPS_APP_DIR:-}" ] && [ "$(basename "$(dirname "$project_root")")" = releases ]; then
    app_dir="$(dirname "$(dirname "$project_root")")"
fi
if [ -z "${VPS_CLI_IMAGE:-}" ]; then
    version="$(tr -d '[:space:]' < "$release_dir/VERSION")"
    VPS_CLI_IMAGE="ghcr.io/dbravoan/dbcms:$version"
fi
test -f "$release_dir/artisan"
command -v docker >/dev/null
# Preserve absolute current/shared links and existing localhost database connections.
exec docker run --rm --network host --user "$(id -u):$(id -g)" \
    --volume "$app_dir:$app_dir" --workdir "$release_dir" \
    --entrypoint php "$VPS_CLI_IMAGE" "$release_dir/artisan" "$@"
