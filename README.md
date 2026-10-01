# ALLOCORE Foundation

Zentrales digitales Betriebssystem der DISAVO/ALLOCORE Unternehmensgruppe.

- Auftraggeber: DISAVO Holding GmbH
- Projektverantwortung: ALLOCORE GmbH
- Stack: Laravel 13 (Modular Monolith), Blade + Alpine + Tailwind, spatie/laravel-permission, Sanctum-API `/api/v1`, MySQL 8
- Module (17): Ai, Audits, Compliance, Core, CorporateDev, DataLake, DataPlatform, Documents, Executive, ExpertNetwork, Finance, Hr, Investments, KnowledgeGraph, Participations, Production, Tasks

> Wir bauen keine Compliance-Software. Wir bauen eine Plattform, auf der Compliance das erste Modul ist.

## Schnellstart

```bash
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan demo:seed {tenant_id}   # optionale Demodaten (idempotent)
php artisan serve                   # http://localhost:8000/login
```

## Enterprise-Intelligence-Layer

1. **Identity** — Sanctum-Auth, Personal Access Tokens mit Abilities, Passwort-Policy, Login-Alerts
2. **Stammdaten** — Unternehmen, Personen, Dokumente, Aufgaben
3. **Event Store** — `stored_events` je Mandant, `/events` + Filter-/Aggregate-API (`summary`, `export` NDJSON, `actors`/`types`/`subjects`/`actions`/`groups`/`subject-types`)
4. **Data Lake** — mandanten-scoped Objekt-Store (`/data-objects`)
5. **Data Warehouse** — `metric_snapshots`, `/metrics` mit Sparklines, `/analytics/trends`
6. **Knowledge Graph** — Entitäten + Kanten + Traversierung (`/graph-*`)
7. **Analytics** — Trend-Deltas, Summaries, Histogramme
8. **AI** — Provider-agnostische Analysen (`/ai-analyses`, Heuristik-Provider)
9. **Executive** — mandanten-übergreifende Holding-Übersicht + Reports

## Fach-Domänen

- **Compliance**: Unterweisungen (inkl. Wiederholungsintervalle), Prüfungen, Fristen, Gefährdungsbeurteilungen, Betriebsanweisungen
- **Audits**: Audits + Feststellungen, Schweregrade, Fristen
- **Expertennetzwerk**: Profile, Q&A (Fragen/Antworten/Akzeptieren), Ausschreibungen + Bewerbungen
- **CorporateDev**: Strategien, Projekte, Maßnahmen
- **Kapital**: Portfolios, Investments, Beteiligungen
- **Produktion**: Maschinen, Produktionsaufträge
- **HR**: Abwesenheiten (Urlaub/Krankheit), Team + Rollen pro Mandant
- **Finanzen**: monatliche Finanzberichte, Umsatz/EBITDA/Liquidität-KPIs

## Workspace (`/app`)

Single-Page-Workspace (Blade + Alpine): Listen mit Suche/Sortierung/Gruppierung/Spalten-Picker, Detail-Drawer mit Verlauf + Verknüpfungen, Create/Edit/Duplizieren/CSV-Import+Export, Bulk-Aktionen mit Rückgängig, gespeicherte Ansichten, globale Suche (`/api/v1/search`), Ctrl+K-Palette, Glocke + Benachrichtigungen-Sektion, Dark Mode, ~30 Tastenkürzel (`?` im UI).

## Benachrichtigungen & Automatisierung

- Datenbank-Benachrichtigungen (`/notifications`) mit voller Filter-Matrix (`?kind=`, `?code=`, `?muted=`, `?unread=`, `?q=`, `?day=`, `?hour=`, `?weekday=`, `?due_*`, `?dir=`, Export/Batch/Stats)
- Scheduler: `tasks:remind`, `compliance:remind` (10 Erinnerungs-Arten), `insights:notify` (kritisch täglich, Warnungen montags), `notifications:prune`, `tokens:prune`, `metrics:snapshot`
- ~90 Insight-Codes (`/insights`) — überfällig/bald-fällig/fehlende Zuordnung je Domäne, mit Dedupe
- Ereignisse mit Auslöser-Attribution (`meta_data.actor`)

## Sicherheit

Strikte Mandanten-Mitgliedschaft (`X-Tenant` → 403), RBAC mit `*.view`/`*.manage` pro Domäne, Rollen-Editor, Lockout-Guards (letzter roles.manager), Escalation-Guard bei Rollen-Vergabe, tenant-scoped FK-Validierung, Rate-Limits (Auth + API), Security-Headers, Token-Widerruf bei Passwort-Änderung.

## Deploy

- Docker + fly.toml (siehe `docs/DEPLOYMENT.md`) — FrankenPHP/Caddy als Webserver; `app`, `scheduler`, `worker` als eigene Prozesse
- Apache/Shared Hosting: Root-`.htaccess` routet in `public/`; `public/build` ist committed (kein Node nötig)

## Tests

```bash
vendor/bin/pint -q && php artisan test   # ~280 Tests
```

Dokumentation: `docs/ALLOCORE_FOUNDATION_ROADMAP.md`, `docs/API_REFERENCE.md`, `docs/EVENT_CATALOG.md`, `docs/openapi.yaml`, `docs/DEPLOYMENT.md`, `docs/OPERATIONS.md`, `docs/adr/`
