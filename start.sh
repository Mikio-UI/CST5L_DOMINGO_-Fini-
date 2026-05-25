#!/bin/bash
set -e

# Kill mpm_event at runtime before Apache starts
a2dismod mpm_event 2>/dev/null || true
rm -f /etc/apache2/mods-enabled/mpm_event.conf
rm -f /etc/apache2/mods-enabled/mpm_event.load
a2enmod mpm_prefork 2>/dev/null || true

# Railway assigns a dynamic port via $PORT — Apache must listen on it
if [ -n "$PORT" ]; then
    sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-enabled/*.conf 2>/dev/null || true
fi

exec apache2-foreground
