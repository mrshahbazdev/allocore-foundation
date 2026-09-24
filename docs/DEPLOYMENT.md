# Deployment (R2 Cloudunabhängigkeit)

Die App ist absichtlich **portabel**: sqlite default, kein Cloud-SDK,
Flysystem-basiertes Storage (`local` disk). Läuft überall, wo PHP 8.3 + Composer
laufen.

## Lokal / eigener Server / SiteGround

```sh
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --force
php artisan serve   # oder nginx/apache auf public/
```

## Docker

```sh
docker build -t allocore-foundation .
docker run -p 8000:8000 -e APP_KEY="$(php artisan key:generate --show)" allocore-foundation
```

## Fly.io

`fly.toml` liegt bei. `fly launch` → sqlite unter `storage/database.sqlite`
(für Persistenz ein Fly Volume auf `storage/` mounten oder `DB_CONNECTION`
auf Postgres zeigen — Schema ist DB-agnostisch).

## Scheduler (Reminders + Aggregation)

Produktiv: `php artisan schedule:run` per Cron (`* * * * *`) —
`tasks:remind`, `compliance:remind` stündlich, `analytics:aggregate` täglich.
Alternativ `php artisan queue:work` falls Queue aktiv.
