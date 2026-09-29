# 08. Events, Outbox and Async Processing

## Domain Events

Domain Events reflect significant events within the business model.

They describe what happened in the domain, not the technical delivery mechanism.

A Domain Event must be independent of:

- the Laravel event system;
- queues;
- the message broker;
- HTTP.

### Conventions (Phase 13+)

- All domain events implement `App\Shared\Domain\DomainEvent` (interface).
- `eventId()` returns `DomainEventId`, a unique identifier that remains stable across redelivery.
- `occurredAt()` and `eventId` are passed explicitly into Domain, without `now()`, `Str::uuid()`, or other Laravel helpers.
- Aggregate roots use the `HasDomainEvents` trait to record (`recordDomainEvent`) and release (`releaseDomainEvents`) events.
- The Application layer explicitly calls `releaseDomainEvents()` and records the outbox message in the same transaction.

## Integration Events

An Integration Event is used for communication between microservices.

It is an external contract.

Domain Events and Integration Events do not have to correspond one-to-one.

They may be transformed into one another (a mapper in the Application layer).

### Versioning

- Version `v1` is immutable after publication.
- Breaking or semantic changes require a new schema version.
- Contract: `contracts/events/alert-triggered.v1.schema.json`.

## Purpose of Events

Events enable:

- reduced module coupling;
- background process execution;
- external service notification;
- building new projections;
- support for a future event-driven architecture.

## Transactional Outbox

The Outbox Pattern is implemented for reliable integration event publication.

The approach works as follows:

- business data changes and registration of the future message occur in one transaction;
- a separate worker publishes the recorded events;
- after successful delivery, the event is marked as published.

This reduces the risk of divergence between database state and published messages.

### outbox_messages Table

Stores immutable contract fields (event_id, type, version, producer, workspace, aggregate, envelope, occurred_at)
and delivery fields (status, attempt_count, next_attempt_at, locked_at, published_at, redis_message_id, last_error).

Statuses: `pending → processing → published`; after exhausting attempts, `failed`.

There are no FKs to business tables, so deleting data does not destroy undelivered events.

### Retry Policy

Delays: 1 min, 5 min, 15 min, 1 h, 6 h, 24 h (repeated). A maximum of 10 attempts, then `failed`.

`outbox:retry {eventId}` manually resets the status to `pending` for republication.

### At-Least-Once Delivery

If the process crashes after XADD but before committing `published`, the event is published again with the same `event_id`.
This is expected behavior. Consumers MUST deduplicate by `event_id`.

## Message Broker and Consumers

A full message broker is not required in the first version.

The initial transport is Redis Stream `autobi.integration-events` (see ADR-017).
The transport is encapsulated behind `IntegrationEventTransportInterface` and can be replaced without changing Domain.

Redis Stream message fields (`XADD`):

- `event_id` — event UUID;
- `event_type` — string type (for example, `alert.triggered`);
- `event_version` — schema version (for example, `1`);
- `payload` — serialized JSON of the canonical event envelope.

### Consumer Group (Notification Service)

- **Consumer Group:** `notification-service-v1`. Created with `0 MKSTREAM` to read history/backlog on the first run.
- **Read loop:** In each iteration, the worker first reclaims stalled messages using `XAUTOCLAIM` (after the idle timeout), then reads new messages using `XREADGROUP` with a finite block timeout.
- **Acknowledgment (XACK):** `XACK` is sent strictly after the local transaction successfully commits (persisting `Notification` and `ConsumedEvent`) or an already processed duplicate is detected (`duplicate` no-op). On transient failures (DB/network errors), no `XACK` is sent and the message remains in the PEL (pending entries list).
- **Dead-Letter Stream (`autobi.integration-events.dead-letter`):** Invalid envelopes (malformed JSON) or unsupported event versions are sent to the dead-letter stream with the stream ID, event ID, error reason, and payload hash; the original message is then acknowledged (`XACK`).
- **Ignored events:** Event types for which the group has no handler are logged and acknowledged (`XACK`) to avoid blocking stream consumption.

## Laravel Queues

Laravel queues are used for:

- heavy imports;
- building projections;
- recalculating analytics;
- processing outbox in analytics (`outbox` queue, unique job);
- background notifications;
- other long-running operations.

The queue worker must listen to `outbox,default`, prioritizing outbox.
_Note:_ Notification service does not use Laravel Queue as an intermediate layer over Redis Stream: the worker reads the stream directly in the `notifications:consume` CLI command.

## Idempotency and Deduplication

Background job and integration event handlers are designed for redelivery (at-least-once).

Reprocessing the same event does not duplicate state:

- In the analytics service, outbox message registration is idempotent by `event_id` (ON CONFLICT DO NOTHING).
- In the notification service, `consumed_events` has a unique `event_id` key. Inserting `notifications` and `consumed_events` is atomic within a local PostgreSQL transaction. When an already processed `event_id` is redelivered, the handler returns `duplicate`, and the message is safely acknowledged (`XACK`).
