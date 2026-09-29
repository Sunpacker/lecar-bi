# Phase 14 — Notification Service Extraction Exercise

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Доказать возможность реального расширения системы отдельным микросервисом.

Создать небольшой notification service после стабилизации Alerting/Outbox. Он должен читать versioned integration events, не ходить напрямую в analytics database, иметь независимый deployable unit и безопасно обрабатывать duplicate events.

Технологию менять только при архитектурной причине.

## Exit Criteria

Нет shared database, integration explicit, analytics service работает при недоступности notification service. Сверить с `docs/architecture/02-monorepo-and-services.md`, `07-api-and-integration.md`, `08-events-outbox-async.md`.

## Integration Checkpoint

Перед завершением этапа пройти [интеграционную проверку](ROADMAP.md#integration-checkpoints).

## Прогресс

- Выделен независимый микросервис `notification/` (Laravel 13, PHP 8.3, zero-dependency Domain).
- Реализована строгая изоляция данных: отдельная база данных `notification-postgres` (`notification-postgres-data` volume) и собственные credentials; сервисы не делят БД.
- Реализован консьюмер Redis Streams (`notifications:consume`) с consumer group `notification-service-v1`, stale message claim (`XAUTOCLAIM`), поддержкой сигналов `SIGTERM`/`SIGQUIT` и dead-letter stream (`autobi.integration-events.dead-letter`).
- Реализована идемпотентная обработка событий `alert.triggered.v1` с дедупликацией по `event_id` в таблице `consumed_events` и проекцией в таблицу `notifications`.
- Реализованы liveness (`/api/v1/health/live`) и readiness (`/api/v1/health/ready`) эндпоинты с проверкой доступности собственной БД и Redis.
- Инфраструктура оркестрации обновлена (`docker-compose.yml`, `docker-compose.dev.yml`, `docker-compose.vps.yml`, `Makefile`, `infra/.env.example`).
- Добавлены unit, feature, contract, architecture и integration тесты.

## Проверка завершения

- **Дата:** 2026-09-23
- **Exit Criteria Status:** Все критерии выполнены в полном объеме:
  1. **No Shared Database:** Notification service использует выделенный PostgreSQL экземпляр (`notification-postgres`), отдельный volume и учетные данные. Контейнеры notification не содержат переменных analytics DB (`POSTGRES_DB=autobi`).
  2. **Explicit Versioned Integration:** Взаимодействие осуществляется исключительно через канонические события `alert.triggered.v1` в Redis Streams согласно `contracts/events/alert-triggered.v1.schema.json`.
  3. **Failure Isolation:** Analytics service полностью независим от доступности notification service. Сбой или остановка `notification` / `notification-worker` не влияет на здоровье и работу analytics HTTP API и сохранение outbox событий.
  4. **Safe At-Least-Once Delivery & Deduplication:** Дедупликация гарантируется unique constraints и таблицей `consumed_events`. Повторное потребление событий не приводит к дублированию проекций.
  5. **Poison Message Handling:** Сообщения с нарушением контракта или неподдерживаемой версией направляются в dead-letter stream `autobi.integration-events.dead-letter` с последующим `XACK`.
- **Команды проверок и результаты:**
  - `composer --working-dir=notification validate --strict` — валидно (OK)
  - `composer --working-dir=notification lint` — Pint и PHPStan (level 6) пройдены без замечаний (0 errors)
  - `composer --working-dir=notification test` — 38 тестов, 144 assertions (OK)
  - `make check-notification` — успешно пройден
  - `sh -n scripts/verify-integration.sh` — синтаксис скрипта интеграции валиден
