# syntax=docker/dockerfile:1
FROM node:24-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
# Include app/config Tailwind sources and the optional derived-project profile.
# .dockerignore excludes secrets, local runtime state and installed dependencies.
COPY . .
RUN npm run build && test -f public/build/manifest.json

# The same PHP extensions are used for Composer, CI and production.
FROM php:8.3-fpm-alpine AS php-base
RUN apk add --no-cache bash curl git unzip zip nginx supervisor su-exec tzdata icu-data-full libstdc++ ffmpeg font-dejavu \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev libwebp-dev oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo_mysql mbstring bcmath intl zip gd pcntl opcache \
    && pecl install redis-6.1.0 && docker-php-ext-enable redis \
    && apk add --no-cache icu-libs libzip libpng libjpeg-turbo freetype libwebp oniguruma \
    && apk del .build-deps \
    && mkdir -p /usr/share/fonts/truetype \
    && ln -s /usr/share/fonts/dejavu /usr/share/fonts/truetype/dejavu \
    && test -f /usr/share/fonts/truetype/dejavu/DejaVuSans.ttf
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN printf '%s\n' 'short_open_tag=Off' 'upload_max_filesize=512M' 'post_max_size=520M' > /usr/local/etc/php/conf.d/dbcms-compatibility.ini
WORKDIR /var/www/html

FROM php-base AS ci
RUN apk add --no-cache apache2 apache2-proxy
COPY --from=frontend /usr/local/bin/node /usr/local/bin/node
COPY --from=frontend /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx
COPY . .
RUN mkdir -p storage/framework/cache/data storage/framework/views storage/framework/sessions storage/logs bootstrap/cache \
    && git config --global --add safe.directory /var/www/html
CMD ["sh"]

FROM php-base AS vendor
COPY . .
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/views storage/framework/sessions storage/logs \
    && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress

FROM php-base AS runtime
LABEL org.opencontainers.image.source="https://github.com/dbravoan/dbcms"
RUN adduser -D -u 1000 -g dbcms dbcms \
    && mkdir -p /var/log/supervisor /var/log/nginx \
    && printf '%s\n' 'opcache.enable=1' 'opcache.memory_consumption=256' 'opcache.validate_timestamps=0' > /usr/local/etc/php/conf.d/opcache-production.ini \
    && sed -i 's/^user = .*/user = dbcms/; s/^group = .*/group = dbcms/' /usr/local/etc/php-fpm.d/www.conf
COPY --chown=root:root . .
COPY --from=vendor --chown=root:root /var/www/html/vendor ./vendor
COPY --from=frontend --chown=root:root /app/public/build ./public/build
COPY --from=frontend --chown=root:root /app/public/fonts ./public/fonts
COPY docker/production/nginx.conf /etc/nginx/nginx.conf
COPY docker/production/supervisord.conf /etc/supervisord.conf
COPY docker/production/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p storage/app/public storage/framework/cache/data storage/framework/views storage/framework/sessions storage/logs bootstrap/cache public/uploads \
    && chown -R dbcms:dbcms storage bootstrap/cache public/uploads \
    && chmod 755 /var/www/html \
    && find storage bootstrap/cache -type d -exec chmod 775 {} + \
    && ln -s storage/app/runtime/.env .env
EXPOSE 80
HEALTHCHECK --interval=15s --timeout=5s --start-period=30s --retries=3 CMD curl -fsS http://127.0.0.1/up || exit 1
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
