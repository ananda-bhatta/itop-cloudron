#!/bin/bash
set -euo pipefail
umask 0027

: "${CLOUDRON_MYSQL_HOST:?}" "${CLOUDRON_MYSQL_PORT:?}" "${CLOUDRON_MYSQL_DATABASE:?}"
: "${CLOUDRON_MYSQL_USERNAME:?}" "${CLOUDRON_MYSQL_PASSWORD:?}" "${CLOUDRON_APP_ORIGIN:?}"

mkdir -p /app/data /run/apache2 /run/lock/apache2 /run/php/sessions
chown www-data:www-data /run/php/sessions
version=$(cat /app/code/upstream-version)

if [[ ! -d /app/data/public ]]; then
    echo 'Initializing iTop application files'
    mkdir -p /app/data/.initializing
    rsync -a /app/code/upstream/ /app/data/.initializing/
    printf '%s\n' "$version" > /app/data/.initializing/.cloudron-upstream-version
    mv /app/data/.initializing /app/data/public
fi

if [[ ! -f /app/data/public/.cloudron-upstream-version ]] || \
   [[ $(cat /app/data/public/.cloudron-upstream-version) != "$version" ]]; then
    echo 'This experimental package does not yet support upstream version migrations. Restore the matching package and consult README.md.' >&2
    exit 1
fi

# Refresh package-managed code, retaining iTop configuration and compiled models.
# The writable root is necessary because iTop renames env-* during setup.
rsync -a --delete \
    --exclude '/.cloudron-upstream-version' \
    --exclude '/conf/***' --exclude '/data/***' --exclude '/log/***' \
    --exclude '/extensions/***' --exclude '/env-*/***' \
    /app/code/upstream/ /app/data/public/

if [[ ! -f /app/data/setup-password ]]; then
    openssl rand -hex 24 > /app/data/setup-password
fi
htpasswd -iBc /app/data/setup.htpasswd setup < /app/data/setup-password > /dev/null

# Only Cloudron administrators should read this file, via the app File Manager.
{
    printf 'Setup URL: %s/setup/\nSetup HTTP username: setup\nSetup HTTP password: ' "$CLOUDRON_APP_ORIGIN"
    cat /app/data/setup-password
    printf '\nDatabase server: %s:%s\nDatabase name: %s\nDatabase user: %s\nDatabase password: %s\n' \
        "$CLOUDRON_MYSQL_HOST" "$CLOUDRON_MYSQL_PORT" "$CLOUDRON_MYSQL_DATABASE" \
        "$CLOUDRON_MYSQL_USERNAME" "$CLOUDRON_MYSQL_PASSWORD"
    printf '\nUse the existing database. Choose a separate iTop administrator password in the setup wizard.\n'
} > /app/data/initial-setup.txt
chmod 600 /app/data/setup-password /app/data/initial-setup.txt
chown root:www-data /app/data/setup.htpasswd
chmod 640 /app/data/setup.htpasswd
chown -R www-data:www-data /app/data/public
if [[ -f /app/data/cron.params ]]; then
    chown root:www-data /app/data/cron.params
    chmod 640 /app/data/cron.params
fi

export APACHE_RUN_DIR=/run/apache2 APACHE_LOCK_DIR=/run/lock/apache2
export APACHE_LOG_DIR=/run/apache2 APACHE_PID_FILE=/run/apache2/apache2.pid
export APACHE_RUN_USER=www-data APACHE_RUN_GROUP=www-data
rm -f /run/apache2/apache2.pid
echo 'Starting iTop web server'
exec /usr/sbin/apache2 -DFOREGROUND
