FROM php:8.4-cli

# Estensioni PHP richieste da Laravel + MySQL + immagini
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libfreetype6-dev libzip-dev unzip git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

RUN composer install --no-dev --optimize-autoloader --no-interaction

ENV APP_ENV=production \
    APP_DEBUG=false \
    PORT=8000

RUN chmod +x docker/entrypoint.sh

EXPOSE 8000
CMD ["docker/entrypoint.sh"]
