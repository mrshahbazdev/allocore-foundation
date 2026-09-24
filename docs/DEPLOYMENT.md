# Deployment (R2 Cloudunabhängigkeit)

Die App ist absichtlich **portabel**: MySQL 8 als Default-Datenbank
(sqlite bleibt via `DB_CONNECTION=sqlite` möglich), kein Cloud-SDK,
Flysystem-basiertes Storage (`local` disk). Läuft überall, wo PHP 8.3 + Composer
+ MySQL laufen.

## Lokal / eigener Server / SiteGround

```sh
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env && php artisan key:generate
# MySQL-DB + User anlegen (siehe unten), dann:
php artisan migrate --force
php artisan serve   # oder nginx/apache auf public/
```

## MySQL (Dev-Setup)

```sh
docker run -d --name allocore-mysql \
    -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=allocore \
    -e MYSQL_USER=allocore -e MYSQL_PASSWORD=allocore \
    -p 3306:3306 mysql:8.0
```

## Docker

```sh
docker build -t allocore-foundation .
docker run -p 8000:8000 \
    -e APP_KEY="$(php artisan key:generate --show)" \
    -e DB_HOST=<mysql-host> -e DB_USERNAME=allocore -e DB_PASSWORD=... \
    allocore-foundation
```

## Fly.io

`fly.toml` liegt bei. `fly launch` → `DB_*` Secrets auf eine externe MySQL
(Fly MySQL, PlanetScale, RDS, …) zeigen — Schema ist DB-agnostisch.

## Scheduler (Reminders + Aggregation)

Produktiv: `php artisan schedule:run` per Cron (`* * * * *`) —
`tasks:remind`, `compliance:remind` stündlich, `analytics:aggregate` täglich.
Alternativ `php artisan queue:work` falls Queue aktiv.
