# syntax=docker/dockerfile:1
# ============ Base PHP (dipakai semua stage PHP) ============
FROM php:8.4-fpm-alpine AS php-base
RUN apk add --no-cache bash icu-libs libzip libpng libjpeg-turbo freetype libwebp imagemagick oniguruma \
 && apk add --no-cache --virtual .build $PHPIZE_DEPS icu-dev libzip-dev libpng-dev libjpeg-turbo-dev \
        freetype-dev libwebp-dev imagemagick-dev oniguruma-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring intl zip gd bcmath opcache exif \
 && pecl install imagick redis \
 && docker-php-ext-enable imagick redis \
 && apk del .build
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-pool.conf

# ============ Composer (tanpa dev) ============
FROM php-base AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --no-progress --prefer-dist

# ============ Build aset Vite (butuh vendor: theme Filament di-import dari vendor/) ============
FROM node:20-alpine AS assets
WORKDIR /app
ARG VITE_PUSHER_APP_KEY
ARG VITE_PUSHER_APP_CLUSTER
ARG VITE_APP_NAME="ERP"
ENV VITE_PUSHER_APP_KEY=$VITE_PUSHER_APP_KEY \
    VITE_PUSHER_APP_CLUSTER=$VITE_PUSHER_APP_CLUSTER \
    VITE_APP_NAME=$VITE_APP_NAME
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ============ Image aplikasi (php-fpm, queue, scheduler) ============
FROM php-base AS app
WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
RUN rm -f bootstrap/cache/*.php public/hot public/build.zip public/error_log \
 && mkdir -p storage/app/public storage/app/private storage/framework/cache storage/framework/sessions \
             storage/framework/views storage/logs bootstrap/cache \
 && composer dump-autoload --optimize --no-dev --no-scripts \
 && BROADCAST_CONNECTION=log php artisan package:discover --ansi \
 && CACHE_STORE=array BROADCAST_CONNECTION=log php artisan filament:assets --ansi \
 && chown -R www-data:www-data storage bootstrap/cache
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ============ Image web (nginx + file publik) ============
FROM nginx:1.27-alpine AS web
COPY --from=app /var/www/html/public /var/www/html/public
RUN rm -rf /var/www/html/public/storage \
 && ln -s /var/www/html/storage/app/public /var/www/html/public/storage
COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
