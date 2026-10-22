#!/usr/bin/env bash
set -euo pipefail
: "${VPS_DIR:?VPS application directory is required}"
[[ "$VPS_DIR" =~ ^/[a-zA-Z0-9_./-]+$ ]] && [ "$VPS_DIR" != / ]
mkdir -p "$VPS_DIR/shared" "$VPS_DIR/releases"
# An existing key/configuration is never overwritten by a bootstrap retry.
if [ -e "$VPS_DIR/shared/.env" ] || [ -e "$VPS_DIR/.env" ]; then exit 0; fi
umask 077
candidate="$(mktemp "$VPS_DIR/shared/.env.bootstrap.XXXXXX")"
trap 'rm -f "$candidate"' EXIT
cat > "$candidate"
test -s "$candidate"
grep -Eq '^APP_KEY=.+$' "$candidate" || { echo 'VPS_ENV_FILE must include a persistent APP_KEY.' >&2; exit 1; }
grep -Eq '^APP_URL=https?://' "$candidate" || { echo 'VPS_ENV_FILE must include APP_URL.' >&2; exit 1; }
# Hard linking publishes once and fails if another bootstrap won the race.
ln "$candidate" "$VPS_DIR/shared/.env"
