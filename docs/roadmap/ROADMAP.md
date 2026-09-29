# AutoBI — Autonomous Development Roadmap

## How to Read

1. Read [AGENTS.md](../../AGENTS.md) and this index.
2. Open only the first unfinished phase in the list below — this is the current phase.
3. Read the [architecture index](../architecture/README.md), then the necessary architecture documents and files for the current task.
4. Open past phases only to check dependencies; future phases only for an explicit planning task.

Do not load the entire roadmap folder. The next phase is not active until the current phase's exit criteria are met.
The phase order defines dependencies; the list contains only names and completion markers.

## Execution

`[ ]` — phase incomplete; `[x]` — all exit criteria and required checks passed.
This list is the single source of truth for phase status; a separate current-phase pointer is unnecessary.
At the time the roadmap was split, the repository contained documentation, but bootstrap was not yet complete.

- [x] [Phase 0 — Repository Bootstrap](00-bootstrap.md)
- [x] [Phase 1 — Development Foundation](01-development-foundation.md)
- [x] [Phase 2 — Identity, Workspace and Access Boundary](02-identity-workspace-access.md)
- [x] [Phase 3 — Demo Data Model](03-demo-data-model.md)
- [x] [Phase 4 — Sales Analytics Vertical Slice](04-sales-analytics.md)
- [x] [Phase 5 — Sales Drill-Down and BI Interaction Model](05-sales-drill-down.md)
- [x] [Phase 6 — Inventory Intelligence](06-inventory-intelligence.md)
- [x] [Phase 7 — ABC/XYZ Analysis](07-abc-xyz-analysis.md)
- [x] [Phase 8 — Dashboard Builder](08-dashboard-builder.md)
- [x] [Phase 9 — Shared Filters and Saved Views](09-shared-filters-saved-views.md)
- [x] [Phase 10 — Data Ingestion](10-data-ingestion.md)
- [x] [Phase 11 — Supplier Analytics](11-supplier-analytics.md)
- [x] [Phase 12 — Alerting](12-alerting.md)
- [x] [Phase 13 — Domain Events and Transactional Outbox](13-events-outbox.md)
- [x] [Phase 14 — Notification Service Extraction Exercise](14-notification-service.md)
- [x] [Phase 15 — RBAC](15-rbac.md)
- [x] [Phase 16 — Performance and Caching](16-performance-caching.md)
- [x] [Phase 17 — Observability](17-observability.md)
- [x] [Phase 18 — Production Hardening](18-production-hardening.md)
- [x] [Phase 19 — Forecasting Extension](19-forecasting.md)

## Updating Progress

- After working on a phase, record a "Progress" section in its file: completed work, remaining work, blockers, and the next step.
- When closing a phase, add a "Completion Verification" section: date, exit criteria confirmation, verification commands, and results.
- Only then replace `[ ]` with `[x]` in the index in the same change; documentation alone does not complete a phase.
- If criteria are no longer met, clear the checkbox and describe the reason in the phase file.
- During parallel work, the integrator updates the index; coordinate changes to shared files sequentially.

## Architectural Context

Before starting a phase, read:

- [00 — Overview](../architecture/00-overview.md).
- [01 — System Architecture](../architecture/01-system-architecture.md).
- [12 — Architecture Decisions](../architecture/12-architecture-decisions.md).

Select topic-specific documents using the routes in `AGENTS.md` and links in the current phase.
[11 — System Evolution](../architecture/11-evolution-and-roadmap.md) is required when planning or changing architecture, not for every implementation.
All backtick-enclosed paths in phase files are relative to the repository root; short architecture filenames refer to `docs/architecture/`.
In case of conflicts, apply the source priority from `AGENTS.md` and agree on the architectural decision first.

## Choosing the Next Task

1. Unblock the current phase.
2. Fix its failing tests.
3. Satisfy missing exit criteria.
4. Complete integration of already implemented parts.
5. Add tests for current behavior.
6. Update necessary documentation.
7. Proceed only after meeting the exit criteria.

Do not jump to complex infrastructure just because it is more interesting.
Prefer one complete vertical slice: a clear outcome, one bounded context, limited write scope, tests, and acceptance criteria.
Separate unrelated contexts, frontend/backend work before the contract is fixed, architectural decisions from routine implementation, and infrastructure migrations from product features.

## Parallel Work

Work in parallel only within the current phase, on independent tasks with non-overlapping write scopes.
Usually safe: frontend/backend against a frozen API, docs and tests outside actively modified files, and analysis-only inspection.
Do not modify OpenAPI, migrations, one bounded context, Docker Compose, root dependencies, or the same files in a refactor/feature concurrently.
Model roles and handoff rules are defined in `AGENTS.md`; the nature of the task matters more than the default model.

## Integration Checkpoints

A required checkpoint is specified in the corresponding phase file and is part of its completion conditions.
Check the build, frontend/backend contract compatibility, architecture, migrations, test suite, documentation, accidental coupling, and exit criteria.
The preferred integration agent is GPT-5.6.

## Product Demonstration

Read [Portfolio Completion Target](portfolio.md) only when preparing a demonstration or assessing product readiness.
