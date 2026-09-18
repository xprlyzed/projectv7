#!/usr/bin/env bash
###############################################################################
# Laravel (Artirdim) bootstrap & self-heal script.
#
# The base pod image only ships Node/Mongo. PHP, Composer and MariaDB live in
# system paths (/usr, /etc) which are WIPED on pod restart, while /app persists.
# This script re-installs whatever is missing and then serves the Laravel app
# on port 3000 (the port the platform ingress routes the site to).
#
# It is invoked by supervisor's "frontend" program via /app/frontend package.json
# "start" script, so it runs automatically on every (re)start.
###############################################################################
set -e

APP_DIR=/app/projecct
DB_DATADIR=/app/.mariadb-data
DB_SOCK=/var/run/mysqld/mysqld.sock
export DEBIAN_FRONTEND=noninteractive

log() { echo "[bootstrap] $*"; }

###############################################################################
# 1. Ensure PHP + extensions + Composer + MariaDB binaries exist
###############################################################################
NEED_APT=0
command -v php >/dev/null 2>&1 || NEED_APT=1
command -v mariadbd >/dev/null 2>&1 || NEED_APT=1

if [ "$NEED_APT" = "1" ]; then
  log "Installing PHP / MariaDB (missing after pod restart)..."
  apt-get update -qq || true
  apt-get install -y -qq \
    php php-cli php-fpm php-mysql php-mbstring php-xml php-curl php-gd \
    php-zip php-bcmath php-intl php-gmp php-sqlite3 php-redis \
    mariadb-server mariadb-client unzip git >/dev/null 2>&1 || true
fi

if ! command -v composer >/dev/null 2>&1; then
  log "Installing Composer..."
  php -r "copy('https://getcomposer.org/installer','/tmp/composer-setup.php');" 2>/dev/null || true
  php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer 2>/dev/null || true
  chmod +x /usr/local/bin/composer 2>/dev/null || true
fi

###############################################################################
# 2. Start MariaDB (persistent datadir under /app)
###############################################################################
mkdir -p /var/run/mysqld /var/log/mysql
chown -R mysql:mysql /var/run/mysqld /var/log/mysql 2>/dev/null || true

if [ ! -d "$DB_DATADIR/mysql" ]; then
  log "Initializing MariaDB datadir..."
  mkdir -p "$DB_DATADIR"
  chown -R mysql:mysql "$DB_DATADIR"
  mariadb-install-db --user=mysql --datadir="$DB_DATADIR" --auth-root-authentication-method=normal >/dev/null 2>&1 || true
fi
chown -R mysql:mysql "$DB_DATADIR" 2>/dev/null || true

if ! mysqladmin --socket="$DB_SOCK" ping >/dev/null 2>&1; then
  log "Starting MariaDB..."
  nohup mariadbd --user=mysql --datadir="$DB_DATADIR" --socket="$DB_SOCK" \
    > /var/log/mysql/mariadb.log 2>&1 &
  for i in $(seq 1 30); do
    mysqladmin --socket="$DB_SOCK" ping >/dev/null 2>&1 && break
    sleep 1
  done
fi

# Ensure app DB + user exist
mysql --socket="$DB_SOCK" >/dev/null 2>&1 <<'SQL' || true
CREATE DATABASE IF NOT EXISTS auction CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'auction'@'127.0.0.1' IDENTIFIED BY 'auction';
CREATE USER IF NOT EXISTS 'auction'@'localhost' IDENTIFIED BY 'auction';
CREATE USER IF NOT EXISTS 'auction'@'%' IDENTIFIED BY 'auction';
GRANT ALL PRIVILEGES ON auction.* TO 'auction'@'127.0.0.1';
GRANT ALL PRIVILEGES ON auction.* TO 'auction'@'localhost';
GRANT ALL PRIVILEGES ON auction.* TO 'auction'@'%';
FLUSH PRIVILEGES;
SQL

###############################################################################
# 3. Ensure Laravel deps + run migrations
###############################################################################
cd "$APP_DIR"

[ -d vendor ] || { log "composer install..."; composer install --no-interaction --prefer-dist >/dev/null 2>&1 || true; }
[ -d public/build ] || { log "npm build..."; npm install >/dev/null 2>&1 && npm run build >/dev/null 2>&1 || true; }

php artisan migrate --force >/dev/null 2>&1 || true

# Seed only on a fresh database (no users yet)
USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | tail -1 | tr -dc '0-9')
if [ -z "$USER_COUNT" ] || [ "$USER_COUNT" = "0" ]; then
  log "Seeding database (first run)..."
  php artisan db:seed --force >/dev/null 2>&1 || true
fi

php artisan config:clear >/dev/null 2>&1 || true

###############################################################################
# 4. Background queue worker (database queue)
###############################################################################
pkill -f "artisan queue:work" 2>/dev/null || true
nohup php artisan queue:work --tries=3 --sleep=1 > /var/log/mysql/queue.log 2>&1 &

###############################################################################
# 5. Serve the app on port 3000 (foreground -> keeps supervisor happy)
###############################################################################
log "Serving Laravel on 0.0.0.0:3000"
exec php artisan serve --host=0.0.0.0 --port=3000
