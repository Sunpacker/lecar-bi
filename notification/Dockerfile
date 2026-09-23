FROM php:8.3-cli-bookworm

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev \
        curl \
        unzip \
        git \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

RUN groupadd --gid 1001 app \
    && useradd --uid 1001 --gid app --create-home app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Composer lock-файл обязателен для воспроизводимой сборки.
# Сначала копируем только файлы зависимостей, чтобы Docker мог кешировать этот слой.
COPY composer.json composer.lock ./

RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --no-dev \
    --no-scripts

COPY . .

# После копирования приложения пересобираем production-autoload.
RUN composer dump-autoload \
    --optimize \
    --no-dev \
    --no-interaction \
    && mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R app:app storage bootstrap/cache \
    && chmod -R ug+rw storage bootstrap/cache

USER app
EXPOSE 8081

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8081"]
