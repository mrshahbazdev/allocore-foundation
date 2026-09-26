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
| GET | `/me` | (auth+tenant) aktueller Nutzer im Mandanten — `id`, `name`, `email`, `roles`, `permissions` |
| GET | `/roles` | (auth+tenant) verfügbare Rollen des Tenants |
| POST | `/roles` | (auth+tenant, `roles.manage`) eigene Rolle anlegen — `name` (snake_case, eindeutig je Mandant), optional `permissions` |
| PUT | `/roles/{role}` | (auth+tenant, `roles.manage`) Permissions einer Rolle setzen — `permissions: string[]`; Rollen anderer Mandanten → 404 |
| DELETE | `/roles/{role}` | (auth+tenant, `roles.manage`) Rolle löschen; System-Rollen (`holding`, `administrator`) → 422 |
| GET | `/permissions` | (auth+tenant) alle bekannten Permissions (für Rollen-Editor) |
| PUT | `/tenant` | (auth+tenant, `roles.manage`) aktuellen Mandanten umbenennen — `name` |
| GET | `/users` | (auth+tenant) Mitglieder des Tenants (User mit zugewiesener Rolle) |
| POST | `/users` | (auth+tenant, `roles.manage`) Benutzer anlegen bzw. vorhandenen per `email` anhängen — `name`, `email`, optional `password` + `roles`; ohne `password` wird `initial_password` einmalig zurückgegeben |
| GET/PUT | `/users/{user}/roles` | (auth+tenant, `roles.manage`) Rollen eines Users lesen/setzen |
| DELETE | `/users/{user}` | (auth+tenant, `roles.manage`) Mitglied aus dem Mandanten entfernen (eigener Account ausgeschlossen, 422) |

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
`/audits`, `/audit-findings` — je `audits.view`/`audits.manage` (Auditor-Rolle hat beides).
Erinnerungen: `compliance:remind` (stündlich) — Unterweisungen, Prüfungen, Fristen, Feststellungen, Audit-Starts, Maßnahmen, GB-Reviews und Projekt-Enden (Mail + Glocke; GBs gehen an den User mit der E-Mail der zugeordneten Person).

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
| `/metrics`, `/metrics/{metric}` | `metrics.view` | KPI-Snapshots aus `analytics:aggregate` (daily) — Counts je Modul + `compliance_rate` + `team_members` + `tender_applications`, `answers`, `instructions_pending` + Finanz-Summen `fin_revenue`, `fin_ebitda`, `fin_cashflow`, `fin_liquidity` + Audit-Kennzahlen `audits`, `audits_planned`, `audits_in_progress`, `audit_findings_open`, `audit_findings_overdue`, `audits_done` + weitere `tenders_awarded`, `machines`, `deadlines`, `leave_requests`, `production_orders_done` |
| `/analytics/trends` | `metrics.view` | Layer 7: Trend je Metrik — letzter Wert, Vorgänger, `delta`, `direction` (up/down/flat/unknown) |
| `/insights` | `metrics.view` | Regelbasierte Hinweise (KI-Steuerung): `code`, `severity` (critical/warning/info), `message`, `data` — Codes: `tasks_overdue`, `tasks_due_soon`, `compliance_rate_low`, `high_risks_open`, `risk_reviews_overdue`, `risk_reviews_due_soon`, `deadlines_overdue`, `deadlines_due_soon`, `instructions_overdue`, `instructions_due_soon`, `inspections_overdue`, `inspections_due_soon`, `measures_overdue`, `orders_overdue`, `machines_in_maintenance`, `fin_negative_liquidity`, `fin_reports_missing`, `leave_requests_pending`, `leave_pending_stale`, `leave_active_today`, `tenders_open`, `tenders_overdue`, `tenders_deadline_soon`, `tenders_no_applications`, `expert_profiles_incomplete`, `documents_no_version`, `persons_without_company`, `companies_no_persons`, `orders_no_machine`, `inspections_no_result`, `op_instructions_review`, `questions_stale`, `orders_machine_maintenance`, `machines_idle`, `instructions_no_person`, `projects_no_owner`, `investments_stale_value`, `documents_no_category`, `expert_profiles_no_rate`, `persons_no_contact`, `leave_overlap`, `applications_no_price`, `audits_no_findings`, `risk_assessments_no_person`, `ai_analyses_failed`, `tenders_no_budget`, `machines_zero_capacity`, `inspections_no_person`, `instructions_renewal_due`, `data_objects_no_category`, `audits_no_result`, `instructions_no_completed_at`, `applications_stale`, `questions_no_answers`, `op_instructions_no_document`, `instructions_no_document`, `tenders_awarded_no_winner`, `projects_done_incomplete`, `orders_done_incomplete`, `questions_no_accepted`, `orders_running_no_start`, `orders_done_no_finish`, `projects_no_strategy`, `investments_no_value`, `leave_decided_no_stamp`, `participations_exited_with_stake`, `risks_no_measures`, `deadlines_completed_no_stamp`, `audits_no_auditor`, `applications_no_proposal`, `tasks_done_no_stamp`, `data_objects_empty`, `graph_edges_no_relation`, `measures_no_project`, `projects_stalled`, `questions_unassigned`, `deadlines_no_subject`, `strategies_no_projects`, `projects_no_measures`, `portfolios_no_investments`, `projects_overdue`, `projects_ending_soon`, `questions_open`, `graph_orphans`, `measures_due_soon`, `orders_due_soon`, `strategies_ending_soon`, `strategies_overdue`, `investments_drawdown`, `participations_capital_need`, `tasks_unassigned`, `audit_findings_overdue`, `audit_findings_critical`, `audit_findings_due_soon`, `audit_findings_unassigned`, `audits_starting_soon`, `audits_overdue`, `audits_unassigned`, `op_instructions_draft`, `measures_unassigned`, `deadlines_unassigned`, `inspections_unassigned`, `instructions_unassigned`, `orders_unassigned`, `projects_unassigned`, `all_clear`, `members_never_logged_in` |
| `/nav-counts` | `metrics.view` | Sidebar-Badges server-seitig: `{sektion: [überfällig, heute]}` — SQL-Counts statt 30 List-Requests |
| `/search?q=` | `metrics.view` | Globale Suche über alle Module (inkl. Data Lake, Graph, KI-Analysen, Events): `[{section, id, label}]` — `q` ≥ 2 Zeichen, optional `sections=tasks,companies`, `limit=1–50` (Default 20) |
| `/notifications` | `metrics.view` | Datenbank-Benachrichtigungen des Users (z. B. aus `compliance:remind`): `[{id, kind, title, due_at, read, created_at}]` — `limit` ≤ 50 (Default 10), `unread=1` nur ungelesene |
| POST `/notifications/{id}/read` | `metrics.view` | Benachrichtigung als gelesen markieren |
| POST `/notifications/{id}/unread` | `metrics.view` | Benachrichtigung als ungelesen markieren (404 bei fremder) |
| POST `/notifications/read-all` | `metrics.view` | Alle ungelesenen Benachrichtigungen als gelesen markieren |
| `/notifications/unread-count` | `metrics.view` | Anzahl ungelesener Benachrichtigungen: `{count}` |
| DELETE `/notifications/{id}` | `metrics.view` | Einzelne Benachrichtigung löschen (404 bei fremder) |
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
| Audits | `/audits`, `/audit-findings` (+ `/{id}`) |

## Fehlerformat

- `401` ohne gültiges Token, `403` ohne Recht auf dem Tenant, `404` cross-tenant Zugriff.
- Paginierung: Laravel `paginate()` — `data[]`, `meta`, `links`. Alle paginierten List-Endpunkte akzeptieren `?per_page=N` (Default 200, Max 200).
