#!/usr/bin/env bash
set -euo pipefail

# ==============================================================================
# dbCMS Standalone Release Package Builder
# ==============================================================================
# Produces self-contained, WordPress-style distribution archives:
#   - dist/dbcms-v<VERSION>.zip
#   - dist/dbcms-v<VERSION>.tar.gz
#   - dist/checksums.txt (SHA256)
# ==============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

# Packaging uses exactly the container runtime used by CI.
if [ ! -f /.dockerenv ]; then
    cd "$ROOT_DIR"
    exec docker compose -f docker/ci/compose.yml run --build --rm app bash bin/build-release-zip.sh "$@"
fi

VERSION=""

# Parse arguments
for arg in "$@"; do
    case "$arg" in
        --version=*)
            VERSION="${arg#*=}"
            ;;
        *)
            ;;
    esac
done

if [ -z "$VERSION" ]; then
    if [ -f "${ROOT_DIR}/VERSION" ]; then
        VERSION="$(cat "${ROOT_DIR}/VERSION" | tr -d '[:space:]')"
    else
        VERSION="1.0.0"
    fi
fi

# Normalize version (remove leading v if provided)
VERSION="${VERSION#v}"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+([+-][a-zA-Z0-9.-]+)?$ ]] || { echo "Invalid release version" >&2; exit 64; }

echo "======================================================================"
echo " Building dbCMS Standalone Release Archive: v${VERSION}"
echo "======================================================================"

DIST_DIR="${ROOT_DIR}/dist"
mkdir -p "${DIST_DIR}"

BUILD_TMP="$(mktemp -d /tmp/dbcms-build-XXXXXX)"
SMOKE_TMP="$(mktemp -d /tmp/dbcms-smoke-XXXXXX)"

cleanup() {
    rm -rf "${BUILD_TMP}" "${SMOKE_TMP}"
}
trap cleanup EXIT

echo "--> 1. Exporting clean source tree with git archive..."
cd "${ROOT_DIR}"
git archive HEAD | tar -x -C "${BUILD_TMP}"
# Tracked development manifests must never be shipped as runtime configuration.
find "$BUILD_TMP/bootstrap/cache" -type f -name '*.php' -delete
rm -f "$BUILD_TMP/mcp/mcp_config.json" "$BUILD_TMP/.mcp.json"
find "$BUILD_TMP" -maxdepth 1 -type f -name '.env*' ! -name '.env.example' ! -name '.env.production.example' -delete

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
npm ci --silent
npm run build

if [ ! -f "${BUILD_TMP}/public/build/manifest.json" ]; then
    echo "ERROR: Front-end manifest missing at public/build/manifest.json" >&2
    exit 1
fi

echo "--> 4. Pruning development artifacts and preparing storage directories..."
rm -rf "${BUILD_TMP}/node_modules"

mkdir -p "${BUILD_TMP}/storage/app/public" \
         "${BUILD_TMP}/storage/framework/cache/data" \
         "${BUILD_TMP}/storage/framework/sessions" \
         "${BUILD_TMP}/storage/framework/views" \
         "${BUILD_TMP}/storage/logs" \
         "${BUILD_TMP}/bootstrap/cache"

chmod -R 775 "${BUILD_TMP}/storage" "${BUILD_TMP}/bootstrap/cache"

# Ensure critical webserver files exist
if [ -f "${ROOT_DIR}/public/.htaccess" ] && [ ! -f "${BUILD_TMP}/public/.htaccess" ]; then
    cp "${ROOT_DIR}/public/.htaccess" "${BUILD_TMP}/public/.htaccess"
fi
if [ -f "${ROOT_DIR}/.htaccess" ] && [ ! -f "${BUILD_TMP}/.htaccess" ]; then
    cp "${ROOT_DIR}/.htaccess" "${BUILD_TMP}/.htaccess"
fi
if [ -f "${ROOT_DIR}/LICENSE" ] && [ ! -f "${BUILD_TMP}/LICENSE" ]; then
    cp "${ROOT_DIR}/LICENSE" "${BUILD_TMP}/LICENSE"
fi
if [ -f "${ROOT_DIR}/SECURITY.md" ] && [ ! -f "${BUILD_TMP}/SECURITY.md" ]; then
    cp "${ROOT_DIR}/SECURITY.md" "${BUILD_TMP}/SECURITY.md"
fi
if [ -f "${ROOT_DIR}/CHANGELOG.md" ] && [ ! -f "${BUILD_TMP}/CHANGELOG.md" ]; then
    cp "${ROOT_DIR}/CHANGELOG.md" "${BUILD_TMP}/CHANGELOG.md"
fi

# Strict sanitization: ensure no local keys or installation locks exist
rm -f "${BUILD_TMP}/.env" \
      "${BUILD_TMP}/.env.production" \
      "${BUILD_TMP}/storage/installed"

find "${BUILD_TMP}/storage" -type f -name '*.key' -delete
find "${BUILD_TMP}/bootstrap/cache" -type f -name '*.php' -delete

ZIP_NAME="dbcms-v${VERSION}.zip"
TAR_NAME="dbcms-v${VERSION}.tar.gz"

echo "--> 5. Packaging standalone archives into ${DIST_DIR}..."
cd "${BUILD_TMP}"
zip -q -r "${DIST_DIR}/${ZIP_NAME}" .
tar -czf "${DIST_DIR}/${TAR_NAME}" .

echo "--> 6. Generating SHA256 checksums..."
cd "${DIST_DIR}"
sha256sum "${ZIP_NAME}" "${TAR_NAME}" > checksums.txt

echo "--> 7. Executing automated smoke test on release ZIP..."
unzip -q "${DIST_DIR}/${ZIP_NAME}" -d "${SMOKE_TMP}"
cd "${SMOKE_TMP}"

php artisan --version >/dev/null 2>&1 || {
    echo "ERROR: Smoke test failed: php artisan --version failed inside unpacked release archive" >&2
    exit 1
}

php artisan route:list --path=install >/dev/null 2>&1 || {
    echo "ERROR: Smoke test failed: install routes missing from route:list" >&2
    exit 1
}

echo "======================================================================"
echo " Standalone release successfully built and verified!"
echo "   - ${DIST_DIR}/${ZIP_NAME} ($(du -h "${DIST_DIR}/${ZIP_NAME}" | cut -f1))"
echo "   - ${DIST_DIR}/${TAR_NAME} ($(du -h "${DIST_DIR}/${TAR_NAME}" | cut -f1))"
echo "   - ${DIST_DIR}/checksums.txt"
echo "======================================================================"
