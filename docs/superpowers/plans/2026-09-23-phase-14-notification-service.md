# Phase 14 — Notification Service Extraction Exercise

## Кратко

Выделить третий независимый deployable-сервис `notification/`, который читает `alert.triggered.v1` из Redis Stream, преобразует событие в workspace-level notification projection и сохраняет её в собственной PostgreSQL.

Цепочка:

`analytics outbox → Redis Stream → notification consumer group → inbox/deduplication → notification PostgreSQL`

Семантика доставки остаётся **at least once**. Exactly-once transport не имитируется: сервис гарантирует идемпотентный side effect через уникальный `event_id`, а Redis message подтверждает только после фиксации собственной транзакции.

Phase 14 начинается только после закрытия Phase 12 и Phase 13. Email, SMS, push, frontend UI, публичный notifications API, новый message broker и общая библиотека бизнес-логики не входят в scope.

## Цель, scope и критерии готовности

### Результат

- `notification/` имеет собственные зависимости, конфигурацию, Dockerfile, тесты, entry points и image.
- Сервис поддерживает только опубликованный контракт `alert.triggered.v1` и не импортирует код `backend/`.
- Сервис подключён только к Redis transport и своей PostgreSQL; credentials analytics PostgreSQL ему не передаются.
- Повторная доставка одного `event_id` создаёт ровно одну notification projection.
- Остановка notification service не влияет на alerting, outbox publisher и HTTP API analytics; после запуска consumer обрабатывает накопленный backlog.
- Полный integration checkpoint подтверждает event flow, duplicate safety, recovery и отсутствие shared database.

### Разрешённый write scope

- `notification/**`
- `contracts/events/**` — только совместимые уточнения transport metadata или fixtures, без изменения опубликованной семантики `alert.triggered.v1`
- `infra/**`
- `Makefile`
- `README.md`
- `scripts/verify-integration.sh`
- `docs/architecture/{01-system-architecture,02-monorepo-and-services,07-api-and-integration,08-events-outbox-async,09-infrastructure-deployment-observability,10-testing-and-quality,12-architecture-decisions}.md`
- `docs/roadmap/{14-notification-service,ROADMAP}.md` — только на финальном checkpoint

### Запрещённый scope

- `frontend/**`
- доменная и прикладная логика `backend/**`
- новый event type или breaking change `alert.triggered.v1`
- SMTP/email/SMS/push providers и пользовательские preferences
- HTTP-вызовы из analytics в notification service
- доступ notification service к analytics PostgreSQL
- Kafka, RabbitMQ или другой новый broker

## Обязательный preflight-gate

До реализации проверить:

- Phase 12 и Phase 13 отмечены `[x]` в `docs/roadmap/ROADMAP.md` и содержат пройденные integration checkpoints.
- Существуют и проходят validation:
  - `contracts/events/alert-triggered.v1.schema.json`;
  - canonical example `alert-triggered.v1`;
  - transport contract Redis Stream из Phase 13.
- Analytics публикует canonical envelope с неизменяемыми `event_id`, `event_type`, `event_version`, `occurred_at`, `producer`, `workspace_id`, `aggregate` и `payload`.
- Stream name и имя поля с JSON-envelope зафиксированы конфигурацией Phase 13. План использует defaults `autobi.integration-events` и `event`; если Phase 13 закрепит другие значения, Phase 14 принимает опубликованный transport contract без его несовместимого изменения.
- Повторная публикация сохраняет исходный `event_id`, а analytics считает доставку успешной после записи в stream, не ожидая consumer.

Если любой пункт не выполнен, Phase 14 не стартует: сначала закрывается соответствующий exit criterion Phase 12/13.

## Архитектурные решения

### Граница сервиса

- Использовать Laravel 13 и PHP 8.3, как в analytics: смена технологии не нужна для небольшого stream consumer и добавила бы второй runtime без архитектурной причины.
- Создать самостоятельный Laravel project в `notification/`; не копировать runtime-классы через общий Composer package.
- Общими между сервисами остаются только versioned event schema и canonical fixture в `contracts/events/**`.
- `alert.triggered.v1` проходит через Anti-Corruption Layer: transport envelope декодируется в локальный immutable DTO, затем application handler создаёт локальную `Notification`.
- Notification service ничего не знает об `Alert` aggregate, Eloquent models и repositories analytics.

### Данные и идемпотентность

Notification PostgreSQL содержит две таблицы:

1. `consumed_events`:
   - `event_id` — UUID primary/unique deduplication key;
   - `stream_message_id` — unique transport trace;
   - `event_type`, `event_version`, `producer`, `workspace_id`, `occurred_at`, `processed_at`;
   - raw envelope не хранится.
2. `notifications`:
   - собственный UUID `id`;
   - unique `source_event_id`;
   - `workspace_id`, `alert_id`, `rule_id`, `severity`;
   - `title`, `body`, `analytical_context` JSON, `occurred_at`, timestamps;
   - без foreign keys на analytics tables.

Вставка notification и регистрация consumed event происходят в одной локальной PostgreSQL-транзакции. При redelivery уже зарегистрированный `event_id` считается успешным no-op, после чего текущий Redis message можно подтвердить.

### Redis Streams

- Stream: default `autobi.integration-events`.
- Consumer group: default `notification-service-v1`.
- Group создаётся идемпотентно через `XGROUP CREATE ... 0 MKSTREAM`, чтобы первый запуск прочитал backlog.
- Новые messages читаются batch-ами через `XREADGROUP` с конечным block timeout; process не зависает навсегда и корректно реагирует на `SIGTERM`/`SIGQUIT`.
- Зависшие pending messages возвращаются через `XAUTOCLAIM` после configurable idle timeout.
- `XACK` выполняется только после успешного commit локальной транзакции или подтверждённого duplicate no-op.
- Malformed envelope и неподдерживаемая версия `alert.triggered` публикуются в `autobi.integration-events.dead-letter` с source stream ID, извлечённым event ID при наличии, reason code и payload hash; исходный message подтверждается только после успешного `XADD` в dead-letter stream.
- Event types, для которых у notification consumer group нет handler, логируются как ignored и подтверждаются: другие consumer groups читают stream независимо.

### Health и эксплуатация

- `GET /api/v1/health/live` проверяет, что HTTP process работает.
- `GET /api/v1/health/ready` проверяет только собственную PostgreSQL и Redis; analytics не является runtime dependency.
- Structured logs содержат `event_id`, Redis message ID, type/version, workspace ID, outcome и duration, но не payload, credentials или полные exception details.
- Отдельные Compose processes используют один image:
  - `notification` — health/readiness HTTP process;
  - `notification-worker` — `php artisan notifications:consume`;
  - `notification-postgres` — отдельный PostgreSQL container и volume.
- Analytics и frontend не получают `depends_on: notification`; падение notification не должно влиять на их readiness.

## План реализации

### Task 1. Зафиксировать service boundary и transport contract

**Files:**

- Modify: `docs/architecture/01-system-architecture.md`
- Modify: `docs/architecture/02-monorepo-and-services.md`
- Modify: `docs/architecture/07-api-and-integration.md`
- Modify: `docs/architecture/08-events-outbox-async.md`
- Modify: `docs/architecture/09-infrastructure-deployment-observability.md`
- Modify: `docs/architecture/12-architecture-decisions.md`
- Modify when needed: `contracts/events/**`

- [ ] Проверить артефакты Phase 13 и записать фактические stream name, JSON field и envelope fixture в документацию.
- [ ] Зафиксировать решение о выделении notification service, владении отдельной БД, Redis Stream consumer group и at-least-once/idempotent semantics следующим свободным ADR.
- [ ] Указать, что Redis Stream — заменяемый transport adapter, а schema/fixture — единственный shared contract.
- [ ] Зафиксировать compatibility policy: `v1` immutable; новая обязательная семантика требует новой event version и отдельного handler.

Acceptance criteria:

- Документация однозначно показывает ownership данных и зависимости между analytics, Redis transport и notification.
- Ни один документ не описывает прямой DB access или синхронный вызов notification из analytics.

### Task 2. Создать независимый Laravel service skeleton

**Files:**

- Create: `notification/composer.json`, `notification/composer.lock`
- Create: `notification/artisan`, `notification/bootstrap/**`, `notification/config/**`, `notification/routes/**`
- Create: `notification/.env.example`, `notification/phpunit.xml`, `notification/phpstan.neon`, `notification/pint.json`
- Create: `notification/Dockerfile`, `notification/.dockerignore`, `notification/README.md`
- Create: `notification/app/Providers/AppServiceProvider.php`
- Create: `notification/app/Http/Controllers/HealthController.php`
- Test: `notification/tests/Feature/HealthCheckTest.php`

- [ ] Использовать тот же поддерживаемый PHP/Laravel baseline, что и `backend/`, и отдельный Composer autoload namespace `NotificationService\\`.
- [ ] Подключить только необходимые зависимости Laravel, Predis, PHPUnit, Pint и Larastan; не создавать shared application package.
- [ ] Реализовать live/readiness endpoints и отдельные проверки notification PostgreSQL/Redis.
- [ ] Настроить service-local lint, static analysis и PHPUnit scripts.
- [ ] Добавить architecture test, запрещающий imports/namespaces analytics и Laravel dependencies внутри `Notification/Domain`.

Acceptance criteria:

- `composer --working-dir=notification validate --strict`, lint и базовые tests проходят независимо от `backend/` и `frontend/`.
- Notification image собирается из context `notification/` и не копирует `backend/`.

### Task 3. Реализовать локальную domain/application модель

**Files:**

- Create: `notification/app/Notification/Domain/Notification.php`
- Create: `notification/app/Notification/Domain/NotificationId.php`
- Create: `notification/app/Notification/Domain/NotificationSeverity.php`
- Create: `notification/app/Notification/Application/AlertTriggeredV1.php`
- Create: `notification/app/Notification/Application/AlertTriggeredV1Decoder.php`
- Create: `notification/app/Notification/Application/ConsumeAlertTriggered.php`
- Create: `notification/app/Notification/Application/Contracts/NotificationRepository.php`
- Create: `notification/app/Notification/Application/Contracts/ConsumedEventRepository.php`
- Create: `notification/app/Notification/Application/Contracts/TransactionManager.php`
- Test: `notification/tests/Unit/Notification/AlertTriggeredV1DecoderTest.php`
- Test: `notification/tests/Unit/Notification/ConsumeAlertTriggeredTest.php`
- Test: `notification/tests/Contract/AlertTriggeredV1ContractTest.php`

- [ ] Decoder строго проверяет envelope type/version и поля, используемые projection; framework helpers и transport types не попадают в Domain.
- [ ] Contract test загружает canonical fixture Phase 13 и доказывает, что consumer принимает опубликованный contract.
- [ ] Handler формирует deterministic title/body/context только из event payload, без HTTP/DB lookup в analytics.
- [ ] Handler выполняет duplicate check, сохраняет notification и consumed event в одной транзакции.
- [ ] Покрыть missing/invalid fields, unsupported version, duplicate delivery и rollback при ошибке repository.

Acceptance criteria:

- Один canonical `alert.triggered.v1` создаёт одну локальную notification.
- Два вызова с одинаковым `event_id` оставляют одну notification и один consumed event.
- Application/Domain tests не требуют Redis и PostgreSQL.

### Task 4. Добавить собственную PostgreSQL persistence

**Files:**

- Create: `notification/database/migrations/*_create_consumed_events_table.php`
- Create: `notification/database/migrations/*_create_notifications_table.php`
- Create: `notification/app/Notification/Infrastructure/Persistence/ConsumedEventModel.php`
- Create: `notification/app/Notification/Infrastructure/Persistence/NotificationModel.php`
- Create: `notification/app/Notification/Infrastructure/Persistence/EloquentConsumedEventRepository.php`
- Create: `notification/app/Notification/Infrastructure/Persistence/EloquentNotificationRepository.php`
- Create: `notification/app/Shared/Infrastructure/LaravelTransactionManager.php`
- Modify: `notification/app/Providers/AppServiceProvider.php`
- Test: `notification/tests/Feature/NotificationPersistenceTest.php`

- [ ] Добавить unique constraints на `consumed_events.event_id`, `consumed_events.stream_message_id` и `notifications.source_event_id`.
- [ ] Не добавлять foreign keys, connection strings или Eloquent relations к analytics data.
- [ ] Корректно обработать конкурентную регистрацию одинакового event ID: unique conflict превращается в duplicate success, остальные DB errors не скрываются.
- [ ] Проверить commit, rollback и reprocessing после commit-before-ack crash window.

Acceptance criteria:

- Миграции применяются к чистой notification PostgreSQL.
- Повторное и конкурентное потребление не создаёт duplicate side effects.
- Service работает с отсутствующей analytics database connection в environment.

### Task 5. Реализовать Redis Stream adapter и long-running consumer

**Files:**

- Create: `notification/config/integration-events.php`
- Create: `notification/app/Integration/Application/IntegrationEventRouter.php`
- Create: `notification/app/Integration/Application/ConsumeIntegrationEvent.php`
- Create: `notification/app/Integration/Infrastructure/Redis/RedisStreamClient.php`
- Create: `notification/app/Integration/Infrastructure/Redis/RedisStreamConsumer.php`
- Create: `notification/app/Integration/Infrastructure/Redis/RedisDeadLetterPublisher.php`
- Create: `notification/app/Console/Commands/ConsumeNotificationsCommand.php`
- Modify: `notification/.env.example`
- Test: `notification/tests/Unit/Integration/IntegrationEventRouterTest.php`
- Test: `notification/tests/Unit/Integration/RedisStreamConsumerTest.php`
- Test: `notification/tests/Feature/ConsumeNotificationsCommandTest.php`

- [ ] Вынести stream/group/consumer name, batch size, block timeout, stale idle timeout и dead-letter stream в config/env.
- [ ] Создавать consumer group идемпотентно; ошибку `BUSYGROUP` трактовать как already exists, остальные Redis errors не подавлять.
- [ ] В каждом цикле сначала возвращать stale pending messages, затем читать новые messages.
- [ ] Подтверждать message только после результатов `processed`, `duplicate`, `ignored` или успешного dead-letter publish.
- [ ] На transient DB/Redis error не выполнять `XACK`; process логирует sanitized failure и продолжает после bounded backoff.
- [ ] Обработать `SIGTERM`/`SIGQUIT`, завершив текущий message/batch и выйдя с корректным code.

Acceptance criteria:

- Unit tests доказывают ack/no-ack matrix для success, duplicate, malformed, unsupported version и transient failure.
- Повторный запуск command продолжает pending/backlog, а не теряет сообщения.
- Consumer не использует Laravel Queue как второй уровень доставки поверх Redis Stream.

### Task 6. Интегрировать сервис в local и VPS orchestration

**Files:**

- Modify: `infra/docker-compose.yml`
- Modify: `infra/docker-compose.dev.yml`
- Modify: `infra/docker-compose.vps.yml`
- Modify: `infra/.env.example`
- Modify: `infra/README.md`
- Modify: `Makefile`
- Modify: `README.md`

- [ ] Добавить отдельные `notification`, `notification-worker`, `notification-postgres` и notification volume.
- [ ] Использовать отдельные DB name/user/password variables; notification containers не получают `POSTGRES_DB`, `POSTGRES_USER` и `POSTGRES_PASSWORD` analytics.
- [ ] Redis остаётся общим transport infrastructure, но получает отдельную named connection/config namespace для integration events.
- [ ] Не добавлять dependency от backend/frontend к notification или notification-postgres.
- [ ] Добавить `install-notification`, `check-notification`, `notification-migrate`, отдельные image build targets и включить notification checks в aggregate `make check`.
- [ ] Production orchestration применяет notification migrations отдельно и позволяет пересобрать/restart notification image без пересборки frontend/backend.

Acceptance criteria:

- Все три application images собираются независимо.
- По `docker inspect` notification containers не имеют analytics DB credentials/host.
- Остановка `notification` и `notification-worker` не делает backend/frontend unhealthy.

### Task 7. Провести end-to-end и failure-mode verification

**Files:**

- Modify: `scripts/verify-integration.sh`
- Create: `notification/tests/Integration/RedisStreamConsumptionTest.php` when a service-local integration harness is preferable

- [ ] Поднять clean stack и применить миграции обеих баз.
- [ ] Создать/evaluate demo alert и запустить outbox publisher.
- [ ] Дождаться одной строки в notification `notifications` и соответствующего `consumed_events`; сверить `event_id`, workspace, aggregate/payload projection.
- [ ] Повторно опубликовать тот же envelope с тем же `event_id`; количество notification rows остаётся равным одному.
- [ ] Остановить `notification` и `notification-worker`, создать ещё один alert/event и подтвердить:
  - analytics health/API работают;
  - business transaction и outbox publication завершаются;
  - event присутствует в Redis Stream.
- [ ] Запустить notification service и подтвердить обработку backlog.
- [ ] Прервать worker после локального commit до ack с помощью test seam/fault injection, затем подтвердить duplicate-safe recovery.
- [ ] Отправить malformed message и unsupported `alert.triggered` version; проверить dead-letter metadata и отсутствие notification row.
- [ ] Проверить live/readiness endpoints и graceful worker shutdown.

Acceptance criteria:

- Полная цепочка проходит без прямого чтения analytics DB.
- Недоступность consumer не влияет на analytics и не теряет опубликованный event.
- Redelivery, stale pending recovery и poison message policy подтверждены автоматизированно.

### Task 8. Integration checkpoint и закрытие Phase 14

**Files:**

- Modify: `docs/roadmap/14-notification-service.md`
- Modify: `docs/roadmap/ROADMAP.md`

- [ ] Выполнить:

```bash
composer --working-dir=notification validate --strict
composer --working-dir=notification lint
composer --working-dir=notification test
make check
docker compose --env-file infra/.env -f infra/docker-compose.yml build backend notification
make integration
```

- [ ] Проверить diff на shared business code, analytics DB access, payload logging, secrets, accidental synchronous coupling и скрытые breaking changes event contract.
- [ ] Записать в Phase 14 фактические команды и результаты, оставшиеся ограничения и эксплуатационный порядок запуска/migration.
- [ ] Только после подтверждения каждого exit criterion отметить Phase 14 `[x]` в `docs/roadmap/ROADMAP.md`.

## Матрица проверок

| Требование | Проверка |
|---|---|
| Versioned explicit integration | schema + canonical fixture + consumer contract test |
| No shared database | отдельный PostgreSQL container/volume/credentials и inspect env |
| Safe duplicates | unique `event_id`, duplicate/concurrency/redelivery tests |
| Analytics survives notification outage | stop consumer → alert/outbox publish/API success → backlog recovery |
| Independent deployable unit | отдельные composer lock, Dockerfile, image, config, tests и health endpoints |
| Domain/transport isolation | architecture tests и local DTO/ACL |
| Poison message safety | dead-letter test, ack only after DLQ publish |
| Graceful operations | readiness, stale claim, signal shutdown и structured logs |

## Риски и меры

- **Phase 13 contract ещё не стабилен.** Не угадывать envelope/field name: Phase 14 заблокирована preflight-gate до публикации и проверки canonical fixture.
- **Shared PostgreSQL под видом отдельных schemas.** Использовать отдельный container/volume/credentials; это делает нарушение границы технически заметным.
- **Потеря события между commit и ack.** Принимать redelivery как норму и дедуплицировать по `event_id`.
- **Вечный pending poison message.** Permanent contract errors переводить в dead-letter stream; transient infrastructure errors оставлять pending.
- **Ненаблюдаемый worker.** Добавить structured outcomes и readiness Redis/DB; метрики/tracing не расширять до Phase 17.
- **Scope creep до полноценной notification platform.** В Phase 14 хранить только workspace-level projection; delivery providers, preferences, templates, recipient resolution и UI планировать отдельными product phases.

## Допущения

- Phase 13 реализует Redis Streams transport и публикует `alert.triggered.v1` с данными, достаточными для title/body/context без запроса в analytics.
- Один Redis deployment допустим как transport infrastructure; запрет shared database относится к persistent service-owned state.
- Отдельная notification PostgreSQL может использовать тот же PostgreSQL image/version, но не database/container/volume/credentials analytics.
- Public notification API не нужен для доказательства extraction; health endpoints являются только техническим интерфейсом.
- Существующие незакоммиченные изменения Phase 12 принадлежат пользователю и не изменяются при создании или исполнении этого плана.
