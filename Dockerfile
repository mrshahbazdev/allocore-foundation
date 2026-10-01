FROM node:20-bookworm-slim AS assets
WORKDIR /app
COPY package*.json vite.config.js ./
RUN npm ci
COPY resources ./resources
RUN npm run build

FROM dunglas/frankenphp:php8.3-bookworm

RUN install-php-extensions pdo_mysql pdo_sqlite zip gd pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint \
    && composer install --no-dev --optimize-autoloader --no-interaction

COPY --from=assets /app/public/build ./public/build

ENV APP_ENV=production \
    APP_DEBUG=false \
    DB_CONNECTION=mysql \
    DB_HOST=mysql \
    DB_PORT=3306 \
    DB_DATABASE=allocore \
    QUEUE_CONNECTION=database \
    SERVER_NAME=:8000

EXPOSE 8000

# Default process: web server. fly.toml/docker-compose can override the
# command to run the scheduler or a queue worker as separate processes:
#   app       → frankenphp run --config /etc/caddy/Caddyfile
#   scheduler → php artisan schedule:work
#   worker    → php artisan queue:work
ENTRYPOINT ["app-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
