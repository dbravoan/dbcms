#!/usr/bin/env bash
set -Eeuo pipefail
: "${CMS_IMAGE:?An immutable registry image digest is required}"
[[ "$CMS_IMAGE" =~ @sha256:[a-f0-9]{64}$ ]] || { echo 'CMS_IMAGE must be pinned by digest.' >&2; exit 1; }
export CMS_IMAGE RUN_MIGRATIONS=false
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
app_dir="${VPS_DIR:-$(cd "$script_dir/.." && pwd)}"
control_dir="${VPS_RELEASE_DIR:-$app_dir}"
shared="$app_dir/shared"
project="${VPS_COMPOSE_PROJECT:-$(basename "$app_dir")}"
[[ "$project" =~ ^[a-z0-9][a-z0-9_-]*$ ]]
test ! -e "$shared/deployment-recovery-required" || { echo 'Review shared/deployment-recovery-required before retrying.' >&2; exit 1; }
docker image inspect "$CMS_IMAGE" >/dev/null 2>&1 || docker pull "$CMS_IMAGE"
docker run --rm --entrypoint php "$CMS_IMAGE" -r '
    $profile = json_decode(file_get_contents("cms-project.json"), true, 512, JSON_THROW_ON_ERROR);
    if (($profile["deployment"] ?? null) !== "docker") {
        fwrite(STDERR, "The image deployment profile must be docker.\n");
        exit(1);
    }
'
test -s "$shared/.env"
if ! grep -Eq '^CMS_BRAND=' "$shared/.env"; then export CMS_BRAND="${PROJECT_BRAND:-dbcms}"; fi
if ! grep -Eq '^CMS_THEME=' "$shared/.env"; then export CMS_THEME="${PROJECT_THEME:-db-cyber}"; fi
compose=(docker compose --project-name "$project" --env-file "$shared/.env" -f "$control_dir/docker-compose.prod.yml")
"${compose[@]}" config --quiet
previous_image=''
migration_started=false
app_started=false
maintenance=false
lock="$shared/.deployment-lock"
lock_owned=false
release_lock() {
    if $lock_owned && [ "$(cat "$lock/owner" 2>/dev/null || true)" = "$CMS_IMAGE" ]; then
        rm -f "$lock/owner"
        rmdir "$lock"
        lock_owned=false
    fi
}
fail_deployment() {
    local status=$?
    trap - ERR
    if $migration_started; then
        printf '%s\n' "$CMS_IMAGE" > "$shared/deployment-recovery-required"
        chmod 600 "$shared/deployment-recovery-required"
        if $maintenance; then "${compose[@]}" run --rm --no-deps --entrypoint php app artisan down --retry=15 --quiet || true; fi
        "${compose[@]}" stop app || true
        echo 'Migration started: application stopped for database review; image rollback requires an explicit recovery decision.' >&2
    elif $app_started && [ -n "$previous_image" ]; then
        export CMS_IMAGE="$previous_image"
        "${compose[@]}" up -d --no-build --wait app || true
    elif $app_started; then
        "${compose[@]}" stop app || true
    fi
    if ! $migration_started && $maintenance; then
        if $maintenance; then "${compose[@]}" exec -T app php artisan up --quiet; fi || true
    fi
    if ! $migration_started; then release_lock; fi
    exit "$status"
}
trap fail_deployment ERR
if ! mkdir -m 700 "$lock"; then
    echo 'Another deployment owns shared/.deployment-lock. Review its owner before removing an interrupted lock.' >&2
    exit 1
fi
printf '%s\n' "$CMS_IMAGE" > "$lock/owner"
lock_owned=true
previous_id="$("${compose[@]}" ps -q app)"
if [ -n "$previous_id" ]; then previous_image="$(docker inspect --format '{{.Config.Image}}' "$previous_id")"; fi
infrastructure=(mysql)
if "${compose[@]}" config --services | grep -qx redis; then
    infrastructure+=(redis)
fi
"${compose[@]}" up -d --wait "${infrastructure[@]}"
# A fresh volume stays uninstalled: the HTTPS web installer owns provisioning.
installed=false
if "${compose[@]}" run --rm --no-deps --entrypoint sh app -c 'test -f storage/installed'; then
    installed=true
else
    status=$?
    [ "$status" = 1 ] || false
fi
if $installed; then
    "${compose[@]}" run --rm --no-deps --entrypoint php app bin/runtime-safety.php passport
    maintenance_state="$("${compose[@]}" run --rm --no-deps --entrypoint php app bin/runtime-safety.php maintenance)"
    case "$maintenance_state" in
        active) ;; # Do not replace maintenance belonging to the operator.
        inactive)
            maintenance=true
            "${compose[@]}" run --rm --no-deps --entrypoint php app artisan down --retry=15 --quiet
            ;;
        *) echo 'Cannot determine the existing maintenance state.' >&2; false ;;
    esac
    migration_command=migrate
    if "${compose[@]}" run --rm --no-deps --entrypoint sh app -c 'test -f cms-project.json'; then
        migration_command=cms:project:migrate
    else
        status=$?
        [ "$status" = 1 ] || false
    fi
    migration_started=true
    printf '%s\n' "$CMS_IMAGE" > "$shared/deployment-recovery-required"
    chmod 600 "$shared/deployment-recovery-required"
    "${compose[@]}" run --rm --no-deps app php artisan "$migration_command" --force --no-interaction
fi
app_started=true
"${compose[@]}" up -d --no-build --wait --wait-timeout 120 app
container_id="$("${compose[@]}" ps -q app)"
test "$(docker inspect --format '{{.State.Health.Status}}' "$container_id")" = healthy
"${compose[@]}" exec -T app curl -fsS http://127.0.0.1/up >/dev/null
if $installed; then
    if "${compose[@]}" exec -T app sh -c 'test -f cms-project.json && test -f resources/updates/project-update.json'; then
        "${compose[@]}" exec -T app php artisan cms:project:reconcile --no-interaction
    fi
    "${compose[@]}" exec -T app php artisan queue:restart
    if $maintenance; then "${compose[@]}" exec -T app php artisan up --quiet; fi
fi
printf '%s\n' "$CMS_IMAGE" > "$shared/current-image"
rm -f "$shared/deployment-recovery-required"
release_lock
trap - ERR
printf 'Promoted %s. Complete the web installer if this is the first deployment.\n' "$CMS_IMAGE"
