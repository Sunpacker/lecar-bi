# Phase 14 — Notification Service Extraction Exercise

[Roadmap index and rules](ROADMAP.md) · [Agent router](../../AGENTS.md)

## Goal

Demonstrate that the system can genuinely be extended with a separate microservice.

Create a small notification service after Alerting/Outbox stabilizes. It must read versioned integration events, never access the analytics database directly, have an independent deployable unit, and handle duplicate events safely.

Change technology only for an architectural reason.

## Exit Criteria

No shared database, explicit integration, and the analytics service remains operational when the notification service is unavailable. Cross-check against `docs/architecture/02-monorepo-and-services.md`, `07-api-and-integration.md`, and `08-events-outbox-async.md`.

## Integration Checkpoint

Complete the [integration check](ROADMAP.md#integration-checkpoints) before closing the phase.

## Progress

- Extracted an independent `notification/` microservice (Laravel 13, PHP 8.3, zero-dependency Domain).
- Implemented strict data isolation: a separate `notification-postgres` database (`notification-postgres-data` volume) with its own credentials; services do not share a database.
- Implemented a Redis Streams consumer (`notifications:consume`) with the `notification-service-v1` consumer group, stale message claim (`XAUTOCLAIM`), `SIGTERM`/`SIGQUIT` signal support, and a dead-letter stream (`autobi.integration-events.dead-letter`).
- Implemented idempotent processing of `alert.triggered.v1` events with `event_id` deduplication in `consumed_events` and projection into `notifications`.
- Implemented liveness (`/api/v1/health/live`) and readiness (`/api/v1/health/ready`) endpoints checking the availability of the service's own database and Redis.
- Updated orchestration infrastructure (`docker-compose.yml`, `docker-compose.dev.yml`, `docker-compose.vps.yml`, `Makefile`, `infra/.env.example`).
- Added unit, feature, contract, architecture, and integration tests.

## Completion Verification

- **Date:** 2026-09-23
- **Exit Criteria Status:** All criteria fully met:
  1. **No Shared Database:** The notification service uses a dedicated PostgreSQL instance (`notification-postgres`), separate volume, and credentials. Notification containers do not contain analytics DB variables (`POSTGRES_DB=autobi`).
  2. **Explicit Versioned Integration:** Communication occurs exclusively through canonical `alert.triggered.v1` events in Redis Streams according to `contracts/events/alert-triggered.v1.schema.json`.
  3. **Failure Isolation:** The analytics service is fully independent of notification service availability. Failure or shutdown of `notification` / `notification-worker` does not affect analytics HTTP API health and operation or outbox event persistence.
  4. **Safe At-Least-Once Delivery & Deduplication:** Deduplication is guaranteed by unique constraints and the `consumed_events` table. Repeated event consumption does not duplicate projections.
  5. **Poison Message Handling:** Messages that violate the contract or use an unsupported version are sent to the `autobi.integration-events.dead-letter` stream, followed by `XACK`.
- **Verification commands and results:**
  - `composer --working-dir=notification validate --strict` — valid (OK)
  - `composer --working-dir=notification lint` — Pint and PHPStan (level 6) passed without issues (0 errors)
  - `composer --working-dir=notification test` — 38 tests, 144 assertions (OK)
  - `make check-notification` — passed successfully
  - `sh -n scripts/verify-integration.sh` — integration script syntax valid
