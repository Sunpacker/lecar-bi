# Autonomous Agent Router

Monorepo: Next.js frontend, Laravel analytics service, PostgreSQL, Redis, and OpenAPI.
Architecture is documented in `docs/architecture/`; paths are relative to the repository root, and Markdown links are relative to this file.

## Sources of Truth

When sources conflict, apply this priority order: user task → `AGENTS.md` → `docs/roadmap/` →
`docs/architecture/12-architecture-decisions.md` → other architecture documents → tests → implementation → assumptions.

If the code diverges from the architecture, determine why; follow the architecture by default.
Record intentional architecture changes in the relevant document and an ADR.

## Reading Routes

Always read this file, applicable nested `AGENTS.md` files, and the [roadmap index](docs/roadmap/ROADMAP.md), then the first unfinished phase and its exit criteria.
Start reading the architecture with the [architecture documentation index](docs/architecture/README.md), then open documents along the route for the task.
Before starting a phase, read documents 00, 01, and 12; read document 11 when planning evolution, and the others as relevant to the task.
Combine routes for tasks spanning multiple areas. Do not load future roadmap phases or the entire repository without a reason.

| Task Area                       | Architecture Document Numbers Below |
| ------------------------------- | ----------------------------------- |
| Frontend                        | 00, 01, 03, 07, 10                   |
| Laravel / Domain                | 00, 04, 05, 06, 10, 12               |
| API / Inter-Service Integration | 01, 02, 07, 08                       |
| Infrastructure                  | 02, 09, 10                           |
| Analytics                       | 05, 06, 08                           |
| RAG / Support Chat              | 00, 01, 03–10, 12, RAG Support Chat  |

For any task involving RAG, support chat, the knowledge base, retrieval, embeddings, or AI providers,
also read [RAG Support Chat](docs/architecture/rag-support-chat.md).

- [00 — Product Overview](docs/architecture/00-overview.md)
- [01 — System Architecture](docs/architecture/01-system-architecture.md)
- [02 — Monorepo and Services](docs/architecture/02-monorepo-and-services.md)
- [03 — Next.js Frontend](docs/architecture/03-frontend-nextjs.md)
- [04 — Laravel DDD Backend](docs/architecture/04-backend-laravel-ddd.md)
- [05 — Bounded Contexts](docs/architecture/05-bounded-contexts.md)
- [06 — Data and Analytics](docs/architecture/06-data-and-analytics.md)
- [07 — API and Integration](docs/architecture/07-api-and-integration.md)
- [08 — Events, Outbox, and Async](docs/architecture/08-events-outbox-async.md)
- [09 — Infrastructure, Deployment, and Observability](docs/architecture/09-infrastructure-deployment-observability.md)
- [10 — Testing and Quality](docs/architecture/10-testing-and-quality.md)
- [11 — Evolution and Roadmap](docs/architecture/11-evolution-and-roadmap.md)
- [12 — Architecture Decisions](docs/architecture/12-architecture-decisions.md)
- [RAG Support Chat — Architecture and Implementation Order](docs/architecture/rag-support-chat.md)

## Agent Selection

| Model           | Role                    | When to Assign                                                                                                                    |
| --------------- | ----------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| GPT-5.6         | Integrator / Architect  | Cross-service changes, OpenAPI, DDD boundaries, major refactoring, complex debugging, migration order, final integration             |
| Claude Opus 4.6 | Domain / Review         | Domain modeling, implementation and refactoring within one context, cohesion, simplification, test quality, Domain independence from Laravel |
| Gemini Pro      | Repository / Analysis   | Broad analysis, dependency mapping, recurring patterns, documentation, migration inventory, test coverage gaps                      |

- One agent: one feature in one service without an architectural decision.
- Two agents: implementation + review, analysis + implementation, or frontend + backend after the contract is fixed.
- Three agents: only for independent tasks; Gemini analyzes, GPT-5.6 designs and integrates, and Claude implements Domain logic or reviews.
- Assign a separate reviewer for important changes; the nature of the task matters more than the default model.

## Scope, Parallel Work, and Handoff

Before starting, define the objective, allowed and prohibited directories, contract, dependencies, and completion criteria.
Primary write scopes: `apps/web/**`, `services/analytics/**`, `contracts/**`, `docs/**`, `infra/**`.
Parallel tasks are allowed within the current phase when write scopes do not overlap.
Do not assign simultaneous changes to the same bounded context to two agents.
Fix the shared contract before parallel implementation and do not change it until implementation is complete.
The integrator assigns file ownership, collects results, and is responsible for shared checks; agents must not revert others' changes.

Areas with a high risk of conflicts: root dependencies/config, Docker Compose, OpenAPI, CI, shared TypeScript config, Laravel service providers, routes, and migrations; assign a dedicated integration task.

Every handoff must include:

- Objective and current state.
- Write Scope and Read Scope, including relevant architecture documents.
- Contract, dependencies, and Acceptance Criteria.
- Validation: checks performed, results, and remaining checks.
- Known risks and the next concrete step; "finish the backend" is insufficient.

## Responsibility Boundaries

- **Frontend:** UI, routing, Server/Client Components, state, dashboards, charts, tables, filters, mapping, and the generated API client.
- **Laravel:** Domain, Application, Infrastructure, Presentation, persistence, calculations, import, queues, events, and alert rules.
- **OpenAPI:** the single public boundary; Eloquent models are not the API contract.
- When changing an API: contract → compatibility check → backend → frontend client generation → tests.
- Frontend and backend must not define requests/responses independently.

Key constraints; see the reading routes in section 2 for details:

- The backend is one deployable analytics service, with modules organized first by bounded context, then by layer.
- Initial contexts: Workspace, Data Ingestion, Sales Analytics, Inventory Analytics, Supplier Analytics, Dashboard, Alerting.
- Every backend feature belongs to a context; do not create a shared `Services` area for unrelated business rules.
- Domain is independent of Laravel and outer layers; Infrastructure dependencies point inward.
- Do not place domain rules in controllers, jobs, Eloquent models, resources, CLI commands, or listeners.
- CQRS-lite: commands change state, queries read it; for BI, prefer specialized read models over large object graphs.
- The frontend is feature-oriented: routes, features, entities, widgets, shared UI, API access, and utilities; do not copy backend DDD into React.
- Prefer server-side fetching when it reduces client state; use Client Components for interactivity.
- The frontend formats data but does not duplicate backend calculations or redefine business meaning.
- PostgreSQL stores persistent analytics data; Redis handles technical tasks.
- Other services must not read the analytics DB: exchange data through versioned APIs or integration events.
- Analytical tables, projections, aggregates, and materialized views are allowed.
- Domain Events are internal; Integration Events are external contracts, without raw domain objects.
- Use Outbox for reliable publishing and make consumers idempotent; add a broker only according to the roadmap.

## Autonomous Workflow

Repeat this cycle until the agreed task is complete or an external blocker is confirmed; do not stop at a plan.

1. **Define the objective.** Identify the expected outcome, scope, and verifiable acceptance criteria from the user's request.
2. **Restore context.** Follow the reading route, identify the phase, service, and context; check `git status` and existing changes.
3. **Choose the task.** Carry out the explicit request; when asked to continue the roadmap, take the smallest task in the current phase that is ready to start.
   Priority: blockers → failing tests → unmet exit criteria → integration → necessary tests and documentation.
4. **Check dependencies.** Locate the required contracts, implementation, validation commands, and available tools; distinguish facts from assumptions.
5. **Make a short plan.** Identify files, APIs/data, checks, risks, and the sequence of steps; make simple, reversible decisions independently.
6. **Organize execution.** Choose one agent or independent subtasks according to sections 3–4; provide each agent with scope and acceptance criteria.
7. **Implement a cohesive change.** Follow the API change order from section 5; complete a vertical slice without unrelated cleanup.
8. **Validate the result.** Run applicable formatters, lint, static analysis, unit, integration, contract, and E2E tests from the project configuration.
   Check the result against each acceptance criterion; for documentation, check links, consistency, and formatting constraints.
9. **Fix and repeat.** If a check fails, determine the cause, fix it, and rerun the affected checks; do not weaken tests to make them pass.
   If the cause remains unchanged, change the hypothesis or diagnostic approach; handle external unavailability according to section 7.
10. **Review.** Check the diff, architectural boundaries, duplication, edge cases, compatibility, migrations, race conditions, and idempotency.
    Resolve blocking / important findings and return to step 8; optional findings must not expand scope unnecessarily.
11. **Record progress.** For roadmap work, update the phase: completed work, remaining work, blockers, and the next step; the integrator updates the index.
    Close a phase only after confirming all exit criteria and the required checkpoint; a documentation change alone does not close a phase.
12. **Decide what comes next.** If the task is complete, report the result; if work remains within the agreed scope, return to step 3.
    Proceed to the next phase only when exit criteria are met and the assignment covers that phase.

Final report: outcome, changed files, checks performed and their results, remaining limitations or a specific blocker.
Explicitly identify checks that were not run; do not claim CI passed without confirmation.

## Autonomy and Constraints

- Choose local names, private helpers, test organization, and internal refactoring within scope independently.
- Do not request permission again for work already assigned; continue available independent steps while awaiting a response.
- Clarify only unknown requirements that affect the outcome or actions beyond your authority; ask one specific question with a recommendation.
- When blocked, record the cause, attempted actions, and required input or access; do not repeat an action known to fail without changing anything.
- Do not perform destructive operations, publish, deploy, or send external messages without appropriate user authorization.
- Without a separate architectural task, do not change service boundaries, context/DB ownership, API/auth strategy,
  messaging technology, major runtime dependencies, phase order, or recorded decisions.
- When changing the architecture, update the relevant documents; record lasting decisions in document 12 or a separate ADR.
- Document changes to integrations, events, the deployment model, and the testing strategy.
- Check existing solutions before adding a dependency; do not add a library for a trivial utility.
- Keep migrations narrow, reviewable, safe to apply forward, and compatible with deployment order where possible; analyze destructive changes separately.
- Do not commit secrets, log credentials/tokens, or expose exception details or sensitive values in frontend config.
- The backend is responsible for authorization and validation; client-side state is not a source of trust.
- Avoid N+1 queries, large aggregations in PHP memory, full datasets instead of summaries, and repeated expensive calculations without projections.
- SQL/backend handles heavy processing; optimize as needed, without premature complexity.

## Definition of Done

- Required behavior is implemented; necessary tests are added and pass, and applicable lint/static analysis and CI pass.
- The contract, generated client, migrations, and architecture documentation are updated if affected.
- No mandatory TODOs remain unresolved and no unrelated changes are included; the architecture and current roadmap phase are respected.
- Do not proceed to the next phase until the current phase's mandatory exit criteria are met.

## MCP

Connected MCPs are listed in `.agents/mcp.json`.
Use Context7 to obtain up-to-date library and framework documentation before implementation if APIs or recommended approaches may have changed.
