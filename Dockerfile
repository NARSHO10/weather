# Dockerfile for Weather App - ready for Render (PHP + Apache)
# Based on official PHP Apache image

FROM php:8.2-apache

# Install useful PHP extensions and certificates for HTTPS requests
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev \
        zip \
        unzip \
        libonig-dev \
        libxml2-dev \
        ca-certificates \
    && docker-php-ext-install pdo_mysql mbstring xml zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Copy application files
WORKDIR /var/www/html
COPY . /var/www/html/

# Ensure files owned by the www-data user
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Allow .htaccess overrides (so RewriteRules and headers work)
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Expose the HTTP port
EXPOSE 80

# Start Apache in the foreground
CMD ["apache2-foreground"]
