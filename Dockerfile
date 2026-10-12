# CrediTrack — Laravel 13 + Filament 5. Nginx + PHP-FPM + cola + scheduler (supervisor).
FROM webdevops/php-nginx:8.4
ENV WEB_DOCUMENT_ROOT=/app/public \
    PHP_DATE_TIMEZONE=America/Bogota \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    PHP_UPLOAD_MAX_FILESIZE=10M \
    PHP_POST_MAX_SIZE=12M
WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan filament:assets \
    && chown -R application:application storage bootstrap/cache public \
    && chmod -R 775 storage bootstrap/cache

COPY docker/supervisor-laravel.conf /opt/docker/etc/supervisor.d/laravel.conf
COPY docker/entrypoint-laravel.sh /opt/docker/provision/entrypoint.d/30-laravel.sh
RUN chmod +x /opt/docker/provision/entrypoint.d/30-laravel.sh
EXPOSE 80
