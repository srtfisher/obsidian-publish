#!/usr/bin/env bash
#
# Deploys this application in place. Invoked by the GitHub Actions workflow as a
# forced command over SSH, and safe to run by hand on the server.
#
set -euo pipefail

SITE_DIR="${SITE_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
BRANCH="${DEPLOY_BRANCH:-main}"

cd "$SITE_DIR"

echo "==> Deploying $BRANCH to $SITE_DIR"

# A failed deploy must never leave the site in maintenance mode.
trap 'php artisan up >/dev/null 2>&1 || true' EXIT

php artisan down --retry=60 >/dev/null 2>&1 || true

echo "==> Fetching"
git fetch --prune origin "$BRANCH"
git reset --hard "origin/$BRANCH"

echo "==> Composer"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> Assets"
npm ci --no-audit --no-fund
npm run build

echo "==> Migrating"
php artisan migrate --force

echo "==> Caching"
php artisan optimize

# This server sets opcache.validate_timestamps=0, so php-fpm keeps serving the
# previous deploy's bytecode until its workers are recycled.
echo "==> Reloading PHP-FPM"
sudo -n /usr/sbin/service "php$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')-fpm" reload

php artisan up >/dev/null 2>&1 || true
trap - EXIT

echo "==> Deployed $(git rev-parse --short HEAD)"
