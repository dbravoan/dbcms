#!/usr/bin/env bash
set -euo pipefail
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
project_root="$(cd "$script_dir/.." && pwd)"
cd "$project_root"
if [ ! -f vendor/autoload.php ]; then
    echo 'Use a built standalone release containing vendor and public/build; dependencies are built inside CI Docker.' >&2
    exit 1
fi
bash "$script_dir/vps-artisan.sh" cms:install "$@"
bash "$script_dir/vps-artisan.sh" config:cache
bash "$script_dir/vps-artisan.sh" route:cache
bash "$script_dir/vps-artisan.sh" view:cache
echo 'Installed using the VPS PHP CLI; existing VPS/Plesk web hosting retained.'
