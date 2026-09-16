#!/bin/sh
set -eu

port="${PORT:-8080}"
case "$port" in
    *[!0-9]*|'') echo 'PORT must be numeric' >&2; exit 1 ;;
esac

sed -i "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

# mod_php needs prefork; Apache cannot start when event or worker is also enabled.
rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf \
    /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf
a2enmod mpm_prefork >/dev/null
apache2ctl configtest

private_root="${SENIORLINK_PRIVATE_STORAGE:-/data/private}"
mkdir -p "${private_root}/uploads"
mkdir -p /data/profile_pictures /data/system_logos
cp -an /usr/local/share/seniorlink/images/profile_pictures/. /data/profile_pictures/
cp -an /usr/local/share/seniorlink/images/system_logos/. /data/system_logos/
chown -R www-data:www-data "${private_root}" /data/profile_pictures /data/system_logos

php /var/www/html/scripts/railway-bootstrap.php

exec apache2-foreground
