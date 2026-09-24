# ADR-0001: Platform Foundation Decisions

Status: Accepted (v1.0 scaffold)
Date: 2026-09-24

## Context

ALLOCORE Foundation muss die Architekturregeln R1–R6 aus dem Entwicklungsauftrag erfüllen: keine Datensilos, Cloudunabhängigkeit, API First, Mandantenfähigkeit, Event First, Dokumentation vor Entwicklung.

## Decisions

### ADR-001: `User` ≠ `Person`
`User` (Authentifizierung, Sanctum) und `Person` (HR-Entität) bleiben getrennte Modelle. Ein Mitarbeiter braucht keinen Login; ein Berater hat Login ohne Mitarbeiterstatus. `Person` wird in Phase 2 im Core-Modul eingeführt.

### ADR-002: Modularer Monolith statt Microservices
Ein Laravel-Monolith mit `nwidart/laravel-modules`. Eine Datenbank, eine Codebase, ein Deployment — erfüllt R1 und R2. Module kommunizieren nur über Contracts/Services, nie über Cross-Module-Model-Queries (per Deptrac/Review zu enforceen).

### ADR-003: Single-Database-Tenancy
`stancl/tenancy` wird ohne `DatabaseTenancyBootstrapper` betrieben: **keine** Tenant-Datenbanken. Alle mandantenfähigen Tabellen tragen `tenant_id` (UUID), gescoped via `Stancl\Tenancy\Database\Concerns\BelongsToTenant`. Begründung: R1 (keine Datensilos — DWH/Reporting lesen eine DB), einfachere Migrationen/Backups, SiteGround-Kompatibilität (R2).

- `App\Models\Tenant` erbt `Stancl\Tenancy\Database\Models\Tenant` (+ `HasDomains`).
- `TenancyServiceProvider` enthält keine `CreateDatabase`/`MigrateDatabase`/`DeleteDatabase`-Jobs.
- Tenant-Identifikation: Domain (`tenant.domain`) für Web, `X-Tenant`-Header (`tenant.request`) für API.

### ADR-004: Rollen per spatie-Teams auf Tenant gemappt
`spatie/laravel-permission` mit `'teams' => true`; `team_foreign_key` = Tenant-UUID (Spalten als `string(36)` in der published Migration angepasst). `App\Support\TenantTeamResolver` mappt den Team-Kontext auf `tenant()->getTenantKey()`. Rollen sind damit automatisch mandantenspezifisch (Dokument C).

### ADR-005: Event Store via spatie/laravel-event-sourcing
`stored_events` + `snapshots` Tabellen sind migriert. Domänen-Events folgen dem Event Catalog (Dokument D) als `Modules/<Module>/Events/<PastTense>Event.php`. Der Event Store ist die Audit-Wahrheit; Model-Tabellen bleiben die Read-Seite (Hybrid — siehe Roadmap §10).

### ADR-006: API First
Alle Module exponieren ihre Funktionen unter `/api/v1` mit Sanctum-Auth. Modul-API-Routen laufen unter `auth:sanctum + tenant.request`. API-Doku wird später via Scribe generiert.

## Consequences

- Jede neue mandantenfähige Tabelle MUSS `tenant_id` tragen; Modelle MÜSSEN `BelongsToTenant` nutzen. Migration-Review-Pflicht.
- Tests laufen gegen SQLite; CI sollte zusätzlich MySQL testen (SiteGround-Ziel).
