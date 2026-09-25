# ALLOCORE Foundation

Zentrales digitales Betriebssystem der DISAVO/ALLOCORE Unternehmensgruppe.

- Auftraggeber: DISAVO Holding GmbH
- Projektverantwortung: ALLOCORE GmbH
- Stack: Laravel 13 (Modular Monolith), Blade + Alpine + Tailwind, spatie/laravel-permission, stancl/tenancy (Single-DB, `tenant_id`), Sanctum-API `/api/v1`, MySQL 8
- Module: Core, Compliance, ExpertNetwork, CorporateDev, Investments, Participations, Production, HR, Finance, DataPlatform, DataLake, KnowledgeGraph, AI, Executive, Insights

> Wir bauen keine Compliance-Software. Wir bauen eine Plattform, auf der Compliance das erste Modul ist.

## Schnellstart

```bash
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan demo:seed {tenant_id}   # optionale Demodaten
php artisan serve                   # http://localhost:8000/login
```

Dokumentation: `docs/ALLOCORE_FOUNDATION_ROADMAP.md` (Roadmap v1.0), `docs/API_REFERENCE.md`, `docs/EVENT_CATALOG.md`, `docs/openapi.yaml`, `docs/DEPLOYMENT.md`, `docs/adr/`
