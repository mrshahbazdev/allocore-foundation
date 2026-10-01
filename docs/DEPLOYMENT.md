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
php artisan serve   # dev only — prod: nginx/apache/php-fpm auf public/
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

Drei Prozess-Gruppen laufen aus demselben Image (`[processes]`):

- `app` — FrankenPHP/Caddy (PHP 8.3, `php_server` auf `public/`)
- `scheduler` — `php artisan schedule:work`
- `worker` — `php artisan queue:work database`

Der `app-entrypoint` führt `migrate --force` + `config:cache` nur im Web-Prozess aus.

## Scheduler + Queue

- **Scheduler**: im Container läuft `schedule:work` als eigener Prozess
  (`[processes]` in fly.toml); außerhalb `php artisan schedule:run` per Cron
  (`* * * * *`).
- **Queue** (`QUEUE_CONNECTION=database`): ein Worker-Prozess
  `php artisan queue:work database --sleep=3 --tries=3` läuft dauerhaft
  (eigener fly-Prozess `worker`). Auf Shared-Hosting ohne langlaufende
  Prozesse: Cron-Alternative `* * * * * php artisan queue:work --stop-when-empty`.
