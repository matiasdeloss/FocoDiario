# -------------------------------------------------------------
# Etapa 1: Compilar assets de frontend (Vite)
# -------------------------------------------------------------
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci || npm install
COPY . .
RUN npm run build

# -------------------------------------------------------------
# Etapa 2: Instalar dependencias PHP (Composer)
# -------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer*.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# -------------------------------------------------------------
# Etapa 3: Imagen de producción con FrankenPHP (PHP 8.4)
# -------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.4

# Instalar extensiones necesarias para Laravel y PostgreSQL
RUN install-php-extensions \
    pdo_pgsql \
    pcntl \
    bcmath

# Quitar la capability de archivo de FrankenPHP: Render no permite ejecutar
# binarios con capabilities y el puerto 10000 no necesita privilegios
RUN setcap -r /usr/local/bin/frankenphp

WORKDIR /app

# Copiar código de la aplicación
COPY . /app

# Copiar dependencias de Composer y assets compilados
COPY --from=vendor /app/vendor /app/vendor
COPY --from=frontend /app/public/build /app/public/build

# Configurar permisos para almacenamiento y caché
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

# Configurar script de inicio
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80 443 10000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
