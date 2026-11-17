# Promptedit

Generate images from a prompt and see the price before you hit generate.

Live at https://promptedit.oddegen.et

## Setup

You need PHP 8.5, Node 24 and Docker.

```bash
cp .env.example .env
```

Fill in `.env`:

```
DB_DATABASE=promptedit
DB_USERNAME=promptedit
DB_PASSWORD=secret
PIXELFORGE_API_KEY=
PIXELFORGE_WEBHOOK_SECRET=
```

Then:

```bash
docker compose up -d --wait
composer setup
php artisan db:seed
composer run dev
```

Log in as `test@example.com` / `password`.

## Tests

```bash
composer test
```

## Deploy

```bash
docker compose -f compose.production.yaml run --rm web php artisan migrate --force
docker compose -f compose.production.yaml up -d
docker compose -f compose.production.yaml run --rm web php artisan admin:create you@example.com
```
