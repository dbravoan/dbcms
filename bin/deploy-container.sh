#!/usr/bin/env bash
set -euo pipefail
: "${CMS_IMAGE:?An immutable image tag is required}"
export CMS_IMAGE RUN_MIGRATIONS=false
compose=(docker compose -f docker-compose.prod.yml)
"${compose[@]}" config --quiet
previous_id="$("${compose[@]}" ps -q app)"
previous_image=""
if [ -n "$previous_id" ]; then
    previous_image="$(docker inspect --format '{{.Config.Image}}' "$previous_id")"
fi
rollback() {
    if [ -n "$previous_image" ]; then
        export CMS_IMAGE="$previous_image"
        "${compose[@]}" up -d --no-build app
    fi
    echo 'Deployment failed; previous application image restored when available. Database changes require separate review.' >&2
}
trap rollback ERR
# Start dependencies without replacing the healthy application yet.
docker load -i image.tar.gz
"${compose[@]}" up -d --wait mysql redis
# A single migration job precedes application replacement.
"${compose[@]}" run --rm --no-deps app php artisan migrate --force --no-interaction
"${compose[@]}" up -d --no-build --wait --wait-timeout 120 app
container_id="$("${compose[@]}" ps -q app)"
test "$(docker inspect --format '{{.State.Health.Status}}' "$container_id")" = healthy
"${compose[@]}" exec -T app curl -fsS http://127.0.0.1/up >/dev/null
trap - ERR
rm -f image.tar.gz
printf 'Promoted %s\n' "$CMS_IMAGE"
