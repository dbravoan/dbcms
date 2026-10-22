#!/usr/bin/env bash
set -euo pipefail

# ==============================================================================
# dbCMS Standalone Release Package Builder
# ==============================================================================
# Produces self-contained, WordPress-style distribution archives:
#   - dist/dbcms-installer-v<VERSION>.zip
#   - dist/dbcms-v<VERSION>.tar.gz
#   - dist/checksums.txt (SHA256)
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

if [ -f "$ROOT_DIR/cms-project.json" ] && [ "${DBCMS_DELIVERY_RUNTIME_ONLY:-0}" != 1 ]; then
    echo 'Derived projects prepare deployment runtimes locally; standalone dbCMS archives are product-only.' >&2
    exit 1
fi

VERSION=""
SOURCE_REF=""
OUTPUT_DIR=""
RUNTIME_OUTPUT=""
for arg in "$@"; do
    case "$arg" in
        --version=*)
            [[ -z "$VERSION" && -n "${arg#*=}" ]] || { echo 'Specify one nonempty --version.' >&2; exit 64; }
            VERSION="${arg#*=}"
            ;;
        --source-ref=*)
            [[ -z "$SOURCE_REF" ]] || { echo 'Specify --source-ref only once.' >&2; exit 64; }
            SOURCE_REF="${arg#*=}"
            [[ "$SOURCE_REF" =~ ^[a-f0-9]{40}$ ]] || { echo '--source-ref must be a full 40-character commit SHA.' >&2; exit 64; }
            ;;
        --output=*) OUTPUT_DIR="${arg#*=}" ;;
        --runtime-output=*) RUNTIME_OUTPUT="${arg#*=}" ;;
        --help|-h)
            echo 'Usage: bin/build-release-zip.sh [--version=X.Y.Z] [--source-ref=<40-character commit SHA>]'
            echo 'Source defaults to HEAD. The requested version must match VERSION in that exact commit.'
            exit
            ;;
        *)
            echo "Unknown build option: $arg" >&2
            exit 64
            ;;
    esac
done
VERSION="${VERSION#v}"
if [[ -n "$VERSION" && ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+([+-][a-zA-Z0-9.-]+)?$ ]]; then
    echo 'Invalid release version.' >&2
    exit 64
fi

# Packaging uses exactly the container runtime used by CI.
if [ ! -f /.dockerenv ]; then
    cd "$ROOT_DIR"
    mkdir -p "$ROOT_DIR/dist"
    signing_args=()
    if [ -n "${DBCMS_UPDATE_SIGNING_KEY_FILE:-}" ]; then
        signing_args=(--volume "${DBCMS_UPDATE_SIGNING_KEY_FILE}:/run/secrets/updates-signing.pem:ro" --env DBCMS_UPDATE_SIGNING_KEY_FILE=/run/secrets/updates-signing.pem --env DBCMS_UPDATE_SIGNING_KEY_ID="${DBCMS_UPDATE_SIGNING_KEY_ID:-releases-2026}")
    fi
    exec docker compose -f docker/ci/compose.yml run --build --rm \
        --volume "${ROOT_DIR}:/var/www/html:ro" \
        --volume "${ROOT_DIR}/dist:/var/www/html/dist:rw" \
        "${signing_args[@]}" app bash bin/build-release-zip.sh "$@"
fi

if [[ -n "$SOURCE_REF" ]]; then
    [[ "$(git -C "$ROOT_DIR" cat-file -t "$SOURCE_REF" 2>/dev/null || true)" == commit ]] || { echo '--source-ref must identify an existing commit object.' >&2; exit 64; }
    SOURCE_SHA="$SOURCE_REF"
else
    SOURCE_SHA="$(git -C "$ROOT_DIR" rev-parse --verify 'HEAD^{commit}')" || { echo 'Release packaging requires a Git commit.' >&2; exit 64; }
fi
SOURCE_VERSION="$(git -C "$ROOT_DIR" show "$SOURCE_SHA:VERSION")" || { echo 'The selected source commit has no VERSION file.' >&2; exit 64; }
SOURCE_VERSION="${SOURCE_VERSION%$'\r'}"
[[ "$SOURCE_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+([+-][a-zA-Z0-9.-]+)?$ ]] || { echo 'The selected source VERSION is invalid.' >&2; exit 64; }
if [[ -z "$VERSION" ]]; then
    VERSION="$SOURCE_VERSION"
elif [[ "$VERSION" != "$SOURCE_VERSION" ]]; then
    echo "Requested version $VERSION does not match selected source VERSION $SOURCE_VERSION." >&2
    exit 64
fi

echo "======================================================================"
echo " Building dbCMS Standalone Release Archive: v${VERSION} ($SOURCE_SHA)"
echo "======================================================================"

DIST_DIR="${OUTPUT_DIR:-${ROOT_DIR}/dist}"
mkdir -p "${DIST_DIR}"

BUILD_TMP="$(mktemp -d /tmp/dbcms-build-XXXXXX)"
SMOKE_TMP="$(mktemp -d /tmp/dbcms-smoke-XXXXXX)"

cleanup() {
    if [ -n "${SMOKE_PID:-}" ]; then
        kill "$SMOKE_PID" 2>/dev/null || true
        wait "$SMOKE_PID" 2>/dev/null || true
    fi
    rm -rf "${BUILD_TMP}" "${SMOKE_TMP}"
}
trap cleanup EXIT

echo "--> 1. Exporting clean source tree with git archive..."
cd "${ROOT_DIR}"
git archive "$SOURCE_SHA" | tar -x -C "${BUILD_TMP}"
# Git does not retain empty directories. Composer hooks and cache sanitization
# both require these paths even when no runtime placeholders are tracked.
mkdir -p "${BUILD_TMP}/storage/app/public" \
         "${BUILD_TMP}/storage/framework/cache/data" \
         "${BUILD_TMP}/storage/framework/sessions" \
         "${BUILD_TMP}/storage/framework/views" \
         "${BUILD_TMP}/storage/logs" \
         "${BUILD_TMP}/bootstrap/cache"
# Composer source exports retain development tooling; only runtime packages prune it.
rm -rf "$BUILD_TMP/.github" "$BUILD_TMP/docs" "$BUILD_TMP/specs" "$BUILD_TMP/tests" "$BUILD_TMP/docker"
rm -f "$BUILD_TMP/phpunit.xml" "$BUILD_TMP/phpstan.neon" "$BUILD_TMP/pint.json" "$BUILD_TMP/docker-compose.yml" "$BUILD_TMP/Makefile" "$BUILD_TMP/CONTRIBUTING.md"
# Tracked development manifests must never be shipped as runtime configuration.
find "$BUILD_TMP/bootstrap/cache" -type f -name '*.php' -delete
rm -f "$BUILD_TMP/mcp/mcp_config.json" "$BUILD_TMP/.mcp.json"
find "$BUILD_TMP" -maxdepth 1 -type f -name '.env*' \
    ! -name '.env.example' \
    ! -name '.env.production.example' \
    ! -name '.env.installation.example' \
    -delete

echo "--> 2. Installing production PHP dependencies (no-dev, no-classmap-authoritative)..."
cd "${BUILD_TMP}"
# Ensure environment allows composer install during packaging


composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction \
    --no-progress

echo "--> 3. Compiling production front-end assets..."
if [ -n "${DBCMS_PREPARED_ASSETS:-}" ]; then
    test -f "$DBCMS_PREPARED_ASSETS/manifest.json"
    mkdir -p public/build
    cp -a "$DBCMS_PREPARED_ASSETS/." public/build/
else
    npm ci --silent
    npm run build
fi

if [ ! -f "${BUILD_TMP}/public/build/manifest.json" ]; then
    echo "ERROR: Front-end manifest missing at public/build/manifest.json" >&2
    exit 1
fi

echo "--> 4. Pruning development artifacts and securing runtime directories..."
rm -rf "${BUILD_TMP}/node_modules"

chmod -R 775 "${BUILD_TMP}/storage" "${BUILD_TMP}/bootstrap/cache"

# Strict sanitization: ensure no local keys or installation locks exist
rm -f "${BUILD_TMP}/.env" \
      "${BUILD_TMP}/.env.production" \
      "${BUILD_TMP}/storage/installed"

find "${BUILD_TMP}/storage" -type f -name '*.key' -delete
# Keep the package/service manifests generated by Composer for the exact
# production dependency set. Removing them makes package commands (Passport,
# for example) unavailable on the first web request. Runtime caches may contain
# environment-specific values and must not be distributed.
find "${BUILD_TMP}/bootstrap/cache" -type f -name '*.php' \
    ! -name 'packages.php' \
    ! -name 'services.php' \
    -delete

ZIP_NAME="dbcms-installer-v${VERSION}.zip"
TAR_NAME="dbcms-v${VERSION}.tar.gz"

required_files=(
    "EMPEZAR.html"
    ".env.installation.example"
    ".htaccess"
    "public/.htaccess"
    "public/index.php"
    "public/build/manifest.json"
    "vendor/autoload.php"
)
for required_file in "${required_files[@]}"; do
    if [ ! -f "${BUILD_TMP}/${required_file}" ]; then
        echo "ERROR: Required installer file missing: ${required_file}" >&2
        exit 1
    fi
done

# A release must start unconfigured. Templates may contain names and placeholders,
# but runtime secrets, installation markers and generated OAuth keys are forbidden.
for forbidden_file in \
    "${BUILD_TMP}/.env" \
    "${BUILD_TMP}/.env.production" \
    "${BUILD_TMP}/storage/installed" \
    "${BUILD_TMP}/storage/oauth-private.key" \
    "${BUILD_TMP}/storage/oauth-public.key"; do
    if [ -e "$forbidden_file" ]; then
        echo "ERROR: Secret or installation state included in package: ${forbidden_file#${BUILD_TMP}/}" >&2
        exit 1
    fi
done
if grep -E '^APP_KEY=base64:[A-Za-z0-9+/]{43}=$' \
    "${BUILD_TMP}/.env.example" \
    "${BUILD_TMP}/.env.production.example" \
    "${BUILD_TMP}/.env.installation.example" >/dev/null; then
    echo "ERROR: A generated APP_KEY was found in an environment template" >&2
    exit 1
fi

if [ -n "${DBCMS_UPDATE_SIGNING_KEY_FILE:-}" ] && [ "${DBCMS_DELIVERY_RUNTIME_ONLY:-0}" != 1 ]; then
    SOURCE_TMP="$(mktemp -d /tmp/dbcms-update-source-XXXXXX)"
    git -C "$ROOT_DIR" archive "$SOURCE_SHA" | tar -x -C "$SOURCE_TMP"
    php "$ROOT_DIR/bin/build-update-release.php" \
        --source-root="$SOURCE_TMP" --runtime-root="$BUILD_TMP" \
        --version="$VERSION" --sha="$SOURCE_SHA" \
        --output="$DIST_DIR" --key-file="$DBCMS_UPDATE_SIGNING_KEY_FILE" \
        --key-id="${DBCMS_UPDATE_SIGNING_KEY_ID:-releases-2026}"
    cp "$DIST_DIR/manifest.json" "$BUILD_TMP/resources/updates/installed-manifest.json"
    cp "$DIST_DIR/manifest.sig" "$BUILD_TMP/resources/updates/installed-manifest.sig"
    rm -rf "$SOURCE_TMP"
fi

echo "--> 5. Packaging the verified runtime once..."
rm -f "$DIST_DIR/$ZIP_NAME" "$DIST_DIR/$TAR_NAME"
(cd "$BUILD_TMP" && zip -q -r "$DIST_DIR/$ZIP_NAME" . && tar -czf "$DIST_DIR/$TAR_NAME" .)
(cd "$DIST_DIR" && sha256sum ./*.zip ./*.tar.gz ./*manifest.json ./*manifest.sig 2>/dev/null > checksums.txt) || {
    if [ "${DBCMS_DELIVERY_RUNTIME_ONLY:-0}" != 1 ] && [ -n "${DBCMS_UPDATE_SIGNING_KEY_FILE:-}" ]; then exit 1; fi
    (cd "$DIST_DIR" && sha256sum "$ZIP_NAME" "$TAR_NAME" > checksums.txt)
}

if [ -n "$RUNTIME_OUTPUT" ]; then
    mkdir -p "$(dirname "$RUNTIME_OUTPUT")"
    cp "$DIST_DIR/$TAR_NAME" "$RUNTIME_OUTPUT"
fi

echo "--> 7. Executing automated HTTP smoke test on release ZIP..."
unzip -q "${DIST_DIR}/${ZIP_NAME}" -d "${SMOKE_TMP}"
cd "${SMOKE_TMP}"

test ! -e .env
test ! -e storage/installed
grep -Eq 'RewriteRule.+public/' .htaccess

SMOKE_PORT="$((18000 + RANDOM % 1000))"
SMOKE_URL="http://localhost:${SMOKE_PORT}"
COOKIE_JAR="${SMOKE_TMP}/cookies.txt"
# The CI service injects its own testing database and runtime drivers. The
# extracted package must instead exercise the values written by the browser
# installer, exactly as it would on shared hosting.
unset APP_ENV DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
unset SESSION_DRIVER CACHE_STORE QUEUE_CONNECTION
php -S "127.0.0.1:${SMOKE_PORT}" -t public >"${SMOKE_TMP}/http-smoke.log" 2>&1 &
SMOKE_PID=$!
for _ in $(seq 1 30); do
    if curl --fail-with-body --silent --show-error --location \
        --cookie-jar "$COOKIE_JAR" --cookie "$COOKIE_JAR" \
        --output "${SMOKE_TMP}/first-response.html" \
        --write-out '%{http_code}' "${SMOKE_URL}/" >"${SMOKE_TMP}/http-status"; then
        break
    fi
    sleep 0.2
done

HTTP_STATUS="$(cat "${SMOKE_TMP}/http-status" 2>/dev/null || true)"
if [[ "$HTTP_STATUS" != "200" && "$HTTP_STATUS" != "302" ]]; then
    cat "${SMOKE_TMP}/http-smoke.log" >&2
    echo "ERROR: First HTTP request returned ${HTTP_STATUS:-no response}" >&2
    exit 1
fi
if grep -qiE 'MissingAppKeyException|No application encryption key has been specified|Whoops, looks like something went wrong' \
    "${SMOKE_TMP}/first-response.html"; then
    echo "ERROR: First HTTP request exposed a Laravel bootstrap failure" >&2
    exit 1
fi
test -s .env || {
    echo "ERROR: First HTTP request did not create the installation environment" >&2
    exit 1
}
grep -Eq '^APP_KEY="?base64:[A-Za-z0-9+/]{43}="?$' .env || {
    echo "ERROR: First HTTP request did not persist a valid APP_KEY" >&2
    exit 1
}

# Complete the same browser workflow against a fresh MySQL database. This uses
# only HTTP requests and the files extracted from the release archive.
extract_form_token() {
    sed -n 's/.*name="_token" value="\([^"]*\)".*/\1/p' "$1" | head -n 1
}
extract_script_token() {
    sed -n "s/.*X-CSRF-TOKEN': '\([^']*\)'.*/\1/p" "$1" | head -n 1
}

SMOKE_THEME="db-cyber"
SMOKE_BRAND="dbcms"
if [ -f "$SMOKE_TMP/cms-project.json" ]; then
    SMOKE_THEME="$(php -r '$p=json_decode(file_get_contents($argv[1]),true,flags:JSON_THROW_ON_ERROR); echo $p["default_theme"] ?? $p["themes"][0] ?? "db-cyber";' "$SMOKE_TMP/cms-project.json")"
    SMOKE_BRAND="$(php -r '$p=json_decode(file_get_contents($argv[1]),true,flags:JSON_THROW_ON_ERROR); echo $p["brand"];' "$SMOKE_TMP/cms-project.json")"
fi
SMOKE_DATABASE="dbcms_release_${RANDOM}_${RANDOM}"
curl --fail-with-body --silent --show-error \
    --cookie-jar "$COOKIE_JAR" --cookie "$COOKIE_JAR" \
    "${SMOKE_URL}/install/database" --output "${SMOKE_TMP}/database.html"
DATABASE_TOKEN="$(extract_form_token "${SMOKE_TMP}/database.html")"
test -n "$DATABASE_TOKEN"
curl --fail-with-body --silent --show-error --location \
    --cookie-jar "$COOKIE_JAR" --cookie "$COOKIE_JAR" \
    --data-urlencode "_token=${DATABASE_TOKEN}" \
    --data-urlencode "host=mysql" \
    --data-urlencode "port=3306" \
    --data-urlencode "database=${SMOKE_DATABASE}" \
    --data-urlencode "username=root" \
    --data-urlencode "password=ci-only-password" \
    --data-urlencode "auto_create=1" \
    "${SMOKE_URL}/install/database/save" --output "${SMOKE_TMP}/migrate.html"

MIGRATION_TOKEN="$(extract_script_token "${SMOKE_TMP}/migrate.html")"
test -n "$MIGRATION_TOKEN"
curl --fail-with-body --silent --show-error \
    --cookie-jar "$COOKIE_JAR" --cookie "$COOKIE_JAR" \
    --header 'Content-Type: application/json' \
    --header "X-CSRF-TOKEN: ${MIGRATION_TOKEN}" \
    --data '{"with_demo":false}' \
    "${SMOKE_URL}/install/migrate/run" --output "${SMOKE_TMP}/migrate.json"
grep -Eq '"success"[[:space:]]*:[[:space:]]*true' "${SMOKE_TMP}/migrate.json"

curl --fail-with-body --silent --show-error \
    --cookie-jar "$COOKIE_JAR" --cookie "$COOKIE_JAR" \
    "${SMOKE_URL}/install/admin" --output "${SMOKE_TMP}/admin.html"
ADMIN_TOKEN="$(extract_form_token "${SMOKE_TMP}/admin.html")"
test -n "$ADMIN_TOKEN"
curl --fail-with-body --silent --show-error --location \
    --cookie-jar "$COOKIE_JAR" --cookie "$COOKIE_JAR" \
    --data-urlencode "_token=${ADMIN_TOKEN}" \
    --data-urlencode "name=Release Smoke Admin" \
    --data-urlencode "email=release-smoke@example.invalid" \
    --data-urlencode "password=release-smoke-password" \
    --data-urlencode "password_confirmation=release-smoke-password" \
    --data-urlencode "site_name=dbCMS Release Smoke" \
    --data-urlencode "app_url=${SMOKE_URL}" \
    --data-urlencode "admin_path=control" \
    --data-urlencode "brand=${SMOKE_BRAND}" \
    --data-urlencode "front_theme=${SMOKE_THEME}" \
    "${SMOKE_URL}/install/admin/store" --output "${SMOKE_TMP}/complete.html"

test -s storage/installed
grep -Fq '"version": "'"${VERSION}"'"' storage/installed
grep -Eq '^SESSION_DRIVER="?database"?$' .env
grep -Eq '^CACHE_STORE="?database"?$' .env
grep -Eq '^QUEUE_CONNECTION="?database"?$' .env
curl --fail-with-body --silent --show-error "${SMOKE_URL}/up" --output "${SMOKE_TMP}/up.html"
curl --fail-with-body --silent --show-error "${SMOKE_URL}/control/login" --output "${SMOKE_TMP}/login.html"
curl --fail-with-body --silent --show-error "${SMOKE_URL}/" --output "${SMOKE_TMP}/homepage.html"
grep -qiE '<form|iniciar sesi[oó]n|login' "${SMOKE_TMP}/login.html"

kill "$SMOKE_PID" 2>/dev/null || true
wait "$SMOKE_PID" 2>/dev/null || true
SMOKE_PID=""

if [ "${DBCMS_DELIVERY_RUNTIME_ONLY:-0}" = 1 ]; then
    rm -f "${DIST_DIR}/${ZIP_NAME}" "${DIST_DIR}/${TAR_NAME}" "${DIST_DIR}/checksums.txt"
fi

echo "======================================================================"
echo " Standalone release successfully built and verified!"
if [ "${DBCMS_DELIVERY_RUNTIME_ONLY:-0}" != 1 ]; then
    echo "   - ${DIST_DIR}/${ZIP_NAME} ($(du -h "${DIST_DIR}/${ZIP_NAME}" | cut -f1))"
    echo "   - ${DIST_DIR}/${TAR_NAME} ($(du -h "${DIST_DIR}/${TAR_NAME}" | cut -f1))"
    echo "   - ${DIST_DIR}/checksums.txt"
else
    echo "   - $RUNTIME_OUTPUT"
fi
echo "======================================================================"
