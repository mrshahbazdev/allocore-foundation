#!/bin/sh
set -e

# Only the web process boots the app fully (migrations + cached config).
# Scheduler/worker processes skip this and exec their command directly —
# every process shares the same image and storage volume.
case "$1" in
    frankenphp*)
        php artisan migrate --force
        php artisan config:cache
        ;;
esac

exec "$@"
