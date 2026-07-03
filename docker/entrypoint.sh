#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

echo "[entrypoint] Preparando storage..."
mkdir -p storage/app/public/uploads \
         storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs
# O volume persistente pode montar como root — devolve a posse ao www-data.
chown -R www-data:www-data storage bootstrap/cache || true

# Link público (idempotente) para servir uploads em /storage/...
php artisan storage:link || true

echo "[entrypoint] Rodando migrations..."
php artisan migrate --force --no-interaction || {
    echo "[entrypoint] ERRO: migrate falhou. Verifique DB_* e se o Postgres subiu." >&2
    exit 1
}

echo "[entrypoint] Cacheando config e views (sem route:cache — rotas usam closures)..."
php artisan config:cache
php artisan view:cache
php artisan event:cache || true

echo "[entrypoint] Pronto. Iniciando serviços."
exec "$@"
