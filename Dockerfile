cat > ~/email-system/Dockerfile << 'DOCKERFILE'
FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli curl mbstring

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
DOCKERFILE