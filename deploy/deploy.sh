#!/usr/bin/env bash
#
# Ship the latest commit. Run this on the VPS for every release:
#
#   cd /var/www/scheduler-timesheet && sudo -u www-data bash deploy/deploy.sh
#
# It prints the PHP-FPM reload command to finish with. The deploy itself needs
# no root, only write access to the project.

set -euo pipefail

APP_DIR="/var/www/scheduler-timesheet"
BRANCH="master"

# Servers differ on which PHP they run, so ask rather than assume. Prefer a
# unit systemd actually has; fall back to the CLI's own version.
detect_php_fpm() {
    local unit
    unit="$(systemctl list-units --type=service --all --no-legend --plain 'php*fpm*.service' 2>/dev/null \
        | awk '{print $1}' | head -n1)"

    if [[ -n "$unit" ]]; then
        echo "${unit%.service}"
        return
    fi

    echo "php$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')-fpm"
}

PHP_FPM_SERVICE="$(detect_php_fpm)"

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

echo "==> Clearing the previous release's caches"
# Wayfinder writes its TypeScript from the route list, and the build imports
# what it writes. A routes cache left behind by the previous release hides new
# routes from it, so the build fails on an import for a file it never wrote.
# This has to happen before the build, not after it.
php artisan optimize:clear

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
