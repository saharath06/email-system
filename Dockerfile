FROM php:8.2-cli

WORKDIR /app

RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libonig-dev \
    libzip-dev \
    unzip \
    zip \
    && docker-php-ext-install pdo pdo_mysql mysqli curl mbstring \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY . /app

CMD php -S 0.0.0.0:${PORT:-8080} -t /app