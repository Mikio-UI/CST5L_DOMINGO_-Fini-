FROM php:8.2-apache

# Cache bust
ARG CACHE_BUST=9

ENV APACHE_RUN_USER=www-data \
    APACHE_RUN_GROUP=www-data \
    APACHE_RUN_DIR=/var/run/apache2 \
    APACHE_PID_FILE=/var/run/apache2/apache2.pid \
    APACHE_LOCK_DIR=/var/lock/apache2 \
    APACHE_LOG_DIR=/var/log/apache2

# Install mysqli
RUN docker-php-ext-install mysqli

# Force prefork: disable event, enable prefork — both in one RUN so no layer can undo it
RUN a2dismod mpm_event mpm_worker || true \
 && a2enmod mpm_prefork \
 && rm -f /etc/apache2/mods-enabled/mpm_event.load \
          /etc/apache2/mods-enabled/mpm_event.conf \
          /etc/apache2/mods-enabled/mpm_worker.load \
          /etc/apache2/mods-enabled/mpm_worker.conf \
 && mkdir -p /var/run/apache2 /var/lock/apache2 /var/log/apache2

# Enable rewrite + override in the same RUN (no new a2en after this)
RUN a2enmod rewrite \
 && printf '<Directory /var/www/html>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
    > /etc/apache2/conf-enabled/override.conf

# Verify — build will FAIL here if mpm_event sneaks back in
RUN apache2ctl -t && ! ls /etc/apache2/mods-enabled/ | grep mpm_event

COPY . /var/www/html/
WORKDIR /var/www/html

CMD ["apache2ctl", "-D", "FOREGROUND"]
