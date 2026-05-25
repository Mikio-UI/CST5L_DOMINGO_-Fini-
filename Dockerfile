FROM php:8.2-apache

# Cache bust
ARG CACHE_BUST=8

# Required Apache runtime env vars
ENV APACHE_RUN_USER=www-data \
    APACHE_RUN_GROUP=www-data \
    APACHE_RUN_DIR=/var/run/apache2 \
    APACHE_PID_FILE=/var/run/apache2/apache2.pid \
    APACHE_LOCK_DIR=/var/lock/apache2 \
    APACHE_LOG_DIR=/var/log/apache2

# Install mysqli
RUN docker-php-ext-install mysqli

# Nuclear option: delete ALL mod symlinks, then manually add only what we need
RUN rm -f /etc/apache2/mods-enabled/* \
 && ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
 && ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
 && ln -s /etc/apache2/mods-available/authz_core.load  /etc/apache2/mods-enabled/authz_core.load \
 && ln -s /etc/apache2/mods-available/authz_host.load  /etc/apache2/mods-enabled/authz_host.load \
 && ln -s /etc/apache2/mods-available/authz_user.load  /etc/apache2/mods-enabled/authz_user.load \
 && ln -s /etc/apache2/mods-available/authn_core.load  /etc/apache2/mods-enabled/authn_core.load \
 && ln -s /etc/apache2/mods-available/authn_file.load  /etc/apache2/mods-enabled/authn_file.load \
 && ln -s /etc/apache2/mods-available/auth_basic.load  /etc/apache2/mods-enabled/auth_basic.load \
 && ln -s /etc/apache2/mods-available/access_compat.load /etc/apache2/mods-enabled/access_compat.load \
 && ln -s /etc/apache2/mods-available/alias.load       /etc/apache2/mods-enabled/alias.load \
 && ln -s /etc/apache2/mods-available/alias.conf       /etc/apache2/mods-enabled/alias.conf \
 && ln -s /etc/apache2/mods-available/dir.load         /etc/apache2/mods-enabled/dir.load \
 && ln -s /etc/apache2/mods-available/dir.conf         /etc/apache2/mods-enabled/dir.conf \
 && ln -s /etc/apache2/mods-available/env.load         /etc/apache2/mods-enabled/env.load \
 && ln -s /etc/apache2/mods-available/filter.load      /etc/apache2/mods-enabled/filter.load \
 && ln -s /etc/apache2/mods-available/headers.load     /etc/apache2/mods-enabled/headers.load \
 && ln -s /etc/apache2/mods-available/mime.load        /etc/apache2/mods-enabled/mime.load \
 && ln -s /etc/apache2/mods-available/mime.conf        /etc/apache2/mods-enabled/mime.conf \
 && ln -s /etc/apache2/mods-available/rewrite.load     /etc/apache2/mods-enabled/rewrite.load \
 && ln -s /etc/apache2/mods-available/setenvif.load    /etc/apache2/mods-enabled/setenvif.load \
 && ln -s /etc/apache2/mods-available/setenvif.conf    /etc/apache2/mods-enabled/setenvif.conf \
 && ln -s /etc/apache2/mods-available/reqtimeout.load  /etc/apache2/mods-enabled/reqtimeout.load \
 && ln -s /etc/apache2/mods-available/reqtimeout.conf  /etc/apache2/mods-enabled/reqtimeout.conf \
 && ln -s /etc/apache2/mods-available/php8.2.load      /etc/apache2/mods-enabled/php8.2.load \
 && ln -s /etc/apache2/mods-available/php8.2.conf      /etc/apache2/mods-enabled/php8.2.conf \
 && mkdir -p /var/run/apache2 /var/lock/apache2 /var/log/apache2

# Write Apache directory config directly — no a2enconf
RUN printf '<Directory /var/www/html>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
    > /etc/apache2/conf-enabled/override.conf

# Verify no mpm_event present
RUN echo "=== MODS ENABLED ===" && ls /etc/apache2/mods-enabled/ && apache2ctl -t

# Copy project files
COPY . /var/www/html/

WORKDIR /var/www/html

CMD ["apache2", "-D", "FOREGROUND"]
