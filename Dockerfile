FROM php:8.2-apache

# Remove ALL MPM symlinks, enable only prefork
RUN rm -f /etc/apache2/mods-enabled/mpm_*.conf \
          /etc/apache2/mods-enabled/mpm_*.load \
 && ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
 && ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load

# Enable mod_rewrite for .htaccess
RUN a2enmod rewrite

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

# Set required Apache environment variables
ENV APACHE_RUN_USER=www-data
ENV APACHE_RUN_GROUP=www-data
ENV APACHE_RUN_DIR=/var/run/apache2
ENV APACHE_PID_FILE=/var/run/apache2/apache2.pid
ENV APACHE_LOCK_DIR=/var/lock/apache2
ENV APACHE_LOG_DIR=/var/log/apache2

# Ensure runtime dirs exist
RUN mkdir -p /var/run/apache2 /var/lock/apache2 /var/log/apache2

# Verify config at build time
RUN apache2ctl -t

CMD ["apache2", "-D", "FOREGROUND"]
