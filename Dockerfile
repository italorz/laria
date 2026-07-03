# syntax=docker/dockerfile:1

# Laria — imagem de produção para Coolify.
# PHP-FPM + nginx + fila (supervisor) + Node/Chromium para o scraper Puppeteer.

############################################
# Stage 1 — build dos assets Vite (Node)
############################################
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

############################################
# Stage 2 — runtime (PHP-FPM + nginx + Node + Chromium)
############################################
FROM php:8.4-fpm-bookworm AS runtime

# Dependências de sistema: nginx, supervisor, libs das extensões PHP e Chromium.
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        unzip \
        git \
        ca-certificates \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        chromium \
        fonts-liberation \
        fonts-noto-color-emoji \
    && rm -rf /var/lib/apt/lists/*

# Extensões PHP exigidas: Postgres, GD (compressão p/ Gemini), Redis, fila, etc.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql gd zip intl bcmath exif pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis

# Node.js 22 (reaproveitado da imagem oficial) para rodar o scraper Puppeteer.
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

# Composer (binário da imagem oficial).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Puppeteer usa o Chromium do sistema — não baixa o próprio.
ENV PUPPETEER_SKIP_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium

# Ajustes de PHP para produção (upload de fotos).
RUN { \
        echo 'upload_max_filesize=32M'; \
        echo 'post_max_size=40M'; \
        echo 'memory_limit=512M'; \
        echo 'max_execution_time=120'; \
    } > /usr/local/etc/php/conf.d/laria.ini

WORKDIR /var/www/html

# Código-fonte + vendor + assets já buildados.
COPY . .
COPY --from=assets /app/public/build ./public/build

# Dependências PHP (produção) e do scraper (usa o Chromium do sistema).
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist \
    && (cd scraper && npm ci --omit=dev)

# Permissões para o www-data (nginx/php-fpm rodam como www-data).
RUN chown -R www-data:www-data storage bootstrap/cache

# Configs de runtime.
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/laria.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
