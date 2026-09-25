# ALLOCORE Foundation — API Referenz (v1)

Alle Endpunkte unter `/api/v1`. Auth: `Authorization: Bearer <sanctum-token>`.
Tenant-Scope: Header `X-Tenant: <tenant-uuid>` (Pflicht auf allen Domänen-Endpunkten).
RBAC: `{domain}.view` für GET, `{domain}.manage` für POST/PUT/PATCH/DELETE.

## Zentral (kein Tenant-Header nötig)

| Methode | Pfad | Zweck |
|---|---|---|
| GET | `/health` | Liveness |
| GET/POST | `/tenants` | Mandantenliste / Mandant anlegen (seedet Rollenmodell) |
| GET | `/context` | (auth) aktueller User + Tenant-Kontext |
| GET | `/roles` | (auth+tenant) verfügbare Rollen des Tenants |
| GET/PUT | `/users/{user}/roles` | (auth+tenant, `roles.manage`) Rollen eines Users lesen/setzen |

## Core (`companies`, `persons`)

| Pfad | Rechte |
|---|---|
| `/companies`, `/companies/{id}` | `companies.view` / `companies.manage` |
| `/persons`, `/persons/{id}` | `persons.view` / `persons.manage` |

## Documents (`documents`)

| Pfad | Methode | Recht |
|---|---|---|
| `/documents`, `/documents/{id}` | CRUD | `documents.view` / `documents.manage` |
| `/documents/{id}/versions` | POST (Upload neue Version) | `documents.manage` |
| `/documents/{id}/download/{version?}` | GET | `documents.view` |

## Tasks (`tasks`)

`/tasks`, `/tasks/{id}` — Filter `status`. Erinnerungen: `tasks:remind` (stündlich).

## Compliance (`compliance`)

`/instructions`, `/inspections`, `/deadlines` (morphTo subject — an beliebiges Objekt anhängbar),
`/risk-assessments`, `/operating-instructions` — je `compliance.view`/`compliance.manage`.
Erinnerungen: `compliance:remind` (stündlich).

## ExpertNetwork (`experts`)

| Pfad | Besonderheit |
|---|---|
| `/expert-profiles` | CRUD |
| `/expert-profiles/match?skills[]=…` | Skill-Overlap-Matching (Score-sortiert) |
| `/questions`, `/questions/{id}/answers` | Q&A |
| `/answers/{id}/accept` | Antwort akzeptieren |
| `/tenders`, `/tenders/{id}/applications`, `/tender-applications/{id}` | Ausschreibungen + Award |

## DataPlatform (`metrics`)

| Pfad | Recht | Zweck |
|---|---|---|
| `/events` | `metrics.view` | Event-Stream aus `stored_events` (R5) — Query: `type`, `subject_id`, `subject_type`, `per_page` (max. 200), neueste zuerst |
| `/metrics`, `/metrics/{metric}` | `metrics.view` | KPI-Snapshots aus `analytics:aggregate` (daily) |
| `/analytics/trends` | `metrics.view` | Layer 7: Trend je Metrik — letzter Wert, Vorgänger, `delta`, `direction` (up/down/flat/unknown) |
| `/insights` | `metrics.view` | Regelbasierte Hinweise (KI-Steuerung): `code`, `severity` (critical/warning/info), `message`, `module` |
| `/nav-counts` | `metrics.view` | Sidebar-Badges server-seitig: `{sektion: [überfällig, heute]}` — SQL-Counts statt 30 List-Requests |
| `/search?q=` | `metrics.view` | Globale Suche über alle Module (inkl. Data Lake, Graph, KI-Analysen, Events): `[{section, id, label}]` — `q` ≥ 2 Zeichen, optional `sections=tasks,companies`, `limit=1–50` (Default 20) |
| POST `/demo-seed` | `roles.manage` | Demodaten für den aktuellen Tenant laden (idempotent; wie `php artisan demo:seed {tenant}`) |

## DataLake (`datalake`) — Layer 4

| Pfad | Recht | Zweck |
|---|---|---|
| `/data-objects` | `datalake.view` / `datalake.manage` | Objektspeicher (PDFs, Bilder, Verträge, CAD, Produktionsdaten) — `category`-Filter |
| `/data-objects/{id}/download` | `datalake.view` | Objekt herunterladen |

## KnowledgeGraph (`graph`) — Layer 6

| Pfad | Recht | Zweck |
|---|---|---|
| `/graph-entities` | `graph.view` / `graph.manage` | Knoten (`type`, `name`, morph `subject`, json `properties`) |
| `/graph-edges` | `graph.view` / `graph.manage` | Kanten (`from`, `to`, `relation`, idempotent via unique) |
| `/graph-entities/{id}/neighbors?depth={n}` | `graph.view` | BFS-Traversal, `depth` ≤ 3 |

## Ai (`ai`) — Layer 8

| Pfad | Recht | Zweck |
|---|---|---|
| `/ai-analyses` | `ai.view` / `ai.manage` | Analyse-Runs (provider-agnostic; Default `heuristic`, LLM-Provider steckbar) |

## Executive (`executive`) — Layer 9

| Pfad | Recht | Zweck |
|---|---|---|
| `/executive/overview` | `executive.view` | Holding-Rollup: KPIs über alle Tenants + `totals` |
| `/exec-reports` | `executive.view` / `executive.manage` | Persistierte Executive-Snapshots |

## Weitere Domänen (CRUD, `{domain}.view`/`{domain}.manage`)

| Domäne | Pfade |
|---|---|
| CorporateDev | `/strategies`, `/projects`, `/measures` (+ `/{id}`) |
| Finance | `/financial-reports` (+ `/{id}`) |
| Hr | `/leave-requests` (+ `/{id}`) |
| Investments | `/portfolios`, `/investments` (+ `/{id}`) |
| Participations | `/participations` (+ `/{id}`) |
| Production | `/machines`, `/production-orders` (+ `/{id}`) |

## Fehlerformat

- `401` ohne gültiges Token, `403` ohne Recht auf dem Tenant, `404` cross-tenant Zugriff.
- Paginierung: Laravel `paginate()` — `data[]`, `meta`, `links`. Alle paginierten List-Endpunkte akzeptieren `?per_page=N` (Default 200, Max 200).
