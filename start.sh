#!/bin/bash
set -e

# Kill mpm_event at runtime before Apache starts
a2dismod mpm_event 2>/dev/null || true
rm -f /etc/apache2/mods-enabled/mpm_event.conf
rm -f /etc/apache2/mods-enabled/mpm_event.load
a2enmod mpm_prefork 2>/dev/null || true

exec apache2-foreground
