# ALLOCORE Foundation — API Referenz (v1)

Alle Endpunkte unter `/api/v1`. Auth: `Authorization: Bearer <sanctum-token>`.
Tenant-Scope: Header `X-Tenant: <tenant-uuid>` (Pflicht auf allen Domänen-Endpunkten). Mitgliedschaft Pflicht: Nutzer dürfen nur Mandanten betreten, in denen sie Mitglied sind — sonst `403 Kein Mitglied dieses Mandanten.` (Einstiegspunkt: der Ersteller eines neuen Mandanten wird automatisch `administrator`; weitere Nutzer werden per `POST /users` eines Mitglieds angebunden).
RBAC: `{domain}.view` für GET, `{domain}.manage` für POST/PUT/PATCH/DELETE.

**Security-Defaults:** Alle Antworten tragen Security-Header (`X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`). Passwort-Policy: min. 12 Zeichen + Groß-/Kleinbuchstabe + Zahl. Passwort-Änderung und -Reset widerrufen alle persönlichen API-Tokens. Auth-Routen (Registrierung, Login, Passwort vergessen/zurücksetzen/bestätigen) sind auf 6 Versuche/Minute pro IP gedrosselt.

## Zentral (kein Tenant-Header nötig)

| Methode | Pfad | Zweck |
|---|---|---|
| GET | `/health` | Liveness + Readiness — `checks.database` + `checks.migrations` (ausstehende Migrationen → `pending:N`), 503 bei `degraded`; ohne Auth |
| GET | `/version` | Build-/Betriebs-Infos — `app_version` (APP_VERSION env), `laravel`, `php`, `commit` (Git-SHA aus .git/HEAD, falls vorhanden); ohne Auth |
| GET/POST | `/tenants` | (auth) GET listet nur eigene Mitgliedschaften; POST legt Mandant an (seedet Rollenmodell) — Ersteller wird automatisch `administrator` |
| GET | `/context` | (auth) aktueller User + Tenant-Kontext |
| GET/POST, DELETE | `/tokens`, `/tokens/{id}` | auth | Personal Access Tokens — Klartext nur bei POST-Antwort; `abilities` optional (`['tasks.view', ...]` — scoped Token, enforced auf `permission:`-Routen; Whitelist: `GET /tokens/abilities`), `expires_in_days` 1–365; Workspace-Token werden pro Seitenaufruf rotiert |
| `/me` | (auth+tenant) aktueller Nutzer im Mandanten — `id`, `name`, `email`, `roles`, `permissions` |
| GET | `/roles` | (auth+tenant) verfügbare Rollen des Tenants |
| POST | `/roles` | (auth+tenant, `roles.manage`) eigene Rolle anlegen — `name` (snake_case, eindeutig je Mandant), optional `permissions` |
| PUT | `/roles/{role}` | (auth+tenant, `roles.manage`) Permissions einer Rolle setzen — `permissions: string[]`; Rollen anderer Mandanten → 404; Rechte der letzten `roles.manage`-Rolle kappen → 422 |
| DELETE | `/roles/{role}` | (auth+tenant, `roles.manage`) Rolle löschen; System-Rollen (`holding`, `administrator`) → 422; letzte `roles.manage`-Rolle (wenn kein Mitglied die Rechte anderweitig behält) → 422 |
| GET | `/permissions` | (auth+tenant) alle bekannten Permissions (für Rollen-Editor) |
| PUT | `/tenant` | (auth+tenant, `roles.manage`) aktuellen Mandanten umbenennen — `name` |
| GET | `/users` | (auth+tenant) Mitglieder des Tenants (User mit zugewiesener Rolle) |
| POST | `/users` | (auth+tenant, `roles.manage`) Benutzer anlegen bzw. vorhandenen per `email` anhängen — `name`, `email`, optional `password` + `roles`; ohne `password` wird `initial_password` einmalig zurückgegeben; Abstufung des letzten `roles.manage`-Mitglieds → 422 |
| GET/PUT | `/users/{user}/roles` | (auth+tenant, `roles.manage`) Rollen eines Users lesen/setzen; Lockout-Guard: letztes `roles.manage`-Mitglied kann nicht abgestuft werden → 422 |
| DELETE | `/users/{user}` | (auth+tenant, `roles.manage`) Mitglied aus dem Mandanten entfernen (eigener Account ausgeschlossen, 422; letztes `roles.manage`-Mitglied → 422) |

## Core (`companies`, `persons`)

| Pfad | Rechte |
|---|---|
| `/companies`, `/companies/{id}` | `companies.view` / `companies.manage` — Liste: `?q=` (Name) |
| `/persons`, `/persons/{id}` | `persons.view` / `persons.manage` — Liste: `?company_id=`, `?q=` (Name/E-Mail) |

## Documents (`documents`)

| Pfad | Methode | Recht |
|---|---|---|
| `/documents`, `/documents/{id}` | CRUD — Liste: `?category=`, `?q=` (Titel) | `documents.view` / `documents.manage` |
| `/documents/{id}/versions` | POST (Upload neue Version) | `documents.manage` |
| `/documents/{id}/download/{version?}` | GET | `documents.view` |

## Tasks (`tasks`)

`/tasks`, `/tasks/{id}` — Filter `status`, `assignee_id`, `unassigned=1`, `overdue=1`. Erinnerungen: `tasks:remind` (stündlich).

## Compliance (`compliance`)

`/instructions`, `/inspections`, `/deadlines` (morphTo subject — an beliebiges Objekt anhängbar),
`/risk-assessments`, `/operating-instructions` — je `compliance.view`/`compliance.manage`.
Listen-Filter: `?status=` + `?q=` (Titel-Suche); Inspections zusätzlich `?result=`, Risk-Assessments `?risk_level=`. Zuordnungs-Filter: `responsible_id` (`/deadlines`, `/audits`, `/audit-findings`, `/measures`), `person_id` (`/instructions`, `/inspections`, `/risk-assessments`, `/leave-requests`), `owner_id` (`/projects`), `company_id` (`/tenders`, `/participations`), `person_id` auch `/expert-profiles`, `document_id` (`/instructions`, `/operating-instructions`), `assigned_to` (`/production-orders`), `created_by` (`/tasks`, `/tenders`), `from_entity_id`/`to_entity_id` (`/graph-edges`).
- `?sort=<col>&dir=asc|desc` serverseitige Sortierung (Spalten-Whitelist) auf allen Entity-Listen (u. a. `/deadlines`, `/instructions`, `/inspections`, `/risk-assessments`, `/audits`, `/audit-findings`, `/tasks`, `/measures`, `/companies`, `/persons`, `/documents`, `/production-orders`, `/machines`, `/investments`, `/portfolios`, `/participations`, `/leave-requests`, `/tenders`, `/questions`, `/expert-profiles`, `/strategies`, `/projects`, `/financial-reports`, `/data-objects`, `/graph-entities`). Überfällig/`due_soon=1` (≤7 Tage) auf: `/deadlines` (open,due_at), `/instructions` (pending,due_at), `/inspections` (scheduled,scheduled_at), `/risk-assessments` (open,review_at), `/audit-findings` + `/measures` (open|in_progress,due_at).

`?q=` (LIKE-Suche auf Titel/Name) gibt es außerdem auf: `/tasks`, `/projects`, `/strategies`, `/measures`, `/audits`, `/audit-findings`, `/tenders`, `/questions`, `/expert-profiles` (headline), `/machines`, `/production-orders` (order_no+product), `/portfolios`, `/investments`, `/participations`, `/graph/entities`, `/companies`, `/persons`, `/documents`, `/data-objects`.
`/audits`, `/audit-findings` — je `audits.view`/`audits.manage` (Auditor-Rolle hat beides).
Erinnerungen: `compliance:remind` (stündlich) — Unterweisungen, Prüfungen, Fristen, Feststellungen, Audit-Starts, Maßnahmen, GB-Reviews und Projekt-Enden sowie Unterweisungs-Wiederholungen (Mail + Glocke; GBs gehen an den User mit der E-Mail der zugeordneten Person).

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
| `/events` | `metrics.view` | Event-Stream aus `stored_events` (R5) — Query: `type`, `subject_id`, `subject_type`, `action` (Suffix, z. B. `created`), `before_id`/`after_id` (ID-Cursor), `per_page` (max. 200), neueste zuerst, `since`/`until` (Zeitraum auf `created_at`) |
| `/events/export` | `metrics.view` | Audit-Export — NDJSON-Stream aller Mandanten-Events (`application/x-ndjson`, `Content-Disposition`-Attachment). Gleiche Filter wie `/events` (ohne Cursor/per_page) plus `group` (Präfix auf Event-Typ, z. B. `task` für `task.*`) |
| `/metrics`, `/metrics/{metric}` | `metrics.view` | KPI-Snapshots aus `analytics:aggregate` (daily) — Counts je Modul + `compliance_rate` + `team_members` + `tender_applications`, `answers`, `instructions_pending` + Finanz-Summen `fin_revenue`, `fin_ebitda`, `fin_cashflow`, `fin_liquidity` + Audit-Kennzahlen `audits`, `audits_planned`, `audits_in_progress`, `audit_findings_open`, `audit_findings_overdue`, `audits_done` + weitere `tenders_awarded`, `machines`, `deadlines`, `leave_requests`, `production_orders_done` |
| `/analytics/trends` | `metrics.view` | Layer 7: Trend je Metrik — letzter Wert, Vorgänger, `delta`, `direction` (up/down/flat/unknown) |
| `/insights` | `metrics.view` | Query: `severity=critical|warning|info`, `code=` — Regelbasierte Hinweise (KI-Steuerung): `code`, `severity` (critical/warning/info), `message`, `data` — Codes: `tasks_overdue`, `tasks_due_soon`, `compliance_rate_low`, `high_risks_open`, `risk_reviews_overdue`, `risk_reviews_due_soon`, `deadlines_overdue`, `deadlines_due_soon`, `instructions_overdue`, `instructions_due_soon`, `inspections_overdue`, `inspections_due_soon`, `measures_overdue`, `orders_overdue`, `machines_in_maintenance`, `fin_negative_liquidity`, `fin_reports_missing`, `leave_requests_pending`, `leave_pending_stale`, `leave_active_today`, `tenders_open`, `tenders_overdue`, `tenders_deadline_soon`, `tenders_no_applications`, `expert_profiles_incomplete`, `documents_no_version`, `persons_without_company`, `companies_no_persons`, `orders_no_machine`, `inspections_no_result`, `op_instructions_review`, `questions_stale`, `orders_machine_maintenance`, `machines_idle`, `instructions_no_person`, `projects_no_owner`, `investments_stale_value`, `documents_no_category`, `expert_profiles_no_rate`, `persons_no_contact`, `leave_overlap`, `applications_no_price`, `audits_no_findings`, `risk_assessments_no_person`, `ai_analyses_failed`, `tenders_no_budget`, `machines_zero_capacity`, `inspections_no_person`, `instructions_renewal_due`, `data_objects_no_category`, `audits_no_result`, `instructions_no_completed_at`, `applications_stale`, `questions_no_answers`, `op_instructions_no_document`, `instructions_no_document`, `tenders_awarded_no_winner`, `projects_done_incomplete`, `orders_done_incomplete`, `questions_no_accepted`, `orders_running_no_start`, `orders_done_no_finish`, `projects_no_strategy`, `investments_no_value`, `leave_decided_no_stamp`, `participations_exited_with_stake`, `risks_no_measures`, `deadlines_completed_no_stamp`, `audits_no_auditor`, `applications_no_proposal`, `tasks_done_no_stamp`, `data_objects_empty`, `graph_edges_no_relation`, `measures_no_project`, `projects_stalled`, `questions_unassigned`, `deadlines_no_subject`, `strategies_no_projects`, `projects_no_measures`, `portfolios_no_investments`, `projects_overdue`, `projects_ending_soon`, `questions_open`, `graph_orphans`, `measures_due_soon`, `orders_due_soon`, `strategies_ending_soon`, `strategies_overdue`, `investments_drawdown`, `participations_capital_need`, `tasks_unassigned`, `audit_findings_overdue`, `audit_findings_critical`, `audit_findings_due_soon`, `audit_findings_unassigned`, `audits_starting_soon`, `audits_overdue`, `audits_unassigned`, `op_instructions_draft`, `measures_unassigned`, `deadlines_unassigned`, `inspections_unassigned`, `instructions_unassigned`, `orders_unassigned`, `projects_unassigned`, `all_clear`, `members_never_logged_in` |
| `/nav-counts` | `metrics.view` | Sidebar-Badges server-seitig: `{sektion: [überfällig, heute]}` — SQL-Counts statt 30 List-Requests |
| `/search?q=` | `metrics.view` | Globale Suche über alle Module (inkl. Data Lake, Graph, KI-Analysen, Events): `[{section, id, label}]` — `q` ≥ 2 Zeichen, optional `sections=tasks,companies`, `limit=1–50` (Default 20) |
| `/notifications` | `metrics.view` | Datenbank-Benachrichtigungen des Users (z. B. aus `compliance:remind` + Modul-Events): `[{id, kind, title, due_at, entity_id, read, created_at}]` — `per_page`/`limit` ≤ 200 (Default 10), `unread=1` nur ungelesene, `kind=` nach Art filtern. Kinds: `unterweisung`, `pruefung`, `frist`, `feststellung`, `audit`, `massnahme`, `aufgabe`, `auftrag`, `gefaehrdungsbeurteilung`, `projekt`, `ausschreibung`, `frage`, `antwort`, `urlaub`, `rollen`, `unterweisung_wiederholung` (Wiederholungsintervall abgelaufen). Benachrichtigungs-Auslöser: Zuweisung an `responsible_id`/`assignee` (User-Modelle), verknüpfte Personen via E-Mail→User (`person_id` bei Unterweisung/Prüfung/GB, `assigned_to` bei Aufträgen), sowie Modul-Events (Fragen, Antworten, Urlaub, Ausschreibungen, Team), Status-Abschluss der Person (`completed` bei Unterweisung/Prüfung, `done` bei Aufträgen) und `compliance:remind`/`tasks:remind` |
| POST `/notifications/{id}/read` | `metrics.view` | Benachrichtigung als gelesen markieren |
| POST `/notifications/{id}/unread` | `metrics.view` | Benachrichtigung als ungelesen markieren (404 bei fremder) |
| POST `/notifications/read-all` | `metrics.view` | Alle ungelesenen Benachrichtigungen als gelesen markieren |
| POST `/notifications/delete-read` | `metrics.view` | Alle gelesenen Benachrichtigungen löschen — `{deleted: n}` |
| `/notifications/unread-count` | `metrics.view` | Anzahl ungelesener Benachrichtigungen: `{count}` |
| DELETE `/notifications/{id}` | `metrics.view` | Einzelne Benachrichtigung löschen (404 bei fremder) |
| POST `/demo-seed` | `roles.manage` | Demodaten für den aktuellen Tenant laden (idempotent; wie `php artisan demo:seed {tenant}`) |

## DataLake (`datalake`) — Layer 4

| Pfad | Recht | Zweck |
|---|---|---|
| `/data-objects` | `datalake.view` / `datalake.manage` | Objektspeicher (PDFs, Bilder, Verträge, CAD, Produktionsdaten) — Filter: `category`, `q` (Name), `mime` (Präfix, z. B. `image/`) |
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
| `/ai-analyses` | `ai.view` / `ai.manage` — Liste: `?status=`, `?kind=` | Analyse-Runs (provider-agnostic; Default `heuristic`, LLM-Provider steckbar) |

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
