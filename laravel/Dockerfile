# Fase 1: Laravel 11 + Kafka (producer/consumer com SASL)
FROM php:8.4-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev libicu-dev librdkafka-dev unzip git \
    && docker-php-ext-install zip intl pcntl bcmath opcache \
    && pecl install rdkafka \
    && docker-php-ext-enable rdkafka \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV PATH="${PATH}:/app/vendor/bin"

WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
