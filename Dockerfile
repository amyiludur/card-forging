# Card Forge in one container: PHP to serve the app, Node to build the frontend,
# and Chromium so the PDF export works without anything installed on the host.
FROM php:8.3-cli-bookworm

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    CHROMIUM_BINARY=/usr/bin/chromium

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libicu-dev libonig-dev ca-certificates curl gnupg \
        chromium fonts-dejavu-core \
    && docker-php-ext-install -j"$(nproc)" pdo_sqlite zip intl \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependencies first, so editing the app does not reinstall them.
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader

COPY package.json package-lock.json ./
RUN npm ci --no-fund --no-audit

COPY . .

RUN composer dump-autoload --optimize && npm run build

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
