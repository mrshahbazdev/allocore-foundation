# ALLOCORE FOUNDATION — Laravel Implementation Roadmap v1.0

**Auftraggeber:** DISAVO Holding GmbH
**Projektverantwortung:** ALLOCORE GmbH
**Zielgruppe dieses Dokuments:** Mohammad (Projektleitung), Lead Architect, Data Architect, Entwicklerteam, Ausschreibung/Evaluator

> **Wichtigster Satz:** Wir bauen keine Compliance-Software. Wir bauen eine Plattform, auf der Compliance das erste Modul ist.

---

## 0. Executive Summary

ALLOCORE Foundation ist ein modulares Laravel-Monolithen-System (Modular Monolith), das als zentrales Betriebssystem der Unternehmensgruppe dient. Die Wahl eines **Modular Monolith** statt Microservices ist bewusst: eine Datenbank, eine Codebase, ein Deployment — erfüllt R1 (keine Datensilos) und R2 (Cloudunabhängigkeit) am einfachsten, während Module sauber getrennt bleiben und später bei Bedarf extrahiert werden können.

**Kerntechnologie-Stack:**

| Schicht | Entscheidung | Begründung |
|---|---|---|
| Framework | Laravel 12+ (PHP 8.3) | Bestehende Team-Expertise (allocore-suite), schnelle Entwicklung |
| Module-System | `nwidart/laravel-modules` | Bewährt in allocore-suite; saubere Modulgrenzen |
| Multi-Tenancy | `stancl/tenancy` (Single-DB, `tenant_id`-Scoping) | R4 Mandantenfähigkeit ohne DB-Fragmentierung |
| Rollen/Rechte | `spatie/laravel-permission` | R3-Rollenmodell (Dokument C) |
| Event Store | `spatie/laravel-event-sourcing` + `stored_events`-Tabelle | R5 Event First |
| API | Laravel API Resources + `laravel/sanctum` + Versionierung `/api/v1` | R3 API First |
| Dokumentation | Scribe (API-Docs auto-generiert) + `docs/` im Repo | R6 Dokumentation vor Entwicklung |
| Frontend | Livewire 3 + Volt + Tailwind (Admin/Portale); Inertia nur falls nötig | Konsistent mit Team-Skills |
| Queue/Events | Redis + Horizon | Event-Pipeline, Reminder, Matching |
| Storage | Flysystem (lokal/S3-kompatibel) | R2: wechselbar SiteGround ↔ S3 ↔ MinIO |
| DB | MariaDB/MySQL (SiteGround-kompatibel), Postgres-fähig | R2 Cloudunabhängigkeit |
| Data Warehouse | Phase 5: getrennte Analytics-DB (Postgres) + ETL-Jobs | Read/Write-Trennung |
| BI/Reporting | Laravel-interne KPI-Schicht, später Metabase/Superset | DWH-Domänen §5 |

---

## 1. Architekturregeln → Laravel-Mapping

| Regel | Umsetzung in Laravel |
|---|---|
| **R1 Keine Datensilos** | Eine Primärdatenbank. Module kommunizieren nur über definierte Ports/Contracts (`Modules/*/Contracts`), nie über direkte Model-Queries quer durch Module. Shared Kernel: `app/Domain/` für kernübergreifende Aggregate (Company, Person, Document). |
| **R2 Cloudunabhängigkeit** | Keine Cloud-spezifischen SDKs in der Fachlogik. Storage nur via `Storage::` (Flysystem). Queue via `QUEUE_CONNECTION` (database/redis). Keine AWS/Azure-spezifischen Migrationen. Deployment-Profiles: `deploy/siteground.md`, `deploy/azure.md`, `deploy/aws.md`, `deploy/bare-metal.md`. CI testet gegen MySQL + SQLite. |
| **R3 API First** | Jede Fachfunktion: Service-Klasse (`Modules/X/Services`) → Controller → API Route unter `/api/v1`. Web-UI konsumiert dieselben Services. Sanctum-Tokens pro Mandant. Scribe generiert OpenAPI-Doku in CI. |
| **R4 Mandantenfähigkeit** | `tenants`-Tabelle, `tenant_id` auf allen mandantenfähigen Tabellen, Global Scope `BelongsToTenant`. Tenant-Kontext via Middleware (`InitializeTenancyByDomain` oder `ByUser`). Tests: jeder Feature-Test läuft unter 2 Tenants. |
| **R5 Event First** | `StoredEvent`-Aggregate + Domain Events. Jede Mutation dispatched ein Event aus dem **Event Catalog (Dokument D)**. `stored_events` als append-only Wahrheit; Projektionen für Read-Modelle (Listen, Dashboards). |
| **R6 Dokumentation vor Entwicklung** | `docs/` im Repo ist Pflichtlieferumfang. PR-Template enthält Checkbox „Architektur-Entscheidung dokumentiert (ADR in `docs/adr/`)?". Kein Modul-Merge ohne `Modules/X/docs/module.md`. |

### Zielverzeichnisstruktur

```
allocore-foundation/
  app/                    # Shared Kernel: User, Tenant, Company, Person, Document
    Domain/               # Kern-Aggregate, Events, Contracts
    Http/ Providers/
  Modules/
    Core/                 # Auth, RBAC, Tenancy, Audit-Log-Viewer
    Documents/            # Upload, Versionierung, Zugriffskontrolle
    Tasks/                # Aufgaben, Deadlines, Erinnerungen
    Compliance/           # Unterweisungen, Prüfungen, GBU, Betriebsanweisungen
    ExpertNetwork/        # Experten, Q&A, Ausschreibungen, Matching
    Projects/             # Projekte, Strategien, Maßnahmen
    Production/           # Zahntechnik: Maschinen, Kapazität, Aufträge (Phase 4+)
    Investments/          # Portfolios, Renditen, Kapitalallokation
    Reporting/            # KPI-Schicht, DWH-Anbindung
  docs/
    architecture/         # Dokument A (Master Architecture)
    domain/               # Dokument B (Business Domain Model)
    roles/                # Dokument C (Rollenmodell)
    events/               # Dokument D (Event Catalog)
    adr/                  # Architecture Decision Records
    api/                  # Generierte OpenAPI-Docs
  deploy/                 # Pro-Plattform Deployment-Guides
```

---

## 2. Business Domain Model (Dokument B — Vorab-Mapping)

| Domänen-Objekt | Laravel-Artefakt | Modul |
|---|---|---|
| Unternehmen | `Tenant` + `Company` (LegalEntity) | Core |
| Personen | `Person` (von `User` getrennt: User = Login, Person = HR-Entität) | Core |
| Kunden | `Customer` | Core |
| Maschinen | `Machine` | Production |
| Dokumente | `Document`, `DocumentVersion`, Policy-basierte ACL | Documents |
| Projekte | `Project`, `Measure`, `Strategy` | Projects |
| Aufträge | `Order`, `WorkOrder` | Production |
| Investments | `Investment`, `Portfolio`, `Allocation` | Investments |

**Wichtige Modell-Entscheidung (ADR-001):** `User` (Authentifizierung) und `Person` (HR/Mitarbeiter-Entität) sind getrennte Modelle — ein Mitarbeiter braucht keinen Login; ein Berater hat Login ohne Mitarbeiterstatus.

---

## 3. Rollenmodell (Dokument C — spatie/laravel-permission)

| Rolle | Scope | Kernrechte |
|---|---|---|
| Holding | Global (cross-tenant) | Alle Tenants lesen, KPIs, Beteiligungen |
| Geschäftsführer | Tenant | Vollzugriff Tenant, Freigaben, Reports |
| Administrator | Tenant | User, Rollen, Stammdaten, Einstellungen |
| Mitarbeiter | Tenant | Eigene Aufgaben, Unterweisungen, Dokumente lesen |
| Berater | Tenant (eingeladen) | Zugewiesene Projekte/Fachbereiche |
| Auditor | Tenant (zeitlich befristet) | Read-only auf Prüfumfang, Export |
| Kunde | Tenant (Portal) | Eigene Aufträge, Dokumente, Q&A |

Umsetzung: `roles`/`permissions` pro Tenant (teams-Funktion von spatie/laravel-permission). Befristete Rollen über `role_valid_until` + automatischer Entzug per Scheduler.

---

## 4. Event Catalog (Dokument D — Auszug, spatie/laravel-event-sourcing)

Alle Events erben von `DomainEvent` und werden in `stored_events` persistiert:

```
TenantCreated, CompanyCreated, PersonCreated, UserInvited, RoleAssigned, RoleRevoked
DocumentUploaded, DocumentVersioned, DocumentAccessGranted, DocumentAccessRevoked
TaskCreated, TaskAssigned, TaskDeadlineApproaching, TaskCompleted
InstructionAssigned, InstructionCompleted, InstructionExpired
InspectionScheduled, InspectionPassed, InspectionFailed
RiskAssessmentCreated, RiskAssessmentApproved
OrderCreated, OrderDispatched, MachineMaintenanceDue
ExpertRegistered, QuestionAsked, AnswerGiven, TenderPublished, BidSubmitted, ExpertMatched
InvestmentPurchased, PortfolioRevalued, CapitalAllocated
VacationRequested, VacationApproved, SickLeaveReported
```

Konvention: `Modules/<Module>/Events/<PastTense>Event.php`, registriert im `EventServiceProvider`, dokumentiert in `docs/events/<module>.md`.

---

## 5. Phasenplan (Lastenheft §4 → konkrete Sprints)

### PHASE 1 — Architektur (Sprint 0–1, ~1 Session/Woche 1–2)
**Ergebnis: 4 Pflichtdokumente, bevor Code entsteht (R6).**

| Deliverable | Datei | Owner |
|---|---|---|
| A: Master Architecture | `docs/architecture/master-architecture.md` (Systemübersicht, Modulübersicht, Integrationen, Verantwortlichkeiten) | Lead Architect |
| B: Business Domain Model | `docs/domain/business-domain-model.md` + ERD (`docs/domain/erd.svg`) | Data Architect |
| C: Rollenmodell | `docs/roles/role-model.md` + Permission-Matrix | Lead Architect |
| D: Event Catalog | `docs/events/event-catalog.md` | Data Architect |

Plus: ADR-001..005 (User≠Person, Modular Monolith, Tenancy-Strategie, Event-Sourcing-Ansatz, Storage-Abstraktion).

**Exit-Kriterium:** Alle 4 Dokumente von Auftraggeber abgenommen; neuer Entwickler kann Struktur aus Docs erklären (§7-Wissensregel).

### PHASE 2 — Core Platform (Sprints 2–4)
- Laravel-App + nwidart/laravel-modules + stancl/tenancy aufsetzen
- Auth (Breeze/Sanctum + 2FA via google2fa), Tenant-Onboarding
- RBAC: Rollen/Permissions nach Dokument C, Admin-UI
- Module `Core`, `Documents`, `Tasks`:
  - Unternehmen/Standorte/Ansprechpartner (Stammdaten)
  - Documents: Upload (Flysystem), `document_versions`, Policy-ACL
  - Tasks: Verantwortliche, Deadlines, Erinnerungen (Scheduled Jobs + Notifications)
- API v1 für alle Core-Funktionen + Scribe-Doku
- `stored_events` live: Events aus Phase-2-Modulen fließen bereits

**Exit-Kriterium:** Tenant anlegen → User einladen → Rolle vergeben → Dokument hochladen/versionieren → Aufgabe mit Erinnerung — alles per API und UI, alle Aktionen im Event Store.

### PHASE 3 — Compliance (Sprints 5–7) — *erstes Fachmodul*
- `Modules/Compliance`: Unterweisungen (Zuweisung, Nachweis, Wiederholungsintervalle), Prüfungen/Fristen, Gefährdungsbeurteilungen, Betriebsanweisungen (verknüpft mit Documents)
- Fristen-Engine: wiederkehrende Pflichten als Scheduler-Jobs → `InstructionExpired`-Events → Eskalation
- Audit-fähige Nachweise: wer, wann, welche Version (aus Event Store + Document-Versionen)

**Exit-Kriterium:** Schulungsquote und Prüfstatus pro Tenant reportbar.

### PHASE 4 — Expertennetzwerk (Sprints 8–10)
- `Modules/ExpertNetwork`: Expertenprofile, Fragen & Antworten, Bewertungen, Ausschreibungen, Matching
- Matching-Service: Skills/Tags ↔ Ausschreibungs-Anforderungen → `ExpertMatched`-Events
- Projekträume: eingeschränkte Tenant-übergreifende Räume für Berater/Kunden

### PHASE 5 — Data Platform (Sprints 11–13)
- Event Store bereits aktiv → Analytics-Projektionen
- Data Lake: Flysystem-Disk `datalake` (S3/MinIO/lokal) für PDFs, Bilder, Verträge, CAD, Produktionsdaten — referenziert aus `Document`
- Data Warehouse: eigene Analytics-DB, Nightly/Incremental ETL-Jobs (Laravel Commands) → Sternschema pro Domäne (§6)
- Reporting-Modul: KPI-Definitionen als Code (`Modules/Reporting/Kpis/*.php`), Dashboard-Endpoints

### PHASE 6+ — Weitere Geschäftsfelder (Backlog)
- Projects (Unternehmensentwicklung): Strategien, Maßnahmen, Org-Entwicklung
- Production (Zahntechnik): Maschinen, Kapazitätsplanung, Mitarbeiter-Auslastung, Auftragsmanagement
- Investments: Portfolios, Renditen, Kapitalallokation
- KI-gestützte Unternehmenssteuerung auf DWH + Event Store

---

## 6. Data Warehouse Domänen (Lastenheft §5 → Tabellendesign)

Jede Domäne: `fact_*` + `dim_*` Tabellen in Analytics-DB. ETL per Laravel Command (`php artisan etl:run --domain=compliance`), idempotent, aus `stored_events` + Primär-DB.

| Domäne | Fakten (Beispiele) | Dimensionen |
|---|---|---|
| Unternehmen | `fact_company_kpis` (Umsatz, Mitarbeiter, Kunden, Standorte) | dim_tenant, dim_time |
| Compliance | `fact_training_quota`, `fact_inspection_status`, `fact_audit_status` | dim_employee, dim_instruction_type |
| Projekte | `fact_project_effort`, `fact_project_progress`, `fact_risks` | dim_project, dim_phase |
| Personal | `fact_leave`, `fact_sickness`, `fact_availability`, `fact_utilization` | dim_person, dim_team |
| Produktion | `fact_orders`, `fact_lead_time`, `fact_scrap`, `fact_machine_runtime` | dim_machine, dim_order |
| Finanzen | `fact_revenue`, `fact_cashflow`, `fact_ebitda`, `fact_liquidity` | dim_account, dim_period |
| Beteiligungen | `fact_company_value`, `fact_return`, `fact_capital_need` | dim_holding_entity |
| Investment | `fact_portfolio_value`, `fact_performance`, `fact_risk` | dim_asset_class |

---

## 7. Team & Verantwortlichkeiten (Lastenheft §3 → konkrete Aufgaben)

| Rolle | Laravel-spezifische Verantwortung |
|---|---|
| Lead Architect | Modul-Contracts, Tenancy-Setup, Security-Modell, API-Governance, ADRs |
| Data Architect | Domain-Modelle, Event-Schemas, `stored_events`-Struktur, DWH-Design, ETL |
| Backend Engineer | Services, Policies, Jobs, ETL, Integrationen |
| Frontend Engineer | Livewire-Shell, Dashboards, Portale, Design-System |
| Fullstack Developer | MVP-Module, Vertretung BE/FE |

**Onboarding-Regel (§7):** `docs/onboarding.md` — Ziel: neuer Entwickler versteht Architektur in ≤14 Tagen. Gemessen per Review der Pflichtdoku.

---

## 8. Dokumentationspflicht (Lastenheft §6 → Dateipfade)

| Dokument | Pfad | Pflicht | Aktualisierung |
|---|---|---|---|
| Architekturhandbuch | `docs/architecture/` | Ja | Bei jedem ADR |
| Datenmodell | `docs/domain/` + ERD | Ja | Bei jeder Schema-Änderung |
| API-Dokumentation | `docs/api/` (Scribe, CI-generiert) | Ja | Automatisch |
| Rollenmodell | `docs/roles/` | Ja | Bei Rollen-/Rechte-Änderung |
| Eventkatalog | `docs/events/` | Ja | Bei jedem neuen Event |
| Sicherheitskonzept | `docs/security/` | Ja | Bei Auth/ACL-Änderung |
| Infrastrukturübersicht | `deploy/` | Ja | Bei Plattform-Änderung |
| Systemlandkarte | `docs/architecture/system-map.md` | Ja | Bei neuem Modul |

Enforcement: CI-Check — PR mit neuem Modul/Event/Migration ohne entsprechendes Doc-Update schlägt fehl (`scripts/check-docs.php`).

---

## 9. Ausschreibungs-/Evaluierungs-Checkliste für Entwickler

Mohammad kann Bewerber an diesen konkreten Punkten messen:

1. Laravel: Service Layer vs. „fette Controller" — Beispiel-Refactoring zeigen lassen
2. Multi-Tenancy: `stancl/tenancy`-Erfahrung oder Single-DB-Scoping erklären
3. Event Sourcing: `spatie/laravel-event-sourcing`, StoredEvent vs. Projektion
4. Testing: Feature-Tests mit 2 Tenants schreiben (Datenisolation beweisen)
5. API-Design: Versionierung, Resources, Sanctum-Abilities
6. Dokumentationsdisziplin: Bereitschaft zu „Docs first" (R6) — Ausschlusskriterium wenn verweigert

---

## 10. Risiken & offene Entscheidungen

| Risiko | Mitigation |
|---|---|
| Event Sourcing Overhead bei einfachen CRUD | Hybrid: Events für alle Domänen-Änderungen, aber normale Model-Tables als Read-Seite; Event Store ist Wahrheit für Audit/Replay |
| Modular-Monolith wird zur Big Ball of Mud | Module-Contracts erzwingen (kein Cross-Module Model-Zugriff; PHPStan/Deptrac-Regel in CI) |
| Mandantenfähigkeit nachträglich teuer | `tenant_id` von Tag 1 auf allen Tabellen — Pflicht-Check in Migration-Review |
| SiteGround-Limits (kein Redis, shared hosting) | `QUEUE_CONNECTION=database` Fallback; Horizon nur wo verfügbar; Deployment-Profile testen alle drei Ziele |

**Nächste Schritte:**
1. Mohammad: Team aufstellen (Phase-1-Rollen zuerst besetzen)
2. Lead Architect: Repo `allocore-foundation` initialisieren + Skelett (Laravel, Modules, Tenancy, CI)
3. Sprint 0: Dokumente A–D erstellen und abnehmen lassen
