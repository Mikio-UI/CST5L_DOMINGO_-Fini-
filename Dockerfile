FROM php:8.2-apache

# Force only mpm_prefork by directly managing the config files
RUN cd /etc/apache2/mods-enabled \
 && rm -f mpm_event.conf mpm_event.load mpm_worker.conf mpm_worker.load mpm_prefork.conf mpm_prefork.load \
 && ln -sf ../mods-available/mpm_prefork.conf mpm_prefork.conf \
 && ln -sf ../mods-available/mpm_prefork.load mpm_prefork.load

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

EXPOSE 80
