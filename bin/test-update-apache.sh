#!/usr/bin/env bash
set -euo pipefail
if [ ! -f /.dockerenv ]; then
    echo 'Run this HTTP boundary check inside the Docker CI container.' >&2
    exit 1
fi
command -v httpd >/dev/null || { echo 'The CI image needs apache2 and apache2-proxy.' >&2; exit 1; }
command -v php-fpm >/dev/null
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APACHE_FIXTURE="$(mktemp -d /tmp/dbcms-update-apache.XXXXXXXX)"
APACHE_PID=''
FPM_PID=''
cleanup() {
    if [ -n "$APACHE_PID" ]; then kill "$APACHE_PID" 2>/dev/null || true; wait "$APACHE_PID" 2>/dev/null || true; fi
    if [ -n "$FPM_PID" ]; then kill "$FPM_PID" 2>/dev/null || true; wait "$FPM_PID" 2>/dev/null || true; fi
    find "$APACHE_FIXTURE" -depth -delete
}
trap cleanup EXIT INT TERM
chmod 755 "$APACHE_FIXTURE"
mkdir -p "$APACHE_FIXTURE/site/public" "$APACHE_FIXTURE/site/vendor" "$APACHE_FIXTURE/site/bootstrap" "$APACHE_FIXTURE/site/storage/app/private/core-updates/11111111-1111-4111-8111-111111111111/runtime"
cp "$PROJECT_ROOT/.htaccess" "$APACHE_FIXTURE/site/.htaccess"
cp "$PROJECT_ROOT/public/.htaccess" "$APACHE_FIXTURE/site/public/.htaccess"
cp "$PROJECT_ROOT/public/index.php" "$PROJECT_ROOT/public/update.php" "$APACHE_FIXTURE/site/public/"
printf '%s' 'APP_KEY=APACHE-UPDATER-FIXTURE-NOT-A-SECRET' > "$APACHE_FIXTURE/site/.env"
printf '%s' '<?php throw new RuntimeException("Framework must not load during maintenance");' > "$APACHE_FIXTURE/site/vendor/autoload.php"
printf '%s' '<?php throw new RuntimeException("Recovery must use the protected snapshot");' > "$APACHE_FIXTURE/site/bootstrap/UpdateRuntime.php"
APACHE_RUN="$APACHE_FIXTURE/site/storage/app/private/core-updates/11111111-1111-4111-8111-111111111111"
cp "$PROJECT_ROOT/bootstrap/UpdateRuntime.php" "$APACHE_RUN/runtime/UpdateRuntime.php"
for file in UpdatePathPolicy ReleaseManifest SignedManifestVerifier FileUpdateEngine UpdateBarrier; do cp "$PROJECT_ROOT/app/Core/Updates/$file.php" "$APACHE_RUN/runtime/$file.php"; done
printf '%s' '{"run_id":"11111111-1111-4111-8111-111111111111"}' > "$APACHE_FIXTURE/site/storage/app/private/core-updates/active.json"
printf '%s' '{"id":"11111111-1111-4111-8111-111111111111","actor_id":1,"status":"files","mode":"runtime","version":"1.1.0","recovery":{"token_hash":"unavailable-fixture-token","cookie_expires_at":4102444800}}' > "$APACHE_RUN/run.json"
find "$APACHE_FIXTURE/site" -type d -exec chmod 755 {} +
# Fixture data is invented and contains no real credentials. PHP-FPM runs unprivileged.
find "$APACHE_FIXTURE/site" -type f -exec chmod 644 {} +
new_port() { php -r '$s=stream_socket_server("tcp://127.0.0.1:0"); $a=stream_socket_get_name($s,false); fclose($s); echo substr(strrchr($a,":"),1);'; }
FPM_PORT="$(new_port)"
APACHE_PORT="$(new_port)"
cat > "$APACHE_FIXTURE/fpm.conf" <<EOF
[global]
daemonize = no
error_log = $APACHE_FIXTURE/fpm.log
[www]
listen = 127.0.0.1:$FPM_PORT
user = nobody
group = nobody
pm = static
pm.max_children = 2
catch_workers_output = yes
clear_env = no
php_admin_flag[display_errors] = off
php_admin_flag[log_errors] = on
EOF
php-fpm -y "$APACHE_FIXTURE/fpm.conf" -F > "$APACHE_FIXTURE/fpm-stdout.log" 2>&1 &
FPM_PID=$!
for attempt in $(seq 1 50); do
    if php -r '$s = @stream_socket_client("tcp://127.0.0.1:".$argv[1], $errno, $error, 0.05); if (!is_resource($s)) exit(1); fclose($s);' "$FPM_PORT"; then break; fi
    if ! kill -0 "$FPM_PID" 2>/dev/null; then cat "$APACHE_FIXTURE/fpm-stdout.log" >&2; exit 1; fi
    sleep 0.1
done
assert_http() {
    local request_path="$1" expected="$2" actual
    actual="$(curl -sS --max-time 10 -H 'Accept: application/json' -D "$APACHE_FIXTURE/headers" -o "$APACHE_FIXTURE/body" -w '%{http_code}' "http://127.0.0.1:$APACHE_PORT$request_path")"
    if [ "$actual" != "$expected" ]; then
        echo "Apache updater boundary failed: $request_path expected $expected, got $actual." >&2
        cat "$APACHE_FIXTURE/body" >&2
        cat "$APACHE_FIXTURE/apache-error.log" >&2 || true
        exit 1
    fi
    if grep -Eq 'APACHE-UPDATER-FIXTURE|Framework must not load|RuntimeException|Stack trace|vendor/autoload' "$APACHE_FIXTURE/body"; then
        echo 'Apache exposed private content or framework failure during update.' >&2
        exit 1
    fi
}
for document_root in "$APACHE_FIXTURE/site" "$APACHE_FIXTURE/site/public"; do
    cat > "$APACHE_FIXTURE/httpd.conf" <<EOF
ServerRoot /usr/lib/apache2
PidFile $APACHE_FIXTURE/httpd.pid
Listen 127.0.0.1:$APACHE_PORT
ServerName localhost
LoadModule mpm_event_module /usr/lib/apache2/mod_mpm_event.so
LoadModule unixd_module /usr/lib/apache2/mod_unixd.so
LoadModule authz_core_module /usr/lib/apache2/mod_authz_core.so
LoadModule authz_host_module /usr/lib/apache2/mod_authz_host.so
LoadModule dir_module /usr/lib/apache2/mod_dir.so
LoadModule mime_module /usr/lib/apache2/mod_mime.so
LoadModule rewrite_module /usr/lib/apache2/mod_rewrite.so
LoadModule proxy_module /usr/lib/apache2/mod_proxy.so
LoadModule proxy_fcgi_module /usr/lib/apache2/mod_proxy_fcgi.so
User nobody
Group nobody
ErrorLog $APACHE_FIXTURE/apache-error.log
LogLevel warn
TypesConfig /etc/apache2/mime.types
DocumentRoot "$document_root"
DirectoryIndex index.php
<Directory />
    Require all denied
</Directory>
<Directory "$APACHE_FIXTURE/site">
    AllowOverride All
    Options +FollowSymLinks -Indexes
    Require all granted
</Directory>
<FilesMatch "\\.php$">
    SetHandler "proxy:fcgi://127.0.0.1:$FPM_PORT"
</FilesMatch>
EOF
    httpd -f "$APACHE_FIXTURE/httpd.conf" -DFOREGROUND > "$APACHE_FIXTURE/apache-stdout.log" 2>&1 &
    APACHE_PID=$!
    for attempt in $(seq 1 50); do
        if curl -sS -o /dev/null --max-time 1 "http://127.0.0.1:$APACHE_PORT/" 2>/dev/null; then break; fi
        if ! kill -0 "$APACHE_PID" 2>/dev/null; then cat "$APACHE_FIXTURE/apache-stdout.log" >&2; exit 1; fi
        sleep 0.1
    done
    assert_http '/' 503
    grep -qi 'cache-control:.*no-store' "$APACHE_FIXTURE/headers"
    assert_http '/control/updates' 503
    assert_http '/up' 503
    assert_http '/update.php' 403
    if [ "$document_root" = "$APACHE_FIXTURE/site" ]; then
        assert_http '/.env' 403
        assert_http '/storage/app/private/core-updates/active.json' 403
        assert_http '/vendor/autoload.php' 403
    else
        assert_http '/.env' 503
        assert_http '/storage/app/private/core-updates/active.json' 503
        assert_http '/vendor/autoload.php' 503
    fi
    kill "$APACHE_PID"
    wait "$APACHE_PID" 2>/dev/null || true
    APACHE_PID=''
    echo "Apache update boundaries passed for $(basename "$document_root") document root."
done
