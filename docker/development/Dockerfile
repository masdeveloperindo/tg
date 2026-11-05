FROM composer:2@sha256:5190d9b445add2fa26090108273cb1ef1e1af98175201c737b85b3c4f90fbf7c AS composer

FROM php:8.4-cli-bookworm@sha256:54b467ede1db96c66675e7b78457dd43729d0e7be31978d4a1e5c069ceabcf76

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpng-dev libjpeg62-turbo-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j2 bcmath pdo_mysql gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --chmod=0644 uploads.ini /usr/local/etc/php/conf.d/tg-uploads.ini
WORKDIR /app
ENV COMPOSER_HOME=/tmp/composer
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
