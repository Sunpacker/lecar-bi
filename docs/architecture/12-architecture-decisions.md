# 12. Architecture Decisions

The status of individual planned extensions is specified in the corresponding ADR; such an ADR does not imply
that an implementation already exists or that the roadmap order has changed.

## ADR-001 — Monorepo

Decision: keep frontend, backend, contracts, infrastructure, and docs in a single Git repository.

Rationale: coordinated development, a unified change history, and convenient contract management.

## ADR-002 — Independent Deployable Services

Decision: Next.js and Laravel are separately deployable services.

Rationale: independent lifecycles and the ability to scale them separately.

## ADR-003 — Laravel as Analytics Microservice

Decision: Laravel acts as an independent analytics service.

Rationale: the backend must have its own responsibility rather than merely being a technical application for the frontend.

## ADR-004 — DDD inside Laravel

Decision: use DDD with separation into bounded contexts.

Rationale: the project has several independent domains and must remain extensible.

## ADR-005 — Module First

Decision: organize the backend first by bounded context, then by architectural layer.

Rationale: makes boundaries easier to understand and simplifies later extraction of a module into a separate service.

## ADR-006 — CQRS-lite

Decision: logically separate commands and queries without requiring physically separate stores.

Rationale: BI is a read-heavy system and requires specialized read models.

## ADR-007 — OpenAPI Contract

Decision: describe the HTTP API using OpenAPI.

Rationale: a single formal contract and the ability to generate a frontend client.

## ADR-008 — Database per Service

Decision: each microservice owns its database.

Rationale: reduce coupling and ensure service independence.

## ADR-009 — Domain Events and Integration Events

Decision: separate internal domain events from external integration events.

Rationale: the domain model must not depend on how messages are delivered between services.

## ADR-010 — Transactional Outbox

Decision: provide the Outbox Pattern for publishing integration events.

Rationale: improve the reliability of inter-service communication.

## ADR-011 — Redis

Decision: use Redis for technical tasks involving caching, queues, locks, and rate limiting.

Rationale: Laravel has mature Redis integration, and these scenarios are well suited to Redis.

## ADR-012 — No Premature Microservices

Decision: do not extract separate microservices unnecessarily.

Rationale: avoid increasing operational complexity before real requirements arise.

## ADR-013 — Frontend Has No Domain Business Rules

Decision: perform domain calculations and business rules on the backend.

Rationale: a single source of truth and the ability for multiple consumers to use the backend.

## ADR-014 — Analytics-specific Read Models

Decision: allow specialized read models, materialized views, and aggregate structures.

Rationale: DDD must not impair analytical query performance.

## ADR-015 — Independent Infrastructure Evolution

Decision: add a message broker, distributed tracing, and other infrastructure components incrementally.

Rationale: the architecture must be ready to incorporate them, but need not include them in the first release.

## ADR-016 — shadcn/ui and Tailwind CSS

Decision: the frontend must use shadcn/ui together with Tailwind CSS: shadcn/ui for reusable UI components, Tailwind CSS for styling and responsive layouts.

Rationale: a unified UI stack ensures interface consistency and simplifies maintenance of shared components.

Usage rules are described in the [frontend architecture](03-frontend-nextjs.md#required-ui-stack).

## ADR-017 — Redis Stream as Replaceable Integration Event Transport

Decision: Redis Stream `autobi.integration-events` is the initial transport for integration events, implemented as a replaceable adapter through `IntegrationEventTransportInterface`. The Domain and Application layers do not depend on Redis.

Rationale: a full message broker (RabbitMQ, Kafka) is not needed at this stage. Redis is already a required infrastructure dependency. The transport is encapsulated behind a port, so replacing it will not affect Domain or Application.

Constraints:

- Delivery semantics: at-least-once. Consumers must deduplicate by `event_id`.
- Global event ordering is not guaranteed.
- PostgreSQL outbox is the source of truth; Redis Stream is only a delivery channel.
- Stream retention is not managed in Phase 13; it will be added once a consumer and observability are available.
- The consumer group will be created in Phase 14.

## ADR-018 — Notification Service Extraction and Autonomous Bounded Context

Decision: extract `notification/` as the third independently deployable service (Laravel 13, PHP 8.3), with its own PostgreSQL database, consuming `alert.triggered.v1` from Redis Stream `autobi.integration-events` through the `notification-service-v1` consumer group.

Rationale: demonstrate that the system can scale and expand modularly through a separate service without creating a shared database (ADR-008) or prematurely complicating the infrastructure (ADR-012, ADR-015).

Rules and constraints:

- **Database per Service:** Notification Service owns its PostgreSQL database (`notification-postgres`). No foreign keys, shared tables, or access to analytics PostgreSQL.
- **Event-Driven Integration:** Services exchange data exclusively through asynchronous events. There are no direct HTTP calls from analytics to notification or vice versa to enrich data.
- **Self-contained Contract:** The canonical `alert.triggered.v1` event contains all data (rule, severity, threshold, current value, analytical context) needed to create the notification's title, text, and context.
- **At-Least-Once Delivery and Deduplication:** The service guarantees correctness under redelivery through the `consumed_events` table with a unique `event_id` primary key. The `notifications` projection and `consumed_events` are inserted in a single local transaction.
- **Acknowledgment (XACK) and Poison Messages:** `XACK` is performed strictly after a database commit or duplicate detection. Invalid messages and unsupported event versions are sent to the `autobi.integration-events.dead-letter` stream, followed by XACK.
- **Lifecycle Independence:** Temporary unavailability or failure of Notification Service does not affect the analytics service, HTTP API, or Outbox publication. Accumulated events are processed once the consumer group recovers.

## ADR-019 — RAG Support Chat

Status: target design for a planned extension, 2026-09-23. Implementation begins as a separate
task within the agreed roadmap; this ADR does not change existing phases or runtime behavior.
Refined on 2026-09-27: secure RAG model, document permissions, fail closed, and authorization caching.

Decision: implement documentation-based support in the `Support` and `KnowledgeBase` contexts within
the analytics service. Use PostgreSQL/pgvector + FTS, Laravel Queue, and the existing
Workspace/auth boundary. Next.js displays the chat and forwards API/SSE traffic; Laravel owns RAG orchestration.

Rationale: reuse the platform's infrastructure and authorization, preserve DDD boundaries,
and measure quality on a small corpus before complicating retrieval or extracting a service.

Main constraints:

- The MVP works only with explicitly published shared documentation/FAQ, without access to actual
  BI data and without tool calling. Private workspace knowledge is a later extension.
- Conversations are private to a workspace/user pair; the planned `support.use` capability does not replace ownership.
- RAG augments the question with retrieved, authorized chunks. Chunks and the question use the same
  embedding model and a compatible profile; a separate chat model generates the answer.
- `KnowledgeBase` exposes a public retrieval contract; SQL in both search branches restricts
  accessible documents before `LIMIT`. The initial baseline is exact vector search and rank fusion with FTS.
- Authorization takes place outside the LLM. Chunks inherit the document policy; unknown permissions
  and failure of a required check result in fail closed (`503 AUTHORIZATION_UNAVAILABLE`),
  without fallback to unrestricted search or `no_context`. Access is rechecked before the LLM call and delivery.
- Index versions are published atomically; embedding profiles are not mixed. Deleted/revoked
  materials are excluded independently of reindexing.
- Revoking source permissions blocks further delivery of derived answers in history, polling, and SSE;
  the provenance of the entire context is checked. Hiding a citation alone is insufficient.
- The MVP has no cross-request permission cache. A future TTL depends on sensitivity: up to 24 hours
  for public-access decisions, 0 for secret documents. Invalidation and a current revocation check
  are mandatory; a stale `allow` or authorization failure does not grant access. Complex ACLs and an external
  authorization service are considered together with private sources, behind public ports.
- Generation creation is idempotent through HTTP JSON; a worker persists state in PostgreSQL.
  SSE observes a generation and recovers through a full snapshot without rerunning the model.
- Errors, budgets, citations, retention, and measurable evaluation gates are included in the MVP.

Consequences: pgvector is required in dev/test/deploy, along with separate queues/worker capacity, a dispatcher for persisted
generation tasks, and verification of streaming through the proxy/BFF. No new services, broker, or vector DB are required.
Capability and API extensions follow a contract-first approach during implementation, together with the generated client and tests.
The existing API v1 and Sanctum/BFF from ADR-021 are used; Qdrant remains an example technology,
not a new dependency. When connecting private sources, ACL mapping,
synchronization intervals, access revocation, and permission caching are checked separately.

Detailed behavior, acceptance criteria, and work sequence: [RAG Support Chat](rag-support-chat.md).

## ADR-020 — Selective Versioned Caching and Evidence-Driven Performance Optimization

Status: accepted, Phase 16 (2026-09-25).

Decision: implement selective versioned caching in Redis at the Read Model interface boundary, with monotonic dataset versioning in PostgreSQL (`analytics_dataset_versions`), covering B-tree indexes, and request-scoped promise coalescing on the frontend. Based on empirical measurements using the reference `large` profile (100k orders, 300k line items, 500k stock records), dedicated materialized views / projections were **rejected**.

Context and rationale:

- The analytics subsystem (AutoBI) is read-heavy. Repeated requests from dashboard widgets created unnecessary DBMS load.
- Introducing external databases (ClickHouse, Elasticsearch) or heavy trigger-based projections would complicate the architecture, introduce a risk of data divergence, and cause write amplification during batch imports.
- Adding the `idx_foi_ws_order_covering` covering index eliminated sort spills to disk and enabled `Index Only Scan`, reducing p95 from 1,947 ms to 370 ms (within the ≤ 1,000 ms budget). All 23 analytical scenarios met their budgets without materialized tables.

Rules and constraints:

- **PostgreSQL is the sole source of truth:** Redis contains only derived serialized DTOs of read results. Losing the cache does not compromise data integrity.
- **Placement behind the security boundary:** Caching takes place strictly within Read Model infrastructure decorators after authentication, `workspace_id` validation, and RBAC checks.
- **Selective Allowlist:** Only 7 low-cardinality summary and filter methods may be cached (`getSalesOverview`, `getInventorySummary`, `getAbcXyzSummary`, `getSupplierOverview`, and 3 `getFilterOptions` methods). Search queries and paginated record lists are not cached.
- **Monotonic Invalidation Versioning:** A successful fact import increments the relevant dataset version (`sales`, `inventory`, `suppliers`) in `analytics_dataset_versions`. Old keys from the previous version immediately become unreachable and expire by TTL (120–300 s), without blocking `KEYS *` or `Cache::flush()` operations.
- **Fail-Open Resilience:** Any Redis failure (timeout, network failure, memory exhaustion) gracefully falls back to a direct PostgreSQL SQL query without a user-facing 500 error. Only anonymized technical metadata is logged (dataset, workspace, operation, exception class).
- **Request-scoped Promise Coalescing on the Frontend:** Concurrent requests from multiple dashboard widgets within one render cycle are combined into a single request. A persistent cross-session client cache is forbidden.

## ADR-020 — VPS CI/CD through GHCR and Release Directories

Decision: after CI, publish backend and notification to GHCR with full commit SHA tags, and transfer only Compose, observability configuration, and scripts to the VPS. Deployment is serialized, creates verified backups of both databases, runs migrations using the new images, and restores the previous application images if checks fail. Secrets and backups are stored outside releases. The database schema is not rolled back automatically; migrations must be additive.

## ADR-021 — API Authentication through Laravel Sanctum and Next.js BFF

Decision: replace direct trust in the `X-User-Id` header with Laravel Sanctum Bearer tokens valid for 7 days. Browser calls pass through the Next.js BFF proxy (`/api/backend/*`), which injects the Bearer token from a protected HttpOnly session cookie and `X-Workspace-Id`. Analytics exposes one `/api/v1` contract with Bearer authentication. Changing a password revokes all of the user's server-side tokens, requiring a new sign-in.

## ADR-022 — Extended Settings, Email Invitations, and Personal Notifications

Decision: implement modular Settings sections (`/settings/*`) for profile, security, workspace settings, members, and notification severity. Workspace renaming is protected by the `workspace.settings.manage` capability. Workspace invitations are sent by email with cryptographic hashing of the link token (valid for 7 days) and processed by a separate backend worker (`queue:work --queue=default,mail`). Personal read statuses and severity preferences (`info`, `warning`, `critical`) are stored in Notification Service, with inter-service authentication of the trusted BFF through a server secret and context (`X-Server-Secret`, `X-User-Id`, `X-Workspace-Id`).
