#!/usr/bin/env bash
#
# One-time setup of a fresh Hostinger VPS (Ubuntu 22.04 or 24.04) for this app.
# Run it once as root, or with sudo, on a brand new server:
#
#   sudo bash deploy/provision.sh yourdomain.com
#
# Afterwards, releases go out with deploy/deploy.sh, which needs no root.

set -euo pipefail

DOMAIN="${1:-}"
APP_DIR="/var/www/scheduler-timesheet"
REPO="https://github.com/fauzipradipta/scheduler-timesheet.git"
BRANCH="master"
PHP_VERSION="8.5"

if [[ -z "$DOMAIN" ]]; then
    echo "Usage: sudo bash deploy/provision.sh <domain>" >&2
    exit 1
fi

if [[ "$(id -u)" -ne 0 ]]; then
    echo "This script installs system packages, so run it as root." >&2
    exit 1
fi

echo "==> Installing packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y software-properties-common curl git unzip ca-certificates

# Ubuntu ships an older PHP than this project is tested against, so take PHP
# from Ondrej's archive to match the version CI runs.
add-apt-repository -y ppa:ondrej/php
apt-get update

# sqlite3 backs the database, xml and zip build the .xlsx, curl fetches the
# Indonesian holiday feed.
apt-get install -y \
    "php${PHP_VERSION}-fpm" \
    "php${PHP_VERSION}-cli" \
    "php${PHP_VERSION}-sqlite3" \
    "php${PHP_VERSION}-mbstring" \
    "php${PHP_VERSION}-xml" \
    "php${PHP_VERSION}-zip" \
    "php${PHP_VERSION}-curl" \
    "php${PHP_VERSION}-bcmath" \
    "php${PHP_VERSION}-intl" \
    nginx

echo "==> Installing Composer"
if ! command -v composer >/dev/null 2>&1; then
    curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi

echo "==> Installing Node 22"
if ! command -v node >/dev/null 2>&1; then
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    apt-get install -y nodejs
fi

echo "==> Cloning the repository into ${APP_DIR}"
if [[ ! -d "${APP_DIR}/.git" ]]; then
    mkdir -p "$(dirname "$APP_DIR")"
    git clone --branch "$BRANCH" "$REPO" "$APP_DIR"
fi
cd "$APP_DIR"

echo "==> Writing the production environment file"
if [[ ! -f .env ]]; then
    cp .env.example .env

    # Debug output leaks stack traces and environment values, so it stays off.
    sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
    sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env
    sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" .env

    php artisan key:generate --force
fi

echo "==> Creating the SQLite database"
# The directory has to be writable too, for the -wal and -journal files.
touch database/database.sqlite

echo "==> Setting ownership"
# The deploy script caches npm and Composer downloads here, as www-data.
mkdir -p /var/www/.deploy-home
chown -R www-data:www-data /var/www/.deploy-home
chown -R www-data:www-data "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/database"

echo "==> Installing the nginx site"
sed "s|REPLACE_WITH_YOUR_DOMAIN|${DOMAIN}|" deploy/nginx.conf \
    > /etc/nginx/sites-available/scheduler-timesheet
ln -sf /etc/nginx/sites-available/scheduler-timesheet /etc/nginx/sites-enabled/scheduler-timesheet
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx
systemctl enable --now "php${PHP_VERSION}-fpm"

echo "==> Opening the firewall"
if command -v ufw >/dev/null 2>&1; then
    ufw allow OpenSSH || true
    ufw allow 'Nginx Full' || true
fi

echo "==> Building the first release"
sudo -u www-data bash "${APP_DIR}/deploy/deploy.sh"
systemctl reload "php${PHP_VERSION}-fpm"

cat <<DONE

Provisioning finished. Two things are left, both by hand:

  1. Point the domain's A record at this server's IP address.
  2. Once DNS resolves, issue a certificate:

       sudo apt-get install -y certbot python3-certbot-nginx
       sudo certbot --nginx -d ${DOMAIN}

Then create your first account at https://${DOMAIN}/register
DONE
