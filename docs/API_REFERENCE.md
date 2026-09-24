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
| `/events` | `metrics.view` | Event-Stream aus `stored_events` (R5) |
| `/metrics`, `/metrics/{metric}` | `metrics.view` | KPI-Snapshots aus `analytics:aggregate` (daily) |

## Fehlerformat

- `401` ohne gültiges Token, `403` ohne Recht auf dem Tenant, `404` cross-tenant Zugriff.
- Paginierung: Laravel `paginate()` — `data[]`, `meta`, `links`.
