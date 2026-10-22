#!/usr/bin/env bash
set -Eeuo pipefail
: "${VPS_DIR:?VPS application directory is required}"
: "${RELEASE_NAME:?Release name is required}"
profile="${VPS_DEPLOYMENT_PROFILE:-rsync}"
phase="${2:-deploy}"
[[ "$phase" =~ ^(deploy|prepare|finalize|abort)$ ]]
[[ "$profile" =~ ^(github-actions|rsync|ansistrano)$ ]]
[[ "$VPS_DIR" =~ ^/[a-zA-Z0-9_./-]+$ ]] && [ "$VPS_DIR" != / ]
[[ "$RELEASE_NAME" =~ ^release-[a-zA-Z0-9-]+$ ]]
app_dir="$(cd "$VPS_DIR" && pwd -P)"
[ "$app_dir" != / ]
release_dir="$(cd "${1:?Transferred release directory is required}" && pwd -P)"
[ "$release_dir" = "$app_dir/releases/$RELEASE_NAME" ]
current="$app_dir/current"
shared="$app_dir/shared"
state="$release_dir/.deployment-state"
marker="$shared/deployment-recovery-required"
lock="$shared/.deployment-lock"
lock_owned=false
previous=''
promoted=false
maintenance=false
migration_started=false
release_lock() {
    if $lock_owned && [ "$(cat "$lock/owner" 2>/dev/null || true)" = "$RELEASE_NAME" ]; then
        rm -f "$lock/owner"
        rmdir "$lock"
        lock_owned=false
    fi
}
load_state() {
    if [ -f "$state/previous" ]; then
        previous="$(cat "$state/previous")"
        if [ -n "$previous" ]; then [[ "$previous" == "$app_dir/releases/release-"* ]] && [ -d "$previous" ]; fi
        [ ! -f "$state/maintenance" ] || maintenance=true
        [ ! -f "$state/migration-started" ] || migration_started=true
    fi
    if [ "$(readlink -f "$current" 2>/dev/null || true)" = "$release_dir" ]; then promoted=true; fi
}
run_safety() {
    "${VPS_PHP_BINARY:-/usr/bin/php}" "$release_dir/bin/runtime-safety.php" "$1" "$2"
}
run_artisan() {
    local target="$1"
    shift
    VPS_RELEASE_DIR="$target" VPS_PHP_BINARY="${VPS_PHP_BINARY:-/usr/bin/php}" \
        bash "$release_dir/bin/vps-artisan.sh" "$@"
}
reload_fpm() {
    if [ -n "${VPS_FPM_SERVICE:-}" ]; then
        [[ "$VPS_FPM_SERVICE" =~ ^[a-zA-Z0-9@_.-]+$ ]]
        sudo -n systemctl reload "$VPS_FPM_SERVICE"
        return
    fi
    for service in plesk-php83-fpm php8.3-fpm php-fpm; do
        if sudo -n systemctl reload "$service" 2>/dev/null; then return 0; fi
    done
    echo 'Cannot reload PHP-FPM; configure VPS_FPM_SERVICE and sudo rights.' >&2
    return 1
}
fail_deployment() {
    local status=$?
    trap - ERR
    load_state || true
    if $migration_started; then
        printf '%s\n' "$RELEASE_NAME" > "$marker"
        chmod 600 "$marker"
        if $maintenance; then run_artisan "$release_dir" down --retry=15 --quiet || true; fi
        echo 'Deployment failed after migration started. Maintenance remains enabled; inspect the database before choosing code recovery. No automatic rollback was attempted.' >&2
    else
        if $promoted && [ -n "$previous" ]; then
            ln -sfn "$previous" "$current.rollback"
            mv -fT "$current.rollback" "$current"
            reload_fpm || true
        elif $promoted; then
            rm -f "$current"
        fi
        if $maintenance && [ -n "$previous" ]; then run_artisan "$previous" up --quiet || true; fi
        release_lock
        echo 'Deployment failed before migrations; previous code restored when available.' >&2
    fi
    exit "$status"
}
if [ -e "$marker" ]; then
    if [[ "$phase" =~ ^(finalize|abort)$ ]] && [ -f "$state/migration-started" ] && [ "$(cat "$marker")" = "$RELEASE_NAME" ]; then :; else
        echo 'Review the failed migration/deployment and remove shared/deployment-recovery-required before retrying.' >&2
        exit 1
    fi
fi
# Validate the selected native profile and PHP runtime before modifying shared data.
if [ -n "${VPS_FPM_SERVICE:-}" ]; then [[ "$VPS_FPM_SERVICE" =~ ^[a-zA-Z0-9@_.-]+$ ]]; fi
if [ "$phase" != abort ]; then bash "$release_dir/bin/vps-preflight.sh" "$release_dir" "$profile"; fi
if [[ "$phase" =~ ^(deploy|prepare)$ ]]; then
    mkdir -p "$shared"
    if ! mkdir -m 700 "$lock"; then
        echo 'Another deployment owns shared/.deployment-lock. Review its owner before removing an interrupted lock.' >&2
        exit 1
    fi
    printf '%s\n' "$RELEASE_NAME" > "$lock/owner"
else
    test -d "$lock" && [ "$(cat "$lock/owner" 2>/dev/null || true)" = "$RELEASE_NAME" ] || {
        echo 'This deployment does not own shared/.deployment-lock.' >&2
        exit 1
    }
fi
lock_owned=true
trap fail_deployment ERR
if [ -e "$current" ] && [ ! -L "$current" ]; then
    echo 'current must be a release symlink; refusing to replace a directory.' >&2
    false
fi
if [[ "$phase" =~ ^(deploy|prepare)$ ]]; then
    test ! -e "$state"
    for required in artisan vendor/autoload.php public/build/manifest.json bin/vps-artisan.sh bin/runtime-safety.php; do test -f "$release_dir/$required"; done
    previous="$(readlink -f "$current" 2>/dev/null || true)"
    [ -d "$previous" ] || previous=''
    mkdir -p "$state" "$shared" "$app_dir/releases"
    chmod 700 "$state"
    printf '%s\n' "$previous" > "$state/previous"
    for location in storage uploads; do
        if [ ! -e "$shared/$location" ]; then
            if [ -d "$app_dir/$location" ]; then ln -s "$app_dir/$location" "$shared/$location"; else mkdir -p "$shared/$location"; fi
        fi
    done
    if [ ! -e "$shared/.env" ] && [ -f "$app_dir/.env" ]; then ln -s "$app_dir/.env" "$shared/.env"; fi
    test -s "$shared/.env"
    chmod 600 "$(readlink -f "$shared/.env")"
    mkdir -p "$shared/storage/app/public" "$shared/storage/framework/cache/data" \
        "$shared/storage/framework/sessions" "$shared/storage/framework/views" "$shared/storage/logs"
    rm -rf "$release_dir/storage" "$release_dir/public/uploads" "$release_dir/public/storage" "$release_dir/.env"
    ln -s "$shared/storage" "$release_dir/storage"
    ln -s "$shared/uploads" "$release_dir/public/uploads"
    ln -s "$shared/storage/app/public" "$release_dir/public/storage"
    ln -s "$shared/.env" "$release_dir/.env"
    mkdir -p "$release_dir/bootstrap/cache"
    find "$release_dir/bootstrap/cache" -type f -name '*.php' -delete
    chmod 775 "$release_dir/bootstrap/cache"
    if [ -f "$shared/storage/oauth-private.key" ]; then chmod 600 "$shared/storage/oauth-private.key"; fi
    if [ -f "$shared/storage/oauth-public.key" ]; then chmod 644 "$shared/storage/oauth-public.key"; fi
    printf '%s\n' "${VPS_PHP_BINARY:-/usr/bin/php}" > "$release_dir/.php-binary"
fi
health_url="${VPS_HEALTH_URL:-$(sed -n 's/^APP_URL=//p' "$shared/.env" | head -1 | tr -d '\r' | sed "s/^[\"']//; s/[\"']$//")}"
[[ "$health_url" =~ ^https?://[^[:space:]]+$ ]]
if [[ "$phase" =~ ^(deploy|prepare)$ ]]; then
    if [ -f "$shared/storage/installed" ]; then
        run_safety passport "$release_dir"
        maintenance_target="${previous:-$release_dir}"
        maintenance_state="$(run_safety maintenance "$maintenance_target")"
        case "$maintenance_state" in
            active) ;; # Preserve the existing payload, bypass secret and owner.
            inactive)
                maintenance=true
                touch "$state/maintenance"
                run_artisan "$maintenance_target" down --retry=15 --quiet
                ;;
            *) echo 'Cannot determine the existing maintenance state.' >&2; false ;;
        esac
        # Persist before executing migrations: a disconnected deployer must leave recovery blocked too.
        migration_started=true
        touch "$state/migration-started"
        printf '%s\n' "$RELEASE_NAME" > "$marker"
        chmod 600 "$marker"
        run_artisan "$release_dir" cms:project:migrate --force --no-interaction
        run_artisan "$release_dir" config:cache
        run_artisan "$release_dir" route:cache
        run_artisan "$release_dir" view:cache
        run_artisan "$release_dir" cms:cache:purge
    fi
    touch "$state/prepared"
fi
if [ "$phase" = prepare ]; then trap - ERR; exit 0; fi
load_state
test -f "$state/prepared"
if [ "$phase" = abort ]; then false; fi
if [ "$profile" = ansistrano ]; then
    test "$(readlink -f "$app_dir/.ansistrano-next")" = "$release_dir"
    mv -fT "$app_dir/.ansistrano-next" "$current"
else
    ln -sfn "$release_dir" "$current.next"
    mv -fT "$current.next" "$current"
fi
promoted=true
reload_fpm
status="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --connect-timeout 5 --max-time 20 "${health_url%/}/up")"
test "$status" = 200
if [ -f "$shared/storage/installed" ]; then
    if [ -f "$release_dir/resources/updates/project-update.json" ]; then run_artisan "$release_dir" cms:project:reconcile --no-interaction; fi
    run_artisan "$release_dir" queue:restart
    if $maintenance; then run_artisan "$release_dir" up --quiet; fi
fi
rm -f "$marker"
rm -rf "$state"
release_lock
trap - ERR
printf 'Promoted %s. Complete the web installer if this is the first deployment.\n' "$RELEASE_NAME"
