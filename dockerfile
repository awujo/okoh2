# Use the official PHP + Apache image
FROM php:8.2.12-apache

# Set working directory
WORKDIR /var/www/html

# Copy app source code
COPY . .

# Copy custom php.ini from project root
COPY php.ini /usr/local/etc/php/php.ini

# Install dependencies and PHP extensions
RUN apt-get update && \
    apt-get install -y libpng-dev && \
    docker-php-ext-install pdo pdo_mysql gd mysqli && \
    a2enmod rewrite && \
    sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Optional: create log directory for PHP errors
RUN mkdir -p /var/log && touch /var/log/php_errors.log

# Expose Apache port
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]