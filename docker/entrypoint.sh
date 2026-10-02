#!/bin/sh
set -e

# Render asigna el puerto mediante la variable de entorno $PORT (por defecto 80)
export SERVER_NAME=":${PORT:-80}"

# Asegurar directorios de almacenamiento y permisos
mkdir -p /app/storage/framework/cache/data \
         /app/storage/framework/sessions \
         /app/storage/framework/views \
         /app/storage/logs \
         /app/bootstrap/cache

chown -R www-data:www-data /app/storage /app/bootstrap/cache
chmod -R 775 /app/storage /app/bootstrap/cache

# Si se usa SQLite (por defecto o configurado), asegurar que el archivo exista
if [ "$DB_CONNECTION" = "sqlite" ] || [ -z "$DB_CONNECTION" ]; then
    if [ ! -f /app/database/database.sqlite ]; then
        touch /app/database/database.sqlite
    fi
    chown www-data:www-data /app/database/database.sqlite
fi

# Ejecutar migraciones si la base de datos está disponible
if [ -n "$DATABASE_URL" ] || [ -n "$DB_URL" ] || [ -n "$DB_HOST" ] || [ "$DB_CONNECTION" = "sqlite" ] || [ -z "$DB_CONNECTION" ]; then
    echo "Ejecutando migraciones de base de datos..."
    php artisan migrate --force || echo "Aviso: No se pudieron ejecutar las migraciones en este inicio."
fi

# Crear symlink de almacenamiento público si no existe
php artisan storage:link --quiet || true

# Optimizar caché de Laravel para producción si la clave de app está lista
if [ -n "$APP_KEY" ]; then
    echo "Optimizando configuración de Laravel..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

echo "Iniciando FrankenPHP en el puerto ${PORT:-80}..."
exec frankenphp run --config /etc/caddy/Caddyfile
