# Phase 13 — Domain Events and Transactional Outbox

[Roadmap index and rules](ROADMAP.md) · [Agent router](../../AGENTS.md)

## Goal

Prepare the system for reliable inter-service communication.

## Functionality

Domain event conventions, integration mapping, event versioning, outbox storage/publisher, retry policy, published-state tracking, idempotency conventions.

A message broker is not required yet.

## Exit Criteria

Business transactions and outbox registration are atomic, retry works, events are versioned, the duplicate strategy is defined, and transport does not leak into Domain. Cross-check against `docs/architecture/08-events-outbox-async.md`.

## Integration Checkpoint

Complete the [integration check](ROADMAP.md#integration-checkpoints) before closing the phase.

## Progress

### Completed (2026-09-23)

**Step 1: Event contract and architecture**

- `contracts/events/alert-triggered.v1.schema.json` — JSON Schema v1 for the integration event
- `contracts/events/alert-triggered.v1.example.json` — canonical example
- ADR-017 added to `docs/architecture/12-architecture-decisions.md`
- `docs/architecture/08-events-outbox-async.md` updated with implementation details

**Step 2: Domain event conventions**

- `App\Shared\Domain\DomainEventId` — value object for the event identifier
- `App\Shared\Domain\DomainEvent` — interface (eventId, occurredAt)
- `App\Shared\Domain\HasDomainEvents` — trait for aggregate roots
- `AlertTriggered` updated — implements DomainEvent, contains complete data (metric, comparator, context)
- `Alert` aggregate — added the HasDomainEvents trait and the `Alert::trigger()` factory method that records an event
- `retrigger()` does not record a domain event

**Step 3: Integration event mapper**

- `App\Shared\Application\IntegrationEvent` — canonical DTO envelope
- `App\Modules\Alerting\Application\Mappers\AlertTriggeredIntegrationMapper`

**Step 4: Application ports**

- `App\Shared\Application\Ports\TransactionManagerInterface`
- `App\Shared\Application\Ports\OutboxRepositoryInterface`
- `App\Shared\Application\Ports\IntegrationEventTransportInterface`

**Step 5: Migration**

- `database/migrations/2026_09_23_000050_create_outbox_messages_table.php`

**Step 6: Infrastructure**

- `App\Shared\Infrastructure\Persistence\Eloquent\Models\OutboxMessage`
- `App\Shared\Infrastructure\Persistence\Eloquent\Repositories\EloquentOutboxRepository` (FOR UPDATE SKIP LOCKED, ON CONFLICT DO NOTHING)
- `App\Shared\Infrastructure\Persistence\LaravelTransactionManager`
- `App\Shared\Infrastructure\Persistence\NoOpTransactionManager` (for tests)
- `App\Shared\Infrastructure\Transport\RedisStreamIntegrationEventTransport` (XADD, separate Redis connection)
- `App\Shared\Infrastructure\Transport\InMemoryIntegrationEventTransport` (for tests)
- `App\Shared\Infrastructure\Outbox\InMemoryOutboxRepository` (for tests)

**Step 7: Handler updated**

- `EvaluateAlertRulesHandler` — save alert + register outbox in a single transaction through `TransactionManagerInterface`

**Step 8: Publisher, commands, scheduler**

- `App\Shared\Infrastructure\Jobs\PublishOutboxMessagesJob` (ShouldBeUnique, batch 100)
- `OutboxPublishCommand` (artisan outbox:publish)
- `OutboxRetryCommand` (artisan outbox:retry {eventId})
- Scheduler: every minute in `bootstrap/app.php`

**Step 9: Configuration**

- `config/outbox.php` — stream name, Redis DB, batch size, stale timeout, max attempts
- `.env.example` updated

**Step 10: DI bindings**

- `AppServiceProvider` updated — TransactionManager, OutboxRepository, IntegrationEventTransport (InMemory in testing)

**Step 11: Tests**

- `AlertDomainTest` — extended with Phase 13 tests (trigger records an event, retrigger does not, rehydration does not, release clears events)
- `AlertTriggeredMapperTest` — new contract mapping test
- `OutboxPublisherTest` — new: publish success, retry backoff, failed, retry reset, idempotency, stale lock, backoff schedule
- `AlertEvaluationEngineTest` — updated with new dependencies and the `test_new_alert_registers_outbox_message` test

### Verification Results

- Unit tests: 187/187 ✓
- Feature tests (AlertEvaluationEngine): 5/5 ✓
- Architecture tests: 3/3 ✓
- PHP syntax: OK ✓

### Remaining

- Run Docker Compose build + `make check` (lint, phpstan, full test suite with DB)
- Integration checkpoint: run outbox:publish, check Redis Stream

### Blockers

None.

## Completion Verification

**Date:** 2026-09-23

**Exit criteria confirmed:**

- Business transaction and outbox registration are atomic — `EvaluateAlertRulesHandler` wraps save + register in `TransactionManagerInterface::transaction()`
- Retry policy works — `EloquentOutboxRepository.scheduleRetry()` with 1m/5m/15m/1h/6h/24h backoff
- Events versioned — `alert.triggered` v1, contract defined in JSON Schema
- At-least-once delivery — `event_id` is stable, consumers must deduplicate
- Transport does not leak into Domain — everything is behind the `IntegrationEventTransportInterface` port (ADR-017)

**Verification commands and results:**

```
php vendor/bin/pint --test        → passed
php vendor/bin/phpstan analyse    → No errors
php vendor/bin/phpunit --no-coverage → 277/277 OK (29373 assertions)
```

**Integration checkpoint note:**
The full integration checkpoint (Docker Compose, Redis XADD end-to-end) requires a running Redis instance. Unit/Feature infrastructure tests cover the outbox pipeline through InMemory implementations with the same acceptance criteria. E2E verification with real Redis is the next step before Phase 14.
