#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
export LOCAL_UID="${LOCAL_UID:-$(id -u)}"
export LOCAL_GID="${LOCAL_GID:-$(id -g)}"

docker compose build app db
docker compose run --rm --no-deps app php scripts/configure-local.php
case "${COMPOSER_INSTALL_MODE:-dist}" in
    dist|source) ;;
    *) printf '%s\n' 'COMPOSER_INSTALL_MODE harus dist atau source.' >&2; exit 1 ;;
esac
docker compose run --rm --no-deps app composer install --no-interaction "--prefer-${COMPOSER_INSTALL_MODE:-dist}"
docker compose up -d db --wait
docker compose run --rm app php artisan migrate --no-interaction
docker compose run --rm node npm ci
docker compose run --rm node npm run build
printf '%s\n' 'Setup lokal selesai. Jalankan docker compose run --rm app composer test, lalu docker compose up -d app.'
