#!/bin/sh
set -eu

port="${PORT:-8080}"
case "$port" in
    *[!0-9]*|'') echo 'PORT must be numeric' >&2; exit 1 ;;
esac

sed -i "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

private_root="${SENIORLINK_PRIVATE_STORAGE:-/data/private}"
mkdir -p "${private_root}/uploads"
chown -R www-data:www-data "${private_root}"

exec apache2-foreground
