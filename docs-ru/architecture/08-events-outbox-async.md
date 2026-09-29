# 08. Events, Outbox and Async Processing

## Domain Events

Domain Events отражают значимые события внутри бизнес-модели.

Они описывают то, что произошло в domain, а не технический способ доставки.

Domain Event должен быть независим от:

- Laravel event system;
- очередей;
- брокера сообщений;
- HTTP.

### Соглашения (Phase 13+)

- Все domain events реализуют `App\Shared\Domain\DomainEvent` (интерфейс).
- `eventId()` возвращает `DomainEventId` — уникальный идентификатор, стабильный при повторной доставке.
- `occurredAt()` и `eventId` передаются в Domain явно — без `now()`, `Str::uuid()` или других Laravel helpers.
- Aggregate roots используют trait `HasDomainEvents` для записи (`recordDomainEvent`) и освобождения (`releaseDomainEvents`) событий.
- Application layer явно вызывает `releaseDomainEvents()` и регистрирует outbox message в той же транзакции.

## Integration Events

Integration Event используется для взаимодействия между микросервисами.

Он является внешним контрактом.

Domain Event и Integration Event не обязаны совпадать один к одному.

Между ними допускается преобразование (mapper в Application layer).

### Версионирование

- Версия `v1` после публикации неизменна.
- Breaking или семантические изменения требуют новой версии схемы.
- Контракт: `contracts/events/alert-triggered.v1.schema.json`.

## Назначение событий

События позволяют:

- уменьшить связанность модулей;
- запускать фоновые процессы;
- уведомлять внешние сервисы;
- строить новые projections;
- поддерживать будущую event-driven архитектуру.

## Transactional Outbox

Для надёжной публикации integration events реализован Outbox Pattern.

Смысл подхода:

- изменение бизнес-данных и регистрация будущего сообщения происходят в одной транзакции;
- отдельный worker публикует зарегистрированные события;
- после успешной отправки событие отмечается как опубликованное.

Это снижает риск расхождения между состоянием базы и опубликованными сообщениями.

### Таблица outbox_messages

Хранит immutable contract-поля (event_id, type, version, producer, workspace, aggregate, envelope, occurred_at)
и delivery-поля (status, attempt_count, next_attempt_at, locked_at, published_at, redis_message_id, last_error).

Статусы: `pending → processing → published`; при исчерпании попыток — `failed`.

Нет FK на business tables — удаление данных не уничтожает недоставленные события.

### Retry policy

Задержки: 1м, 5м, 15м, 1ч, 6ч, 24ч (повторяется). Максимум 10 попыток, затем `failed`.

`outbox:retry {eventId}` — ручной сброс в `pending` для повторной публикации.

### At-Least-Once Delivery

Если процесс упал после XADD, но до фиксации `published`, событие публикуется повторно с тем же `event_id`.
Это ожидаемое поведение. Consumers ОБЯЗАНЫ дедуплицировать по `event_id`.

## Message Broker и Consumers

На первой версии полноценный message broker не обязателен.

Начальный транспорт: Redis Stream `autobi.integration-events` (см. ADR-017).
Транспорт инкапсулирован за `IntegrationEventTransportInterface` и может быть заменён без изменения Domain.

Поля сообщения в Redis Stream (`XADD`):
- `event_id` — UUID события;
- `event_type` — строковый тип (например, `alert.triggered`);
- `event_version` — версия схемы (например, `1`);
- `payload` — сериализованный JSON канонического конверта события.

### Consumer Group (Notification Service)

- **Consumer Group:** `notification-service-v1`. Создаётся с `0 MKSTREAM` для чтения истории/backlog при первом запуске.
- **Цикл чтения:** В каждом цикле worker сначала возвращает зависшие сообщения через `XAUTOCLAIM` (по истечении idle-таймаута), затем читает новые сообщения через `XREADGROUP` с конечным block timeout.
- **Подтверждение (XACK):** `XACK` отправляется строго после успешной фиксации локальной транзакции (сохранение `Notification` и `ConsumedEvent`) либо при обнаружении уже обработанного дубликата (`duplicate` no-op). При временных сбоях (transient error БД/сети) `XACK` не выполняется, сообщение остаётся в PEL (pending entries list).
- **Dead-Letter Stream (`autobi.integration-events.dead-letter`):** Невалидные конверты (malformed JSON) или неподдерживаемые версии событий отправляются в dead-letter stream с указанием stream ID, event ID, причины ошибки и хеша payload, после чего исходное сообщение подтверждается (`XACK`).
- **Игнорируемые события:** Типы событий, для которых у группы нет обработчика, логируются и подтверждаются (`XACK`), чтобы не блокировать чтение потока.

## Laravel Queues

Laravel queues используются для:

- тяжёлых импортов;
- построения projections;
- пересчёта аналитики;
- обработки outbox в analytics (очередь `outbox`, уникальный job);
- фоновых уведомлений;
- других длительных операций.

Queue worker должен слушать `outbox,default` с приоритетом outbox.
*Примечание:* Notification service не использует Laravel Queue как промежуточный слой поверх Redis Stream: worker читает stream напрямую в CLI-команде `notifications:consume`.

## Идемпотентность и Deduplication

Обработчики фоновых задач и интеграционных событий проектируются с учётом повторной доставки (at-least-once).

Повторная обработка одного и того же события не приводит к дублированию состояния:
- В сервисе аналитики регистрация outbox message идемпотентна по `event_id` (ON CONFLICT DO NOTHING).
- В сервисе уведомлений таблица `consumed_events` имеет уникальный ключ `event_id`. Вставка `notifications` и `consumed_events` атомарна в рамках локальной PostgreSQL-транзакции. При повторной доставке уже обработанного `event_id` обработчик возвращает статус `duplicate`, и сообщение безопасно подтверждается (`XACK`).
