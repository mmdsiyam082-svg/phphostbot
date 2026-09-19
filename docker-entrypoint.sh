#!/bin/sh
set -e
HOSTING_DIR="/var/www/html/sites"
mkdir -p "$HOSTING_DIR"
chown -R www-data:www-data "$HOSTING_DIR"
chmod 755 "$HOSTING_DIR"
exec apache2-foreground
