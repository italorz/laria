# Deploy do Laria no Coolify (GitHub + Postgres + Redis)

Imagem de produção auto-contida (`Dockerfile`): **PHP-FPM 8.4 + nginx + fila (supervisor)
+ Node 22 + Chromium** para o scraper Puppeteer. Escuta na porta **8080**, health check
em **`/up`**.

> O repositório está **enraizado nesta pasta** (`web/`), então no Coolify o *Base Directory*
> é `/` e o *Dockerfile* é `./Dockerfile`.

## 1. Bancos gerenciados pelo Coolify

No projeto do Coolify, crie os dois recursos (mesmo servidor/projeto do app, para ficarem
na mesma rede Docker interna):

- **PostgreSQL** (v16): anote *database*, *user*, *password* e o **hostname interno**
  (Coolify mostra em "Internal URL" — algo como `postgresql-xxxxxxxx`).
- **Redis** (v7): anote a **senha** e o **hostname interno** (`redis-xxxxxxxx`).

Use os hostnames **internos** nas env vars abaixo (o app fala com os bancos pela rede
interna, não pelo IP público).

## 2. Aplicação (Dockerfile build pack)

- **Source:** o repositório do GitHub (público ou via GitHub App do Coolify).
- **Build Pack:** `Dockerfile`.
- **Port Exposes:** `8080`.
- **Health Check Path:** `/up`.
- **Persistent Storage (obrigatório):** volume montado em
  `/var/www/html/storage/app/public` — é onde ficam as fotos enviadas e as imagens geradas
  pela IA. Sem isso, tudo some a cada redeploy.

## 3. Variáveis de ambiente (aba *Environment Variables* do app)

```env
APP_NAME=Laria
APP_ENV=production
APP_KEY=base64:I46tk58kiADoqa4YpY4yUpmYhTW2vsaYDGmGQg8L4TM=
APP_DEBUG=false
APP_URL=https://SEU-DOMINIO

APP_LOCALE=en
LOG_CHANNEL=stack
LOG_LEVEL=info

# --- Postgres (hostname interno do Coolify) ---
DB_CONNECTION=pgsql
DB_HOST=postgresql-xxxxxxxx
DB_PORT=5432
DB_DATABASE=laria
DB_USERNAME=laria
DB_PASSWORD=COLE_A_SENHA_DO_POSTGRES

# --- Redis (sessão, cache e fila) ---
SESSION_DRIVER=redis
SESSION_LIFETIME=120
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=redis-xxxxxxxx
REDIS_PASSWORD=COLE_A_SENHA_DO_REDIS
REDIS_PORT=6379

FILESYSTEM_DISK=public

# --- IA / scraper ---
GEMINI_API_KEY=COLE_SUA_CHAVE
GEMINI_IMAGE_MODEL=gemini-2.5-flash-image
PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium

# OpenAI é opcional (fallback de geração de imagem)
OPENAI_API_KEY=
OPENAI_IMAGE_MODEL=gpt-image-1
OPENAI_IMAGE_QUALITY=low
```

> A `APP_KEY` acima foi gerada para produção. Se preferir, rode `php artisan key:generate --show`
> e use outra — mas mantenha-a fixa, senão sessões/cookies e dados cifrados quebram.
>
> **Rotacione a `GEMINI_API_KEY`** que estava no `.env` de desenvolvimento antes de ir a
> produção — ela não vai para o Git (está no `.gitignore`), mas já esteve exposta localmente.

## 4. O que o container faz sozinho no boot (`docker/entrypoint.sh`)

1. Cria as pastas de `storage/` e ajusta permissões do volume persistente.
2. `php artisan storage:link` (idempotente).
3. `php artisan migrate --force`.
4. `config:cache` + `view:cache` (**não** usamos `route:cache`: as rotas têm closures).
5. Sobe nginx + php-fpm + worker de fila via supervisor.

## 5. Deploy

Faça o *Deploy* no Coolify. Acompanhe os logs de build (o Chromium deixa a imagem em
~1 GB, o primeiro build é mais lento). Ao subir, o health check `/up` deve ficar verde.
Depois configure o domínio + TLS (Let's Encrypt) na aba *Domains* do app.
