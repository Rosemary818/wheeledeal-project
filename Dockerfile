# Use an official PHP image with Apache
FROM php:8.1-apache

# Install extensions for PHP (like mysqli)
RUN docker-php-ext-install mysqli

# Copy all your project files to the Apache root directory
COPY . /var/www/html/

# Enable Apache mod_rewrite if needed (for clean URLs)
RUN a2enmod rewrite

# Expose port 80
EXPOSE 80
