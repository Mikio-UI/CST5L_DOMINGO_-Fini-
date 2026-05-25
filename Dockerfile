FROM php:8.2-apache

# Cache bust
ARG CACHE_BUST=6

# Set required Apache environment variables
ENV APACHE_RUN_USER=www-data \
    APACHE_RUN_GROUP=www-data \
    APACHE_RUN_DIR=/var/run/apache2 \
    APACHE_PID_FILE=/var/run/apache2/apache2.pid \
    APACHE_LOCK_DIR=/var/lock/apache2 \
    APACHE_LOG_DIR=/var/log/apache2

# Install mysqli extension FIRST (it may re-enable mpm_event)
RUN docker-php-ext-install mysqli

# Disable mpm_event explicitly, then enable prefork
RUN a2dismod mpm_event || true \
 && a2enmod mpm_prefork

# NOW wipe ALL mods-enabled and re-enable only what we need (no a2enmod calls after this)
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
      php8.2.conf php8.2.load \
    ; do \
      [ -f /etc/apache2/mods-available/$mod ] && \
        ln -s /etc/apache2/mods-available/$mod /etc/apache2/mods-enabled/$mod || true; \
    done \
 && mkdir -p /var/run/apache2 /var/lock/apache2 /var/log/apache2

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

# Verify — must say Syntax OK with NO mpm_event
RUN echo "=== FINAL MODS ===" && ls /etc/apache2/mods-enabled/ && apache2ctl -t

CMD ["apache2", "-D", "FOREGROUND"]
