# Phase 12 — Alerting

[Roadmap index and rules](ROADMAP.md) · [Agent router](../../AGENTS.md)

## Goal

Add actionable alerts: an incident monitoring and inventory risk notification system with deterministic rule evaluation, guaranteed deduplication, and seamless navigation to the analytics context.

## Functionality

- Monitoring rule management: create, edit, delete, enable/disable (toggle).
- Supported inventory metrics: days of stock (`days_of_stock`), physical available quantity (`quantity_available`), total inventory value (`inventory_value`).
- Supported rule types: critical shortage (`critical_stock`), zero stock (`out_of_stock`), excess stock (`overstock`), reorder point (`reorder_point`).
- Severity levels: `critical`, `warning`, `info`.
- Full alert lifecycle: `OPEN` (new) $\rightarrow$ `ACKNOWLEDGED` (being handled) $\rightarrow$ `RESOLVED` (resolved with a note/reason).
- Deterministic consumption rate and stock calculations based on `fact_inventory_daily` snapshots and 30-day sales history.
- Navigation to the analytics context: alerts contain a direct link to `/inventory?warehouse_id=...&search=...` for immediate in-depth analysis.
- Multi-tenant boundary: strict workspace-level isolation of rules and alerts.

## Exit Criteria

- [x] **Rules are deterministic**: rules are strictly typed using Value Objects (`RuleCondition`, `RuleScope`) and Enums (`RuleMetric`, `RuleComparator`, `RuleType`); evaluation is unambiguous.
- [x] **Execution is idempotent**: repeated runs recalculate current metric values for active alerts without creating duplicate records.
- [x] **Guaranteed deduplication**: enforced in the domain, in `EvaluateAlertRulesHandler` through `DedupFingerprint` calculation, and in PostgreSQL through the partial unique index `uq_alerts_active_dedup` on `(workspace_id, dedup_fingerprint)` WHERE `status IN ('open', 'acknowledged')`.
- [x] **Lifecycle covered by tests**: the full status transition lifecycle is covered by Unit, Feature, and E2E tests.
- [x] **OpenAPI 3.0.3 contract**: `/alert-rules*` and `/alerts*` endpoints defined, Redocly validation reports 0 errors, types generated.
- [x] **End-to-end integration**: verified in Docker using `scripts/verify-integration.sh`.

## Progress

- **Completed:**
  - OpenAPI 3.0.3 contract updated (`contracts/openapi/analytics-v1.yaml`) and validated with 0 errors.
  - PostgreSQL migrations (`2026_09_23_000040_create_alert_rules_table.php`, `2026_09_23_000041_create_alerts_table.php`) created and applied.
  - Pure domain model (Pure DDD) `App\Modules\Alerting\Domain` fully decoupled from the Laravel framework (confirmed by `ArchitectureTest`).
  - Repositories (Eloquent and InMemory) and the `PostgresInventoryAlertSource` adapter implemented.
  - CQRS Commands and Queries implemented.
  - REST controllers `AlertRuleController` and `AlertController` registered in `routes/api.php` with authentication middleware and tenant isolation.
  - `AlertingSeeder` with demo rules and alerts registered in `DatabaseSeeder` and executed.
  - Frontend typed gateway `alertsGateway` implemented with vitest tests.
  - Alert section UI components (`AlertSummaryCards`, `AlertTable`, `AlertRuleList`, `AlertRuleDialog`, `AlertResolveDialog`, `AlertsView`) and `app/(dashboard)/alerts/page.tsx` page developed.
  - "Alerts" navigation item enabled in the sidebar.
  - End-to-end integration steps added to `scripts/verify-integration.sh`.
- **Remaining:** nothing; all exit criteria met.
- **Blockers:** none.
- **Next step:** Phase 13 — Domain Events and Transactional Outbox.

## Completion Verification

- **Date:** 2026-09-23
- **Exit Criteria confirmation:**
  - Deterministic rules, strict idempotency, deduplication, the full alert lifecycle, and navigation to `/inventory` confirmed by tests.
- **Verification commands and results:**
  - `npm --prefix frontend run contracts:validate` — 0 errors (Redocly).
  - `npm --prefix frontend run api:generate` — TypeScript types updated.
  - `npm --prefix frontend run lint` — ESLint passed without issues.
  - `npm --prefix frontend run format:check` — Prettier formatting compliant.
  - `npm --prefix frontend run typecheck` — TypeScript compilation without errors.
  - `npm --prefix frontend test` — 45 test files, 193 vitest tests PASS.
  - `npm --prefix frontend run build` — Next.js 16 production build PASS (`/alerts` dynamic route).
  - `composer --working-dir=backend validate --strict` — composer.json valid.
  - `composer --working-dir=backend lint` — Pint and PHPStan (level 8) PASS, 0 errors.
  - `composer --working-dir=backend test` — 253 PHPUnit tests, 29272 assertions PASS.
  - `make check` — Full set of static checks and tests PASS.
  - `./scripts/verify-integration.sh` — All 43 end-to-end integration steps with live PostgreSQL/Redis/Backend/Frontend containers PASS.
