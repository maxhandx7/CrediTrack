# ---------- STAGE 1: BUILD FRONTEND ----------
FROM node:18 AS node_builder

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .
RUN npm run build


# ---------- STAGE 2: PHP + NGINX ----------
FROM php:8.2-fpm

# Instalar dependencias necesarias
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    nginx \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && apt-get clean

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configurar directorio
WORKDIR /var/www

# Copiar proyecto
COPY . .

# Copiar build de Vite
COPY --from=node_builder /app/public/build ./public/build

# Instalar dependencias PHP
RUN composer install --no-dev --optimize-autoloader

# ... (después de composer install)

# Asegurar que el directorio de logs existe antes de cambiar dueños
RUN mkdir -p /var/www/storage/logs /var/www/storage/framework/sessions /var/www/storage/framework/views /var/www/storage/framework/cache

# Cambiar el dueño de TODO el proyecto a www-data (el usuario de PHP-FPM y Nginx)
RUN chown -R www-data:www-data /var/www

# Aplicar permisos específicos
RUN chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Eliminar config default de nginx
RUN rm -f /etc/nginx/sites-enabled/default

# Configurar PHP-FPM para escuchar en puerto 9000
RUN sed -i 's|listen = .*|listen = 9000|' /usr/local/etc/php-fpm.d/www.conf

# Copiar config nginx
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

# Crear el archivo de log y dar permiso explícito
RUN touch /var/www/storage/logs/laravel.log && chmod 664 /var/www/storage/logs/laravel.log && chown www-data:www-data /var/www/storage/logs/laravel.log

CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
