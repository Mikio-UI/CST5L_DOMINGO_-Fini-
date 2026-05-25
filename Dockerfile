FROM php:8.2-apache

# Fix Apache MPM conflict — disable all MPMs first, then enable only prefork
RUN a2dismod mpm_event mpm_worker mpm_prefork 2>/dev/null || true \
 && a2enmod mpm_prefork

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
