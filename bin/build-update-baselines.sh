#!/usr/bin/env bash
# Run only in the release container. Historic releases remain immutable: their
# inventories are signed and attached to the new release, never retrofitted.
set -euo pipefail
if [ ! -f /.dockerenv ]; then
    echo 'Run the baseline builder in the release Docker container.' >&2
    exit 64
fi
: "${CMS_UPDATE_SIGNING_KEY_FILE:?Private release signing key file is required}"
: "${CMS_UPDATE_SIGNING_KEY_ID:?Release signing key identifier is required}"
release_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
baseline_output="${1:-${release_root}/dist}"
mkdir -p "$baseline_output"
baseline_tmp="$(mktemp -d /tmp/core-update-baselines-XXXXXX)"
trap 'rm -rf "$baseline_tmp"' EXIT
for baseline_version in 1.0.0 1.0.1; do
    baseline_source="$baseline_tmp/source-$baseline_version"
    mkdir -p "$baseline_source"
    baseline_sha="$(git -C "$release_root" rev-parse "v${baseline_version}^{commit}")"
    [[ "$baseline_sha" =~ ^[0-9a-f]{40}$ ]]
    case "$baseline_version" in
        1.0.0) baseline_expected_sha=3c8619b05b253f4ac609e54ff9ec73a6d59d42d3 ;;
        1.0.1) baseline_expected_sha=f90131bb7dc745210443ab37d8bc76fa83a46736 ;;
    esac
    test "$baseline_sha" = "$baseline_expected_sha" || { echo "Historical tag changed: v${baseline_version}" >&2; exit 1; }
    git -C "$release_root" archive "$baseline_sha" | tar -xf - -C "$baseline_source"
    baseline_runtime_args=()
    if [ "$baseline_version" = 1.0.1 ]; then
        baseline_runtime="$baseline_tmp/runtime-$baseline_version"
        mkdir -p "$baseline_runtime" "$baseline_tmp/download"
        # This immutable release is public. Downloads are build inputs only;
        # deployed installations consume our signed baseline, never this script.
        for baseline_asset in dbcms-v1.0.1.zip checksums.txt; do
            curl --fail --silent --show-error --location --proto '=https' --proto-redir '=https' \
                --connect-timeout 10 --max-time 180 --max-filesize 536870912 \
                "https://github.com/dbravoan/dbcms/releases/download/v1.0.1/${baseline_asset}" \
                --output "$baseline_tmp/download/$baseline_asset"
        done
        (cd "$baseline_tmp/download" && awk '$2 == "dbcms-v1.0.1.zip" { print }' checksums.txt > selected-checksum.txt && test -s selected-checksum.txt && sha256sum -c selected-checksum.txt)
        printf '%s  %s\n' 7cb9b8eeb65cbfdd2144047ac5ead6a40f4e3eff94b50b3be02db8b5744f44a1 "$baseline_tmp/download/dbcms-v1.0.1.zip" | sha256sum -c -
        unzip -q "$baseline_tmp/download/dbcms-v1.0.1.zip" -d "$baseline_runtime"
        baseline_runtime_args=(--runtime-root="$baseline_runtime")
    fi
    php "$release_root/bin/build-update-release.php" \
        --source-root="$baseline_source" "${baseline_runtime_args[@]}" \
        --version="$baseline_version" --sha="$baseline_sha" --output="$baseline_output" \
        --key-file="$CMS_UPDATE_SIGNING_KEY_FILE" --key-id="$CMS_UPDATE_SIGNING_KEY_ID" \
        --prefix="baseline-v${baseline_version}"
done
# Only metadata is required to recognize old installations. Do not offer the
# generated historical copies as replacement distributions of stable releases.
find "$baseline_output" -maxdepth 1 -type f -name 'baseline-*.zip' -delete
