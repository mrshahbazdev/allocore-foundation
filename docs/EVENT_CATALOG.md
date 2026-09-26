# Dokument D — Event Catalog (R5 Event First)

Jede Domain-Aktion wird als `DomainEvent` in `stored_events` gespeichert
(`spatie/laravel-event-sourcing`), `tenant_id` in `meta_data`.

## Mechanik

`Modules/DataPlatform/Support/ActivityRecorder.php` hört auf die Eloquent-Events
`created`, `updated`, `deleted` aller Modelle in `ActivityRecorder::WATCHED` und
emittiert `DomainEvent(type, tenantId, subject{type,id,title}, payload)`.
Neue Modelle: nur in `WATCHED` eintragen — keine Änderung am Listener nötig.

## Event-Typen (`{prefix}.{created|updated|deleted}`)

| Prefix | Modell | Beschreibung |
|---|---|---|
| `company.*` | Core\Company | Unternehmen angelegt/geändert/gelöscht |
| `person.*` | Core\Person | Personenstammdaten |
| `document.*` | Documents\Document | Dokument inkl. Uploads |
| `task.*` | Tasks\Task | Aufgaben |
| `instruction.*` | Compliance\Instruction | Unterweisungen |
| `inspection.*` | Compliance\Inspection | Prüfungen |
| `deadline.*` | Compliance\Deadline | Fristen |
| `risk_assessment.*` | Compliance\RiskAssessment | Gefährdungsbeurteilungen |
| `operating_instruction.*` | Compliance\OperatingInstruction | Betriebsanweisungen |
| `expert_profile.*` | ExpertNetwork\ExpertProfile | Expertenprofile |
| `question.*` | ExpertNetwork\Question | Fragen |
| `answer.*` | ExpertNetwork\Answer | Antworten |
| `tender.*` | ExpertNetwork\Tender | Ausschreibungen |
| `tender_application.*` | ExpertNetwork\TenderApplication | Bewerbungen/Award |
| `strategy.*` / `project.*` / `measure.*` | CorporateDev | Strategien, Projekte, Maßnahmen |
| `portfolio.*` / `investment.*` | Investments | Portfolios und Einzelinvestitionen |
| `participation.*` | Participations | Beteiligungen |
| `machine.*` / `production_order.*` | Production | Maschinen, Produktionsaufträge |
| `financial_report.*` | Finance | Monatliche Finanzberichte |
| `leave_request.*` | HR | Urlaubs-/Abwesenheitsanträge |
| `data_object.*` | DataPlatform | Data-Lake-Objekte |
| `graph_entity.*` / `graph_edge.*` | DataPlatform | Knowledge-Graph Entitäten/Kanten |
| `ai_analysis.*` | Ai | KI-Analysen |
| `exec_report.*` | Executive | Executive-Reports |
| `audit.*` / `audit_finding.*` | Audits | Audits und Feststellungen |
| `user.added` / `user.roles_updated` / `user.removed` | Core | Mitglied angelegt, Rollen geändert, Mitglied entfernt (manuell emittiert, kein Eloquent-Event) |
| `role.created` / `role.permissions_updated` / `role.deleted` | Core | Eigene Rolle angelegt, Rechte geändert, Rolle gelöscht (manuell emittiert) |
| `tenant.created` / `tenant.updated` | Core | Mandant angelegt (Zentral-API) / umbenannt (manuell emittiert) |

## Lesen

`GET /api/v1/events?type=task.created&per_page=50` (Recht: `metrics.view`).
