# ── 1) Frontend React (Vite) ─────────────────────────────────
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

# ── 2) Laravel 13 + Nginx + cola + scheduler ─────────────────
FROM webdevops/php-nginx:8.4
ENV WEB_DOCUMENT_ROOT=/app/public \
    PHP_DATE_TIMEZONE=America/Bogota \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0
WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
COPY --from=frontend /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev \
    && chown -R application:application storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/supervisor-laravel.conf /opt/docker/etc/supervisor.d/laravel.conf
COPY docker/entrypoint-laravel.sh /opt/docker/provision/entrypoint.d/30-laravel.sh
RUN chmod +x /opt/docker/provision/entrypoint.d/30-laravel.sh
EXPOSE 80
