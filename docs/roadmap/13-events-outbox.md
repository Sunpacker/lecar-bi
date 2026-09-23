# Phase 13 — Domain Events and Transactional Outbox

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Подготовить систему к надёжному межсервисному обмену.

## Функциональность

Domain event conventions, integration mapping, event versioning, outbox storage/publisher, retry policy, published-state tracking, idempotency conventions.

Message broker пока не обязателен.

## Exit Criteria

Business transaction и outbox registration атомарны, retry работает, events versioned, duplicate strategy определена, transport не проникает в Domain. Сверить с `docs/architecture/08-events-outbox-async.md`.

## Integration Checkpoint

Перед завершением этапа пройти [интеграционную проверку](ROADMAP.md#integration-checkpoints).

## Прогресс

### Выполнено (2026-09-23)

**Шаг 1: Event contract и архитектура**
- `contracts/events/alert-triggered.v1.schema.json` — JSON Schema v1 для integration event
- `contracts/events/alert-triggered.v1.example.json` — canonical example
- ADR-017 добавлен в `docs/architecture/12-architecture-decisions.md`
- `docs/architecture/08-events-outbox-async.md` обновлён с деталями реализации

**Шаг 2: Domain event conventions**
- `App\Shared\Domain\DomainEventId` — value object для идентификатора event
- `App\Shared\Domain\DomainEvent` — interface (eventId, occurredAt)
- `App\Shared\Domain\HasDomainEvents` — trait для aggregate roots
- `AlertTriggered` обновлён — реализует DomainEvent, содержит полные данные (metric, comparator, context)
- `Alert` aggregate — добавлен trait HasDomainEvents и factory method `Alert::trigger()` записывающий событие
- `retrigger()` не записывает domain event

**Шаг 3: Integration event mapper**
- `App\Shared\Application\IntegrationEvent` — canonical DTO envelope
- `App\Modules\Alerting\Application\Mappers\AlertTriggeredIntegrationMapper`

**Шаг 4: Application ports**
- `App\Shared\Application\Ports\TransactionManagerInterface`
- `App\Shared\Application\Ports\OutboxRepositoryInterface`
- `App\Shared\Application\Ports\IntegrationEventTransportInterface`

**Шаг 5: Migration**
- `database/migrations/2026_09_23_000050_create_outbox_messages_table.php`

**Шаг 6: Infrastructure**
- `App\Shared\Infrastructure\Persistence\Eloquent\Models\OutboxMessage`
- `App\Shared\Infrastructure\Persistence\Eloquent\Repositories\EloquentOutboxRepository` (FOR UPDATE SKIP LOCKED, ON CONFLICT DO NOTHING)
- `App\Shared\Infrastructure\Persistence\LaravelTransactionManager`
- `App\Shared\Infrastructure\Persistence\NoOpTransactionManager` (для тестов)
- `App\Shared\Infrastructure\Transport\RedisStreamIntegrationEventTransport` (XADD, отдельный Redis connection)
- `App\Shared\Infrastructure\Transport\InMemoryIntegrationEventTransport` (для тестов)
- `App\Shared\Infrastructure\Outbox\InMemoryOutboxRepository` (для тестов)

**Шаг 7: Handler обновлён**
- `EvaluateAlertRulesHandler` — save alert + register outbox в одной транзакции через `TransactionManagerInterface`

**Шаг 8: Publisher, commands, scheduler**
- `App\Shared\Infrastructure\Jobs\PublishOutboxMessagesJob` (ShouldBeUnique, batch 100)
- `OutboxPublishCommand` (artisan outbox:publish)
- `OutboxRetryCommand` (artisan outbox:retry {eventId})
- Scheduler: каждую минуту в `bootstrap/app.php`

**Шаг 9: Конфигурация**
- `config/outbox.php` — stream name, Redis DB, batch size, stale timeout, max attempts
- `.env.example` обновлён

**Шаг 10: DI bindings**
- `AppServiceProvider` обновлён — TransactionManager, OutboxRepository, IntegrationEventTransport (InMemory в testing)

**Шаг 11: Тесты**
- `AlertDomainTest` — расширен тестами Phase 13 (trigger записывает event, retrigger нет, rehydration нет, release очищает)
- `AlertTriggeredMapperTest` — новый тест contract mapping
- `OutboxPublisherTest` — новый: publish success, retry backoff, failed, retry reset, idempotency, stale lock, backoff schedule
- `AlertEvaluationEngineTest` — обновлён с новыми зависимостями и тестом `test_new_alert_registers_outbox_message`

### Результаты проверки

- Unit tests: 187/187 ✓
- Feature tests (AlertEvaluationEngine): 5/5 ✓
- Architecture tests: 3/3 ✓
- PHP syntax: OK ✓

### Осталось

- Запустить Docker Compose build + `make check` (lint, phpstan, полный тест suite с DB)
- Integration checkpoint: запустить outbox:publish, проверить Redis Stream

### Блокеры

Нет.
