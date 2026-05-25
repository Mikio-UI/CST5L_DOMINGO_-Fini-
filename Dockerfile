FROM php:8.2-apache

# Cache bust
ARG CACHE_BUST=4

# Set required Apache environment variables
ENV APACHE_RUN_USER=www-data \
    APACHE_RUN_GROUP=www-data \
    APACHE_RUN_DIR=/var/run/apache2 \
    APACHE_PID_FILE=/var/run/apache2/apache2.pid \
    APACHE_LOCK_DIR=/var/lock/apache2 \
    APACHE_LOG_DIR=/var/log/apache2

# Wipe ALL mods-enabled, then re-enable only what's needed
RUN rm -rf /etc/apache2/mods-enabled/* \
 && for mod in \
      mpm_prefork.conf mpm_prefork.load \
      rewrite.load \
      authz_core.load \
      authz_host.load \
      access_compat.load \
      auth_basic.load \
      authn_core.load \
      authn_file.load \
      authz_user.load \
      alias.conf alias.load \
      dir.conf dir.load \
      mime.conf mime.load \
      env.load \
      setenvif.conf setenvif.load \
      filter.load \
      headers.load \
      reqtimeout.conf reqtimeout.load \
    ; do \
      [ -f /etc/apache2/mods-available/$mod ] && \
        ln -s /etc/apache2/mods-available/$mod /etc/apache2/mods-enabled/$mod || true; \
    done \
 && mkdir -p /var/run/apache2 /var/lock/apache2 /var/log/apache2

# Install mysqli extension
RUN docker-php-ext-install mysqli

# Copy project files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Allow .htaccess overrides
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/override.conf \
 && a2enconf override

# Verify config at build time
RUN apache2ctl -t

# Write a startup script that dumps loaded MPMs before starting
RUN echo '#!/bin/bash\n\
echo "=== MODS AT RUNTIME ==="\n\
ls /etc/apache2/mods-enabled/\n\
echo "=== STARTING APACHE ==="\n\
exec apache2 -D FOREGROUND\n\
' > /usr/local/bin/start-apache.sh \
 && chmod +x /usr/local/bin/start-apache.sh

CMD ["/usr/local/bin/start-apache.sh"]
