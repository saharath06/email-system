FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    nginx \
    curl \
    && docker-php-ext-install pdo pdo_mysql mysqli

COPY . /var/www/html/
COPY nginx.conf /etc/nginx/nginx.conf

RUN chown -R www-data:www-data /var/www/html

EXPOSE 8080

CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]