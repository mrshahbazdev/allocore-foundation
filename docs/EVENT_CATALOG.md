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
| `strategy.*` / `project.*` / `measure.*` | CorporateDev | Unternehmensentwicklung (in Review, PR #10) |
| `portfolio.*` / `investment.*` | Investments | Investments (PR #11) |
| `participation.*` | Participations | Beteiligungen (PR #12) |
| `machine.*` / `production_order.*` | Production | Zahntechnik-Produktion (PR #13) |

## Lesen

`GET /api/v1/events?type=task.created&per_page=50` (Recht: `metrics.view`).
