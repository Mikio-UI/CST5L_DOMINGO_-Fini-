FROM php:8.2-apache

# Cache bust
ARG CACHE_BUST=2

# Set required Apache environment variables
ENV APACHE_RUN_USER=www-data \
    APACHE_RUN_GROUP=www-data \
    APACHE_RUN_DIR=/var/run/apache2 \
    APACHE_PID_FILE=/var/run/apache2/apache2.pid \
    APACHE_LOCK_DIR=/var/lock/apache2 \
    APACHE_LOG_DIR=/var/log/apache2

# Wipe ALL mods-enabled and only re-enable what we need
RUN rm -rf /etc/apache2/mods-enabled/* \
 && ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
 && ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
 && ln -s /etc/apache2/mods-available/rewrite.load /etc/apache2/mods-enabled/rewrite.load \
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

# Verify config at build time + show what MPMs are enabled
RUN echo "=== MODS ENABLED ===" \
 && ls /etc/apache2/mods-enabled/ \
 && echo "=== APACHE CONFIG TEST ===" \
 && apache2ctl -t

CMD ["apache2", "-D", "FOREGROUND"]
