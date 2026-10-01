# Official PHP image
FROM php:8.2-apache

# Install PHP extensions; pdo & mariadb driver
RUN docker-php-ext-install pdo pdo_mysql

# Enable apache
RUN a2enmod rewrite

# Set working dir
WORKDIR /var/www/html

# Copy project code into container
COPY . /var/www/html
