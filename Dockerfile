# Official PHP image
FROM php:8.2-apache

# Install PHP extensions & utilities needed by Composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo pdo_mysql

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Enable apache mod_rewrite
RUN a2enmod rewrite

# Point Apache DocumentRoot to public folder
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Set working dir
WORKDIR /var/www/html

RUN { \
    echo 'upload_max_filesize=6M'; \
    echo 'post_max_size=8M'; \
} > /usr/local/etc/php/conf.d/uploads.ini

# Install Composer dependencies (leveraging Docker layer cache)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader

# Copy project code into container
COPY . /var/www/html

# Storage for uploaded receipts
RUN mkdir -p /var/www/html/storage/receipts \
    && chown -R www-data:www-data /var/www/html/storage \
    && chmod -R 750 /var/www/html/storage
