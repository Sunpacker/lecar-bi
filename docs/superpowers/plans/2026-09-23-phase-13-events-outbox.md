# Phase 13 — Domain Events и Transactional Outbox

## Кратко

Реализовать гарантированную доставку `AlertTriggered` по цепочке:

`Alert aggregate → domain event → integration mapper → PostgreSQL outbox → queue publisher → Redis Stream`

Семантика доставки — **at least once**. `event_id` остаётся неизменным при повторной публикации, поэтому будущие consumers обязаны дедуплицировать события по нему. Domain ничего не знает о PostgreSQL, Laravel Queue или Redis.

Phase 13 начинается только после закрытия Phase 10–12. Frontend, HTTP API и notification service не входят в scope.

## Реализация

### 1. Зафиксировать event contract и архитектуру

- Добавить JSON Schema и пример `contracts/events/alert-triggered.v1.schema.json` для envelope:
  - `event_id`, `event_type = alert.triggered`, `event_version = 1`;
  - `occurred_at`, `producer = analytics`, `workspace_id`;
  - `aggregate.type = alert`, `aggregate.id`;
  - payload с правилом, severity, metric/comparator, текущим и пороговым значением, товаром, складом и analytical context.
- Версия `v1` после публикации неизменна; breaking или семантические изменения требуют новой версии.
- Подключить AJV к `contracts:validate`: компилировать все event schemas и проверять contract examples.
- Дополнить архитектурные документы:
  - PostgreSQL outbox является источником истины;
  - Redis Stream `autobi.integration-events` — начальный transport;
  - доставка at-least-once, глобальный порядок не гарантируется;
  - новый ADR фиксирует Redis Stream как заменяемый адаптер, а не зависимость Domain.

### 2. Ввести чистые domain event conventions

- В `Shared/Domain` определить:
  - `DomainEvent` с `eventId()` и `occurredAt()`;
  - `DomainEventId`;
  - механизм `recordDomainEvent()` / `releaseDomainEvents()` для aggregate roots.
- Доработать `Alert`:
  - новый alert cycle записывает ровно один `AlertTriggered`;
  - rehydration, повторная evaluation активного alert и обновление текущего значения событие не создают;
  - `event_id` и время передаются в Domain явно, без Laravel helpers и framework clock.
- В Alerting Application добавить mapper `AlertTriggered → IntegrationEvent`.
- Репозитории не публикуют события скрыто: application handler явно сохраняет aggregate и регистрирует выпущенные события внутри одной транзакции.

### 3. Реализовать transactional outbox

- Добавить таблицу `outbox_messages`:
  - immutable contract-поля: `id/event_id`, type, version, producer, workspace, aggregate type/id, JSONB envelope, occurred time;
  - delivery-поля: status, attempt count, next attempt, lock time, published time, Redis message ID и sanitized last error;
  - индекс для выборки готовых сообщений по status/next attempt;
  - без FK на workspace/alert, чтобы удаление business data не уничтожало ещё не доставленное событие.
- Статусы: `pending → processing → published`; после исчерпания попыток — `failed`.
- Ввести application ports для transaction manager, outbox registration/repository и integration transport; Laravel/PostgreSQL/Redis реализации разместить в Infrastructure.
- Обернуть сохранение нового Alert и регистрацию его outbox message в единую PostgreSQL-транзакцию. Ошибка mapping или записи outbox откатывает и alert, и событие.
- Выбирать сообщения через `FOR UPDATE SKIP LOCKED`; зависшие `processing` старше 10 минут возвращать в обработку.
- Не удалять published rows и не обрезать Redis Stream в Phase 13. Retention вводится после появления consumer и наблюдаемости.

### 4. Publisher, retry и runtime

- Добавить `RedisStreamIntegrationEventTransport`, публикующий canonical envelope одной JSON-записью через `XADD`.
- Использовать отдельное Redis connection/database для integration events; очередь Laravel остаётся на своей connection.
- Успешный `XADD` сохраняет Redis stream ID и переводит outbox message в `published`.
- Retry policy: 1 минута, 5 минут, 15 минут, 1 час, 6 часов, затем раз в 24 часа; после 10-й неуспешной попытки статус `failed`.
- Если процесс упал после `XADD`, но до фиксации `published`, событие публикуется повторно с тем же `event_id`. Это ожидаемое поведение at-least-once.
- Добавить:
  - уникальный queued job публикации batch до 100 сообщений;
  - запуск job каждую минуту;
  - `outbox:publish` для ручного запуска;
  - `outbox:retry {eventId}` для повторного запуска failed message.
- Добавить в local и VPS Compose процессы scheduler и queue worker, слушающий `outbox,default` с приоритетом outbox.
- Конфигурацию stream name, Redis DB, batch size, stale timeout и max attempts вынести в `config/outbox.php` и env examples. Не логировать payload, credentials или полные исключения.

## Публичные интерфейсы

- Новый межсервисный контракт: `alert.triggered`, version `1`.
- Redis Stream по умолчанию: `autobi.integration-events`; consumer group создаст Phase 14.
- Consumer обязан:
  - валидировать поддерживаемую версию;
  - дедуплицировать по `event_id`;
  - считать повторную доставку штатной;
  - не читать analytics PostgreSQL напрямую.
- HTTP/OpenAPI и generated frontend client не меняются.
- Phase 13 не публикует `AlertAcknowledged`, `AlertResolved` или import events.

## Проверка и приёмка

- Domain tests:
  - новый alert записывает один event;
  - rehydration и повторная evaluation не создают event;
  - Domain не импортирует Laravel, Redis или Infrastructure.
- Contract tests:
  - schema и example проходят AJV;
  - mapper с фиксированными входными данными создаёт envelope, совпадающий с contract example.
- PostgreSQL integration tests:
  - alert и outbox commit происходят атомарно;
  - ошибка outbox откатывает alert;
  - повторная регистрация одного `event_id` не создаёт вторую запись;
  - конкурентные claims не получают одно сообщение;
  - stale lock восстанавливается.
- Publisher tests:
  - успешный `XADD` сохраняет stream ID и `published_at`;
  - ошибка Redis планирует правильный backoff;
  - десятая ошибка переводит запись в `failed`;
  - ручной retry возвращает её в `pending`;
  - повторная публикация сохраняет исходный `event_id`.
- Integration checkpoint:
  - evaluation demo alert создаёт outbox row;
  - publisher помещает валидный envelope в Redis Stream;
  - повторная evaluation не создаёт новый alert/event;
  - `make check`, architecture tests, event-contract validation, container builds и `make integration` проходят.
- Только после прохождения checkpoint обновить Phase 13 и отметку в `ROADMAP.md`.

## Допущения

- Phase 12 предоставляет рабочий `Alert` aggregate и транзакционную точку создания нового alert cycle; при расхождении с черновым планом интерфейс нормализуется до начала Phase 13.
- Текущие незакоммиченные изменения пользователя сохраняются; номера новых миграций выбираются после существующих без конфликтов.
- Redis уже является обязательной инфраструктурной зависимостью, поэтому новый broker или runtime dependency не добавляется.
