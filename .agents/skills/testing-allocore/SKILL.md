---
name: testing-allocore
description: How to run and test the ALLOCORE Laravel app behind its forced-https asset scheme, including a local TLS-proxy workaround and demo credentials.
---

# Testing ALLOCORE (Laravel) locally

The app forces `URL::forceScheme('https')` whenever `APP_URL` starts with https (app/Providers/AppServiceProvider.php). In this repo `APP_URL` is a `*.preview.devinapps.com` https URL, so all `asset()`/route URLs are emitted as `https://<request-host>/...`.

Consequences for testing:
- `http://localhost:8000` (plain `php artisan serve`) renders UNSTYLED pages — the browser tries to fetch `https://localhost:8000/build/...` but artisan serve has no TLS. This is expected, not a broken fix.
- The `*.preview.devinapps.com` URL is gated behind app.devin.ai login — you cannot use it for automated testing.

## Workaround: local TLS terminator with socat

Simulates the https proxy locally; asset URLs then emit `https://localhost:8443/...` and load correctly:

```bash
cd /tmp
openssl req -x509 -newkey rsa:2048 -keyout key.pem -out cert.pem -days 1 -nodes -subj "/CN=localhost" -addext "subjectAltName=DNS:localhost"
cat cert.pem key.pem > proxy.pem
socat OPENSSL-LISTEN:8443,bind=127.0.0.1,cert=/tmp/proxy.pem,verify=0,fork,reuseaddr TCP:127.0.0.1:8000 &
```

Then browse `https://localhost:8443/...` (Chrome auto-proceeds or click through the self-signed warning).

## Credentials / data
- Login: `demo@allocore.local` / `demo1234`
- Tenant "ALLOCORE GmbH" id `2f324750-f81d-4127-b903-26aec5633241` — select it in the "Mandant:" dropdown on /dashboard.
- Tenant name is stored in the `tenants.data` JSON column (`{"name": ...}`), NOT a `name` column — querying `orderBy('name')`/`get(['name'])` 500s.

## Gotchas
- `resources/views/dashboard.blade.php` has had a bad merge leaving a truncated duplicate `load()`/`api()` block — check for syntax errors in the inline `<script>` if Alpine sections don't render.
- Dashboard APIs need `Authorization: Bearer <token>` + `X-Tenant: <uuid>` headers (the blade injects the token).
