# Use official PHP 8.1 Apache image
FROM php:8.1-apache

# Install PDO and PostgreSQL extensions
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Copy your project into the container
COPY . /var/www/html/

# Enable Apache rewrite module if needed
RUN a2enmod rewrite

# Expose Apache's default port
EXPOSE 80

