# Official PHP image
FROM php:8.2-apache

# Install PHP extensions; pdo & mariadb driver
RUN docker-php-ext-install pdo pdo_mysql

# Enable apache
RUN a2enmod rewrite

# Point Apache DocumentRoot to public folder
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Set working dir
WORKDIR /var/www/html

# Copy project code into container
COPY . /var/www/html
