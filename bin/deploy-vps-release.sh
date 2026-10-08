#!/usr/bin/env bash
set -Eeuo pipefail
: "${VPS_DIR:?Existing VPS/Plesk application directory is required}"
: "${RELEASE_NAME:?Release name is required}"
: "${VPS_CLI_IMAGE:?Verified PHP CLI image is required}"
archive="${1:?Built release archive is required}"
[[ "$VPS_DIR" =~ ^/[a-zA-Z0-9_./-]+$ ]] && [ "$VPS_DIR" != / ]
[[ "$RELEASE_NAME" =~ ^release-[a-zA-Z0-9-]+$ ]]
test -d "$VPS_DIR"
app_dir="$(cd "$VPS_DIR" && pwd -P)"
release_dir="$app_dir/releases/$RELEASE_NAME"
current="$app_dir/current"
shared="$app_dir/shared"
previous="$(readlink -f "$current" 2>/dev/null || true)"
[ -d "$previous" ] || previous=''
promoted=false
maintenance=false
run_artisan() {
    local target="$1"
    shift
    VPS_APP_DIR="$app_dir" VPS_RELEASE_DIR="$target" VPS_CLI_IMAGE="$VPS_CLI_IMAGE" \
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
rollback() {
    local status=$?
    trap - ERR
    if $promoted && [ -n "$previous" ] && [ -d "$previous" ]; then
        ln -sfn "$previous" "$current.rollback"
        mv -fT "$current.rollback" "$current"
        reload_fpm || true
    elif $promoted && [ -z "$previous" ]; then
        rm -f "$current"
    fi
    if $maintenance && [ -n "$previous" ] && [ -f "$previous/artisan" ]; then
        run_artisan "$previous" up --quiet || true
    fi
    echo 'Deployment failed. Previous code restored when available; database migrations were NOT rolled back.' >&2
    exit "$status"
}
trap rollback ERR
test ! -e "$release_dir"
if [ -e "$current" ] && [ ! -L "$current" ]; then
    echo 'current must be the existing release symlink; refusing to replace a directory.' >&2
    exit 1
fi
mkdir -p "$shared" "$app_dir/releases" "$release_dir"
tar -xzf "$archive" -C "$release_dir"
test -f "$release_dir/artisan"
test -f "$release_dir/vendor/autoload.php"
test -f "$release_dir/public/build/manifest.json"
test -f "$release_dir/bin/vps-artisan.sh"
# Adopt legacy persistent locations through links; never move or delete their data.
for location in storage uploads; do
    if [ ! -e "$shared/$location" ]; then
        if [ -d "$app_dir/$location" ]; then
            ln -s "$app_dir/$location" "$shared/$location"
        else
            mkdir -p "$shared/$location"
        fi
    fi
done
if [ ! -e "$shared/.env" ] && [ -f "$app_dir/.env" ]; then ln -s "$app_dir/.env" "$shared/.env"; fi
test -s "$shared/.env"
chmod 600 "$(readlink -f "$shared/.env")"
mkdir -p "$shared/storage/app/public" "$shared/storage/framework/cache/data" \
    "$shared/storage/framework/sessions" "$shared/storage/framework/views" "$shared/storage/logs"
rm -rf "$release_dir/storage" "$release_dir/public/uploads" "$release_dir/public/storage"
ln -s "$shared/storage" "$release_dir/storage"
ln -s "$shared/uploads" "$release_dir/public/uploads"
ln -s "$shared/storage/app/public" "$release_dir/public/storage"
ln -s "$shared/.env" "$release_dir/.env"
mkdir -p "$release_dir/bootstrap/cache"
find "$release_dir/bootstrap/cache" -type f -name '*.php' -delete
chmod 775 "$release_dir/bootstrap/cache"
if [ -f "$shared/storage/oauth-private.key" ]; then chmod 600 "$shared/storage/oauth-private.key"; fi
if [ -f "$shared/storage/oauth-public.key" ]; then chmod 644 "$shared/storage/oauth-public.key"; fi
health_url="${VPS_HEALTH_URL:-$(sed -n 's/^APP_URL=//p' "$shared/.env" | head -1 | tr -d '\r' | sed "s/^[\"']//; s/[\"']$//")}"
[[ "$health_url" =~ ^https?://[^[:space:]]+$ ]]
docker image inspect "$VPS_CLI_IMAGE" >/dev/null
if [ -n "$previous" ] && [ -f "$previous/artisan" ]; then
    maintenance=true
    run_artisan "$previous" down --retry=15 --quiet
fi
# Existing PHP-FPM/Plesk continues serving the website; only the CLI uses Docker.
run_artisan "$release_dir" migrate --force --no-interaction
if [ ! -f "$shared/storage/oauth-private.key" ]; then run_artisan "$release_dir" passport:keys --no-interaction; fi
run_artisan "$release_dir" config:cache
run_artisan "$release_dir" route:cache
run_artisan "$release_dir" view:cache
run_artisan "$release_dir" cms:cache:purge
ln -sfn "$release_dir" "$current.next"
mv -fT "$current.next" "$current"
promoted=true
reload_fpm
run_artisan "$release_dir" queue:restart
run_artisan "$release_dir" up --quiet
status="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --connect-timeout 5 --max-time 20 "${health_url%/}/up")"
test "$status" = 200
trap - ERR
printf 'Promoted VPS/Plesk release %s; existing hosting retained.\n' "$RELEASE_NAME"
