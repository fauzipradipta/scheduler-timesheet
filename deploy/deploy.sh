#!/usr/bin/env bash
#
# Ship the latest commit. Run this on the VPS for every release:
#
#   cd /var/www/scheduler-timesheet && sudo -u www-data bash deploy/deploy.sh
#   sudo systemctl reload php8.5-fpm
#
# The deploy itself needs no root, only write access to the project.

set -euo pipefail

APP_DIR="/var/www/scheduler-timesheet"
BRANCH="master"
PHP_FPM_SERVICE="php8.5-fpm"

# 'sudo -u www-data' leaves HOME pointing at the calling user, which npm and
# Composer cannot write to. Give them a cache directory of their own instead.
export HOME="/var/www/.deploy-home"
export COMPOSER_HOME="${HOME}/.composer"
export npm_config_cache="${HOME}/.npm"
mkdir -p "$COMPOSER_HOME" "$npm_config_cache"

cd "$APP_DIR"

# Whatever happens below, do not leave the site stuck in maintenance mode.
trap 'php artisan up >/dev/null 2>&1 || true' EXIT

echo "==> Pulling ${BRANCH}"
git fetch --prune origin
git checkout "$BRANCH"
git reset --hard "origin/${BRANCH}"

echo "==> Entering maintenance mode"
php artisan down --retry=15 || true

echo "==> Installing PHP dependencies"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "==> Building the frontend"
# Inertia resolves pages through the Vite manifest and public/build is not in
# git, so this build is what makes new pages reachable at all. The build needs
# devDependencies, hence 'npm ci' rather than an --omit=dev install.
npm ci
npm run build

echo "==> Running migrations"
php artisan migrate --force

echo "==> Caching config, routes and views"
php artisan optimize

echo "==> Leaving maintenance mode"
php artisan up

# Opcache holds the previous release's PHP until the pool restarts. www-data
# has no sudo, so this is left to the caller unless we already are root.
if [[ "$(id -u)" -eq 0 ]]; then
    systemctl reload "$PHP_FPM_SERVICE"
else
    echo
    echo "Now run:  sudo systemctl reload ${PHP_FPM_SERVICE}"
fi

echo "Deployed $(git rev-parse --short HEAD) on $(date -u '+%Y-%m-%d %H:%M UTC')"
