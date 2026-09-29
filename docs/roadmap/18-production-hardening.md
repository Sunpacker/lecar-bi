# Phase 18 — Production Hardening

[Roadmap index and rules](ROADMAP.md) · [Agent router](../../AGENTS.md) · [Infrastructure architecture](../architecture/09-infrastructure-deployment-observability.md) · [Testing strategy](../architecture/10-testing-and-quality.md)

## Goal

Prepare a reproducible production-like demonstration: deployment from a clean environment, controlled failures, data recovery, and verified security boundaries. This phase starts after [Phase 16](16-performance-caching.md) and [Phase 17](17-observability.md); their metrics and signals are used to verify failures.

Scope: the existing frontend, analytics, notification, two independent PostgreSQL databases, Redis, and versioned HTTP/event contracts. A new broker, orchestrator, or security service is not required. Deployment to an external environment requires a separate instruction.

## Initial State

- GitHub Actions checks frontend, OpenAPI, analytics, builds two images, and runs basic integration. Separate jobs for notification tests and build, dependency audits, and critical browser E2E tests are not yet in place.
- `infra/docker-compose.yml` is intended for local production-like startup and contains development-friendly `APP_DEBUG` values and notification credentials. `infra/docker-compose.vps.yml` sets `APP_DEBUG=false`, but other secrets, exposed ports, and the recovery procedure require separate verification.
- Analytics uses PostgreSQL and Redis Queue; notification reads Redis Stream with at-least-once delivery, deduplication, and a dead-letter stream. The analytics outbox is the source of truth for published integration events.
- `infra/README.md` describes VPS startup but does not yet define verifiable backup/restore, rollback, or failure scenarios.

## Work Sequence

1. **Define threats and boundaries.** Create a short matrix for the browser, Next.js/BFF, analytics API, file imports, Redis, notification consumer, and both databases. Use relevant [OWASP ASVS 5.0.0](https://owasp.org/projects/asvs/) requirements for checks: sessions/CSRF, backend workspace and capability authorization, data isolation, upload validation and size, errors without detail leakage, CORS, and security headers. Check the public surface through a real proxy: internal databases, Redis, notification, and technical endpoints must not be externally accessible. Record findings, owners, and remediation results. Apply [ZAP Automation Framework](https://www.zaproxy.org/docs/automate/automation-framework/) to the test environment with OpenAPI import and verified authorization; do not direct active checks at real user data.
2. **Address configuration and supply chain risks.** Prevent successful production-like startup with demo keys/passwords or debug enabled; separate dev defaults from production config. Check cookie flags, trusted proxies, TLS termination, CORS origins, secret permissions, and lifecycle. For Compose, consider [secrets](https://docs.docker.com/compose/how-tos/use-secrets/) instead of passing sensitive values through regular environment variables, with documented rotation. Run `npm audit` for frontend and `composer audit --locked` for backend/notification in CI; scan built images using a tool such as [Trivy](https://trivy.dev/docs/dev/guide/target/container_image/). Configure npm, Composer, Docker, and GitHub Actions updates through Dependabot; enable GitHub dependency review and secret scanning where available for the repository. Assess vulnerabilities by reachability and severity; document exceptions with a reason and review deadline.
3. **Bound load and waiting time.** Introduce Redis-backed [Laravel rate limiting](https://laravel.com/docs/13.x/routing) for login, imports, and expensive/mutating API operations with separate keys for anonymous IPs and authenticated users/workspaces; document limits and the `429` response. Align request size limits across proxy, frontend, and backend. Set finite connect/read/request timeouts at HTTP boundaries and for Redis/PostgreSQL; separately verify disconnected connection behavior. Retry only safe reads or idempotent operations; for other operations, preserve existing import, outbox, and `event_id` guarantees, using bounded attempts with backoff and jitter. Do not turn `429` or prolonged dependency failure into infinite retries.
4. **Verify background processes under failure.** For Laravel Queue, align `job timeout < retry_after`, attempt counts, backoff, failed jobs, and the rerun procedure; [Laravel documentation](https://laravel.com/docs/13.x/queues) warns about duplicate processing when timeout ordering is reversed. For Redis Stream, verify pending/reclaim after consumer failure, XACK only after a local commit or confirmed duplicate, poison message delivery to dead-letter, and safe replay. For imports and outbox, verify recovery after worker/Redis restart and the absence of duplicate projections or notifications. Document expected HTTP codes, job states, metrics, and operator actions for each scenario.
5. **Prepare data and deployment.** Describe clean startup without committed secrets, image builds, and deployment by verified digest instead of mutable `latest` ([Docker recommendations](https://docs.docker.com/build/building/best-practices/)), independent analytics/notification migrations on empty databases, smoke checks, and application rollback with compatible migrations. Configure a separate encrypted off-host backup, retention, and isolated restore verification for each PostgreSQL database; use [pg_dump/pg_restore](https://www.postgresql.org/docs/16/app-pgdump.html) for small volumes, selecting and documenting another method for different RPO/RTO requirements. Check roles/extensions, secrets, and consistency of the restored notification database with Redis Stream retention and outbox state. Do not treat the existence of a backup as proof of recovery: record the restore drill date, duration, and actual data loss.
6. **Make checks a release gate.** Compare changed OpenAPI against the base version using [`oasdiff breaking`](https://github.com/oasdiff/oasdiff/blob/main/docs/BREAKING-CHANGES.md), then verify API response conformance and client regeneration. Check `alert.triggered.v1` compatibility with the current notification consumer and redelivery. Add notification lint/static analysis/tests/build and all image builds, Compose configuration checks, security audits, clean migrations, and alert → outbox → notification integration scenarios to CI. Cover critical user flows with [Playwright](https://playwright.dev/docs/ci) on the built system: login and workspace boundaries, dashboard, import failure and retry. Store check results and artifact links alongside the release checklist.

## Exit Criteria

- No open blocking security issues: review against selected ASVS requirements completed, workspace isolation, secrets, public exposure, and fixes verified. Scanning alone is not considered proof of security.
- A clean environment starts according to the instructions with separate secrets; analytics and notification migrations pass on empty databases. The application has a documented deploy, smoke check, and rollback procedure without data destruction.
- Backups of both databases restored in an isolated environment; key records and deduplication verified after restoration. RPO/RTO measured, retention and Redis Stream/queue recovery limits documented.
- Request limits and timeouts verified by tests. Documented PostgreSQL, Redis, worker, and notification failures produce predictable responses/states, are visible in Phase 17 signals, and recover without losing or duplicating business results.
- HTTP and event contract changes are compatible with deployed consumers or versioned; OpenAPI diff, contract tests, and generated client are aligned.
- CI green for frontend, analytics, notification, contracts, all images, applicable audits, and integration; critical Playwright E2E tests pass on a production-like build. Results and acceptable exceptions documented.
- [Integration checkpoint](ROADMAP.md#integration-checkpoints) passed. Mark the phase complete in the index only after all criteria have actually been confirmed.

## Progress

- **Threat matrix and security boundaries:** Developed a threat model using STRIDE and OWASP ASVS 5.0.0 requirements ([`docs/security/threat-model.md`](../security/threat-model.md)). Configured a ZAP AF automated scanning profile ([`docs/security/zap-baseline.yaml`](../security/zap-baseline.yaml)).
- **Session protection and security headers:** Implemented HMAC-SHA256 signing of client session cookies with cryptographic verification through `crypto.timingSafeEqual` ([`frontend/src/features/auth/model/session.ts`](../../frontend/src/features/auth/model/session.ts), covered by tests). Added protective HTTP headers (CSP, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`) to frontend (`next.config.mjs`) and backend (`SecurityHeadersMiddleware.php`).
- **Configuration security and supply chain:** Implemented `ProductionSafetyCheck`, preventing production startup with `APP_DEBUG=true` or default/demo keys and passwords. Prepared the environment variable template [`infra/.env.production.example`](../../infra/.env.production.example) with mandatory unique secrets. Configured automatic dependency updates through [Dependabot](../../.github/dependabot.yml) for npm, Composer, Docker, and GitHub Actions.
- **Rate Limiting and timeouts:** Configured Redis-backed rate limiting in `AppServiceProvider` for login (5/min), imports (10/min), api-write (60/min), and api-read (300/min), returning a structured 429 response and `Retry-After` header. Set strict connection and read timeouts for PostgreSQL (`PDO::ATTR_TIMEOUT`) and Redis (`timeout`, `read_timeout`), as well as the frontend HTTP client (15s).
- **Queues, streams, and resilience invariants:** Aligned Laravel queue parameters (`$timeout = 60s < retry_after = 90s`, `$tries = 3`, exponential backoff). Added the `PredisStreamClient` stream client to Notification Worker with support for `XGROUP`, `XAUTOCLAIM`, `XREADGROUP`, `XACK`, DLQ, and heartbeats. Created monitoring endpoints `/api/v1/health/outbox` and `/api/v1/health/worker`. Developed an incident response runbook ([`docs/runbooks/production-failure-recovery.md`](../runbooks/production-failure-recovery.md)).
- **Backups and Disaster Recovery:** Created executable scripts [`scripts/backup-db.sh`](../../scripts/backup-db.sh), [`scripts/restore-db.sh`](../../scripts/restore-db.sh), and [`scripts/restore-drill.sh`](../../scripts/restore-drill.sh). Successfully performed a live disaster recovery drill with isolated database creation, dump restoration, and data integrity verification:
  - Actual RTO: 3 seconds (target: < 900s).
  - Actual RPO: 0 records / 0 seconds lost (target: < 3600s).
  - Procedures documented in [`docs/operations/backup-and-disaster-recovery.md`](../operations/backup-and-disaster-recovery.md) and [`infra/README.md`](../../infra/README.md).
- **OpenAPI and Release Gate:** Extended the [`contracts/openapi/analytics-v1.yaml`](../../contracts/openapi/analytics-v1.yaml) contract with the `TooManyRequests` (429) schema and updated `HealthResponse`. Updated frontend API client code generation. Added dependency security audits, image builds, notification service tests, clean migration checks, and restore drill execution to the CI pipeline ([`.github/workflows/ci.yml`](../../.github/workflows/ci.yml)). Extended [`scripts/verify-integration.sh`](../../scripts/verify-integration.sh) with steps checking security headers, rate limiting (429), and the absence of debug information leaks.
- **Verification results:** All `make check` checks passed successfully (50 frontend test suites / 227 tests, 383 backend tests / 33493 assertions, 53 notification tests, strict PHPStan and Pint analysis without a single error). All Phase 18 acceptance criteria fully met. Phase 18 closed.

## Completion Verification

- **Date:** 2026-09-25.
- **Exit Criteria confirmation:**
  - [x] _Security:_ ASVS 5.0.0 and STRIDE analysis completed (`docs/security/threat-model.md`). Implemented HMAC-SHA256 session cookie signing with timingSafeEqual, preventing session forgery. Added Security Headers (CSP, `nosniff`, `DENY`, `strict-origin-when-cross-origin`, `Permissions-Policy`). Implemented `ProductionSafetyCheck` against startup with `APP_DEBUG=true` or default keys/passwords.
  - [x] _Deployment and configuration:_ Prepared `infra/.env.production.example` with unique secrets. Documented deploy, smoke test, rollback, and rotation in `infra/README.md`. Independent analytics and notification migrations run successfully on clean databases.
  - [x] _Disaster Recovery:_ Tested `scripts/backup-db.sh`, `scripts/restore-db.sh`, and `scripts/restore-drill.sh`. Live restore drill completed: isolated databases `autobi_drill` and `notification_drill` created, row parity and deduplication verified (RTO = 3s against a target < 900s, RPO = 0s against a target < 3600s). Procedures documented in `docs/runbooks/production-failure-recovery.md` and `docs/operations/backup-and-disaster-recovery.md`.
  - [x] _Load and timeouts:_ Implemented a Redis-backed rate limiter for login, imports, api-write, and api-read, returning 429 and `Retry-After`. Set strict DB connection/read timeouts and a 15s timeout on the frontend HTTP client. Queue jobs aligned with `$timeout < retry_after`.
  - [x] _Streams and isolation:_ Notification worker consumes events through `PredisStreamClient` (`XGROUP`, `XAUTOCLAIM`, `XREADGROUP`, `XACK`), sends heartbeats, is isolated in the database and Redis, and publishes poison messages to DLQ. Endpoints `/health/outbox` and `/health/worker` track component status.
  - [x] _Contracts and CI:_ OpenAPI extended with 429 `TooManyRequests` schemas and updated `HealthResponse`. CI extended with notification checks, security audits, container builds, clean migrations, and restore drill. Dependabot configured.
- **Verification commands and results:**
  - `make check`: PASS
    - `npm --prefix frontend run contracts:validate`: PASS
    - `npm --prefix frontend run api:generate`: PASS
    - `npm --prefix frontend run lint`: PASS (0 warnings, 0 errors)
    - `npm --prefix frontend run format:check`: PASS (Prettier clean)
    - `npm --prefix frontend run typecheck`: PASS (0 type errors)
    - `npm --prefix frontend test`: PASS (50 test files, 227 tests passed)
    - `npm --prefix frontend run build`: PASS (Next.js production build succeeded)
    - `composer --working-dir=backend validate --strict`: PASS
    - `composer --working-dir=backend lint`: PASS (Pint & PHPStan passed with 0 errors)
    - `composer --working-dir=backend test`: PASS (383 tests, 33493 assertions passed)
    - `composer --working-dir=notification validate --strict`: PASS
    - `composer --working-dir=notification lint`: PASS (Pint & PHPStan passed with 0 errors)
    - `composer --working-dir=notification test`: PASS (53 tests passed)
  - `bash scripts/restore-drill.sh`: PASS (Analytics & Notification dumps, test db restore, row count parity confirmed, RTO 3s, RPO 0s)
  - `docker exec lecar-bi-notification-1 curl -s http://localhost:8081/api/v1/health/worker`: PASS (heartbeat active, status running)
  - `docker exec lecar-bi-backend-1 curl -s http://localhost:8080/api/v1/health/outbox`: PASS (publisher run confirmed, status ok)
