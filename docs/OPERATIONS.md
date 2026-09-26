# Betrieb (Operations-Runbook)

Kurz-Referenz für den produktiven Betrieb von ALLOCORE Foundation.

## Scheduler (Background-Jobs)

Der Container/DEV-Prozess muss `php artisan schedule:work` neben `php artisan serve` laufen lassen (Dockerfile CMD macht das bereits). Alternativ System-Cron: `* * * * * php artisan schedule:run`.

| Kommando | Intervall | Zweck |
|---|---|---|
| `compliance:remind` | stündlich | Erinnerungs-Benachrichtigungen (Unterweisung, Prüfung, Frist, Maßnahme, GB-Review, Auftrag, Ausschreibung, Projekt, Audit, Unterweisung-Wiederholung) |
| `tasks:remind` | stündlich | Erinnerung an fällige Aufgaben (Assignee) |
| `insights:notify` | täglich | kritische Insights → `hinweis`-Benachrichtigung an Mitglieder mit `roles.manage` (dedupliziert, 30-Tage-Ablauf) |
| `insights:notify --warnings` | montags | zusätzlich Warnungen statt nur kritisch |
| `analytics:aggregate` | täglich | füllt `metric_snapshots` (KPI-Verlauf, Trends) |
| `notifications:prune` | täglich | gelesene Benachrichtigungen > 90 Tage + Insight-Dedupe-Keys > 60 Tage löschen |
| `tokens:prune` | täglich | abgelaufene/alter API-Tokens entfernen |

Weitere Optionen: `insights:notify --tenant=<id>` für einen einzelnen Mandanten.

## Nützliche Kommandos

- `demo:seed {tenant}` — realistische Demo-Daten für alle Module (idempotent), benachrichtigt Mandanten-Mitglieder einmal (`hinweis`).
- `php artisan migrate` — Modul-Migrationen laufen über `nwidart/laravel-modules`.
- `php artisan test --compact` — komplette Testsuite.
- `vendor/bin/pint` — Code-Style (vor jedem Commit).

## Health & Metriken

- `GET /api/v1/health` — DB + Migrations-Readiness (`503` bei degraded)
- `GET /api/v1/version` — Build-/Betriebs-Infos
- `GET /api/v1/metrics` — KPI des Mandanten (`X-Tenant` Header)
- `GET /api/v1/events/export` — NDJSON-Audit-Export aller Mandanten-Events

## Auth & Mandantenkontext

- `X-Tenant: <uuid>` Header auf fast allen Endpunkten — Mitgliedschaft wird strikt geprüft (`403`).
- Rate-Limits: Auth-Endpoints 6/min pro IP; `throttle:api` auf allen `/api/v1` Gruppen.
- Passwort-Policy: min. 12 Zeichen, Groß-/Kleinbuchstabe + Zahl.
- Benachrichtigungs-Arten (`muted_kinds` via `PUT /me/notification-prefs`) — siehe `docs/API_REFERENCE.md` `/notifications`.

## Retention

- Gelesene Benachrichtigungen: 90 Tage (`notifications:prune --days=N` zum Überschreiben).
- Insight-Dedupe-Keys: 60 Tage.
- API-Tokens: abgelaufen sofort, restliche nach Langzeitregel (`tokens:prune`).

## CI/CD

- GitHub Actions: Pint + `php artisan test` pro PR.
- `automerge`-Label: Workflow merged PRs mit grünen Checks automatisch (Squash).
- Deployment: `docs/DEPLOYMENT.md` (Dockerfile, fly.toml).
