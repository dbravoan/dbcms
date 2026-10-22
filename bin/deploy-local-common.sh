#!/usr/bin/env bash
# Shared by the local deployers. Never source a project .env or require a Git checkout.
set -Eeuo pipefail
DEPLOY_PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
deployment_validate_local() {
    : "${VPS_HOST:?VPS_HOST is required}"
    : "${VPS_USER:?VPS_USER is required}"
    : "${VPS_DIR:?VPS_DIR is required}"
    : "${VPS_SSH_KEY_FILE:?An external private key file is required}"
    : "${VPS_SSH_FINGERPRINT:?An SSH SHA256 host fingerprint is required}"
    VPS_PORT="${VPS_PORT:-22}"
    [[ "$VPS_HOST" =~ ^[a-zA-Z0-9.-]+$ ]] && [[ "$VPS_USER" =~ ^[a-zA-Z0-9_-]+$ ]]
    [[ "$VPS_PORT" =~ ^[0-9]{1,5}$ ]] && ((10#$VPS_PORT > 0 && 10#$VPS_PORT <= 65535))
    [[ "$VPS_DIR" =~ ^/[a-zA-Z0-9_./-]+$ ]] && [ "$VPS_DIR" != / ] && [[ "$VPS_DIR/" != */../* ]]
    [[ "$VPS_SSH_FINGERPRINT" =~ ^SHA256:[a-zA-Z0-9+/]+={0,2}$ ]]
    for tool in docker ssh ssh-keyscan ssh-keygen rsync; do command -v "$tool" >/dev/null; done
    test -f "$VPS_SSH_KEY_FILE" && test ! -L "$VPS_SSH_KEY_FILE"
    VPS_SSH_KEY_FILE="$(cd "$(dirname "$VPS_SSH_KEY_FILE")" && pwd -P)/$(basename "$VPS_SSH_KEY_FILE")"
    [[ "$VPS_SSH_KEY_FILE" != "$DEPLOY_PROJECT_ROOT/"* ]] || { echo 'Keep the SSH private key outside the project directory.' >&2; return 1; }
    if [ -n "${VPS_ENV_FILE_PATH:-}" ]; then
        test -s "$VPS_ENV_FILE_PATH" && test ! -L "$VPS_ENV_FILE_PATH"
        VPS_ENV_FILE_PATH="$(cd "$(dirname "$VPS_ENV_FILE_PATH")" && pwd -P)/$(basename "$VPS_ENV_FILE_PATH")"
        [[ "$VPS_ENV_FILE_PATH" != "$DEPLOY_PROJECT_ROOT/"* ]] || { echo 'Keep the bootstrap environment outside the project directory.' >&2; return 1; }
    fi
    test -f "$DEPLOY_PROJECT_ROOT/cms-project.json"
    DEPLOY_TEMP="$(mktemp -d)"
    trap deployment_cleanup EXIT
    cp "$VPS_SSH_KEY_FILE" "$DEPLOY_TEMP/identity"
    chmod 600 "$DEPLOY_TEMP/identity"
    ssh-keyscan -T 10 -t ed25519 -p "$VPS_PORT" "$VPS_HOST" > "$DEPLOY_TEMP/known_hosts"
    test "$(ssh-keygen -lf "$DEPLOY_TEMP/known_hosts" | awk '{print $2}' | sort -u)" = "$VPS_SSH_FINGERPRINT"
    DEPLOY_SSH=(ssh -i "$DEPLOY_TEMP/identity" -p "$VPS_PORT" -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes -o "UserKnownHostsFile=$DEPLOY_TEMP/known_hosts")
    printf -v DEPLOY_RSYNC_SSH 'ssh -i %q -p %q -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes -o UserKnownHostsFile=%q' "$DEPLOY_TEMP/identity" "$VPS_PORT" "$DEPLOY_TEMP/known_hosts"
    DEPLOY_DESTINATION="$VPS_USER@$VPS_HOST"
    RELEASE_NAME="release-$(date -u +%Y%m%d%H%M%S)-$(od -An -N6 -tx1 /dev/urandom | tr -d ' \n')"
}
deployment_cleanup() {
    if [ -n "${DEPLOY_QUALITY_PROJECT:-}" ]; then
        docker compose --project-name "$DEPLOY_QUALITY_PROJECT" -f "$DEPLOY_TEMP/context/docker/ci/compose.yml" down --volumes --remove-orphans --rmi local >/dev/null 2>&1 || true
    fi
    if [ -n "${DEPLOY_CONTAINER:-}" ]; then docker rm "$DEPLOY_CONTAINER" >/dev/null 2>&1 || true; fi
    if [ -n "${DEPLOY_IMAGE:-}" ]; then docker image rm "$DEPLOY_IMAGE" >/dev/null 2>&1 || true; fi
    if [ -n "${DEPLOY_TEMP:-}" ]; then rm -rf "$DEPLOY_TEMP"; fi
}
deployment_build_context() {
    mkdir "$DEPLOY_TEMP/context"
    # These explicit exclusions remain effective even if a derived project changes .dockerignore.
    rsync -rlpt --exclude='.git' --exclude='.github/' --exclude='.env' \
        --include='.env.example' --include='.env.production.example' --include='.env.installation.example' --exclude='.env.*' \
        --exclude='*.pem' --exclude='*.key' --exclude='auth.json' --exclude='.npmrc' --exclude='.netrc' --exclude='storage/' \
        --exclude='public/uploads/' --exclude='public/storage' --exclude='public/build/' --exclude='public/hot' \
        --exclude='bootstrap/cache/*.php' --exclude='bootstrap/cache/*.json' \
        --exclude='vendor/' --exclude='node_modules/' --exclude='dist/' --exclude='coverage/' \
        --exclude='.mcp.json' --exclude='mcp/mcp_config.json' --exclude='*.sql' --exclude='*.sqlite*' \
        --exclude='.phpunit.cache/' --exclude='.phpstan.cache' --exclude='.agents/' --exclude='.gemini/' --exclude='.opencode/' \
        "$DEPLOY_PROJECT_ROOT/" "$DEPLOY_TEMP/context/"
}
deployment_check_context() {
    DEPLOY_QUALITY_PROJECT="deploycheck-$(od -An -N6 -tx1 /dev/urandom | tr -d ' \n')"
    docker compose --project-name "$DEPLOY_QUALITY_PROJECT" -f "$DEPLOY_TEMP/context/docker/ci/compose.yml" \
        run --build --rm -T app bash bin/ci-check
    docker compose --project-name "$DEPLOY_QUALITY_PROJECT" -f "$DEPLOY_TEMP/context/docker/ci/compose.yml" down --volumes --remove-orphans --rmi local
    DEPLOY_QUALITY_PROJECT=''
}
deployment_build_runtime() {
    local profile="${1:?Deployment profile is required}"
    deployment_build_context
    deployment_check_context
    DEPLOY_IMAGE="dbcms-local-deploy:${RELEASE_NAME}"
    docker build --platform linux/amd64 --file "$DEPLOY_TEMP/context/docker/production/Dockerfile" --target runtime --tag "$DEPLOY_IMAGE" "$DEPLOY_TEMP/context"
    docker run --rm --entrypoint php "$DEPLOY_IMAGE" -r '
        $profile = json_decode(file_get_contents("cms-project.json"), true, 512, JSON_THROW_ON_ERROR);
        if (($profile["deployment"] ?? null) !== $argv[1]) { fwrite(STDERR, "Incorrect project deployment profile.\n"); exit(1); }
    ' "$profile"
    DEPLOY_CONTAINER="$(docker create "$DEPLOY_IMAGE")"
    mkdir "$DEPLOY_TEMP/runtime"
    docker cp "$DEPLOY_CONTAINER:/var/www/html/." "$DEPLOY_TEMP/runtime/"
    docker rm "$DEPLOY_CONTAINER" >/dev/null
    DEPLOY_CONTAINER=''
    rm -f "$DEPLOY_TEMP/runtime/.env"
    test -f "$DEPLOY_TEMP/runtime/vendor/autoload.php"
    test -f "$DEPLOY_TEMP/runtime/public/build/manifest.json"
}
deployment_remote_bootstrap() {
    local script_path="${1:?Remote bootstrap script is required}"
    if [ -n "${VPS_ENV_FILE_PATH:-}" ]; then
        local remote
        printf -v remote 'env VPS_DIR=%q bash %q' "$VPS_DIR" "$script_path"
        "${DEPLOY_SSH[@]}" "$DEPLOY_DESTINATION" "$remote" < "$VPS_ENV_FILE_PATH"
    fi
}
