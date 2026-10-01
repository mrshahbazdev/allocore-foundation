# Changelog

All notable changes to ALLOCORE Foundation. Format follows
[Keep a Changelog](https://keepachangelog.com/de/1.1.0/).

## [1.0.0] — 2026-10-01

First tagged release. ~1000 PRs of build-out consolidated:

### Platform
- 17 modules: Core (Stammdaten), Documents, Tasks, Compliance, ExpertNetwork,
  CorporateDev, Investments, Participations, Production, Finance, Hr, Audits,
  DataLake, KnowledgeGraph, DataPlatform, Ai, Executive
- Single-DB multi-tenancy via stancl/tenancy (`BelongsToTenant` global scope
  on all tenant models; `TenantIsolationTest` sweep guards cross-tenant leaks)
- Per-tenant RBAC (spatie/permission), tenant-scoped `exists` validation,
  assignee/owner limited to tenant members, lockout + escalation guards
- Event store (spatie/laravel-event-sourcing) with German event catalog,
  actor attribution, full filter matrix + NDJSON export
- KPI warehouse: `metric_snapshots` + `metrics:ingest` (webhook events →
  ext_* metrics, per-`occurred_at`-month buckets) + derived KPIs
  (`GET /api/v1/kpis`: margins, CAC, conversion, lab KPIs)
- Webhook ingest (`POST /api/v1/webhooks/{token}`), pull connectors,
  anonymized digital-twin layer for the AI coach
- Notifications hub: ~30 triggers, mute prefs, batch ops, exports
- Insights engine (~90 codes) + `insights:notify` digests
- KI-Coach: `ai:coach` weekly digest over anonymized layer
  (LlmAnalysisProvider OpenAI-compatible, heuristic fallback)
- Workspace UI: one Alpine/Blade app — lists, drawers, command palette,
  keyboard shortcuts, DE/EN i18n, dark mode
- Connect flow: OAuth-style "Sign in with Allocore Manager"
  (`/connect/authorize` + `/api/v1/connect/exchange`)

### Operations
- Dockerfile: FrankenPHP/Caddy web + separate `scheduler`/`worker` process
  groups; Apache `.htaccess` for shared hosting
- Retention: `data:prune` (events/snapshots/twins), `notifications:prune`
- `/api/v1/health` readiness, `/api/v1/version`, API tokens with abilities,
  rate limiting, password policy, security headers
- CI: pint + php artisan test on every PR; automerge sweep

### Notes
- `public/build` stays committed intentionally — the shared Apache host
  has no Node toolchain, so compiled assets must come from the repo.
