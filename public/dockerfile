# Use an official PHP image with Apache server pre-installed
FROM php:8.2-apache

# Copy all your backend files into the default web server directory
COPY . /var/www/html/

# Enable Apache mod_rewrite (useful for PHP routing / REST APIs)
RUN a2enmod rewrite

# Expose port 80 for Render to route traffic to
EXPOSE 80
