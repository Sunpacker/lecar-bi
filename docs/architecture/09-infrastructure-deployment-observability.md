# 09. Infrastructure, Deployment and Observability

## Контейнеризация

Каждый deployable-сервис должен иметь собственный Docker image.

Локальная среда должна позволять запускать всю систему через единый orchestration-файл для разработки.

## Базовые инфраструктурные компоненты

В состав инфраструктуры входят:

- `frontend` — Next.js UI / BFF;
- `backend` — Laravel Analytics Service;
- `postgres` (analytics) — хранилище аналитики и outbox;
- `notification` — Laravel Notification Service (HTTP health/readiness endpoints);
- `notification-worker` — процесс потребления событий `notifications:consume`;
- `notification-postgres` — отдельная база данных PostgreSQL и volume для сервиса уведомлений;
- `redis` — общий транспорт интеграционных событий (Streams), кэш и очереди:
  - database 0: default / queues;
  - database 1: аналитический селективный кэш (отдельная изолированная БД, `volatile-ttl`);
  - database 2: интеграционные события Redis Stream (`autobi.integration-events`).

Notification service не получает учетных данных от `postgres` аналитики и не имеет сетевой зависимости от нее.

## Независимый деплой

Frontend, Analytics и Notification разворачиваются независимо:

- Каждый сервис имеет собственный Dockerfile и build target.
- Изменение Notification Service не требует пересборки Analytics или Frontend.
- Остановка `notification` или `notification-worker` не влияет на readiness/liveness сервисов Analytics и Frontend.
- Скрипты миграций выполняются независимо для каждой базы данных.

## Конфигурация

Конфигурация среды должна храниться вне бизнес-кода.

Секреты не должны находиться в репозитории.

## Observability

Система должна постепенно включать:

- structured logs;
- request identifiers;
- correlation identifiers;
- error tracking;
- metrics;
- distributed tracing;
- health checks.

## Correlation ID

При прохождении одного пользовательского запроса через несколько сервисов должен сохраняться общий correlation identifier.

Это необходимо для поиска связанных записей в логах и дальнейшего distributed tracing.

## Structured Logging

Логи должны быть пригодны для машинной обработки.

Следует избегать неструктурированных произвольных сообщений как единственного источника информации.

### Санитарное логирование при сбоях кэша (Sanitized Fail-Open Logging)

При недоступности, таймаутах или деградации Redis инфраструктурный кэш логирует предупреждение уровня warning:
- В контекст включаются только технические метаданные: имя датасета (`dataset`), `workspace_id`, каноническая операция (`operation`) и класс ошибки (`RedisException`).
- Категорически исключаются: полезная нагрузка ответов (payload), персональные данные, заголовки авторизации и параметры SQL.
- Ошибка не прерывает запрос: система выполняет fail-open fallback на прямое обращение к PostgreSQL Read Model.


## Health Checks

Каждый сервис предоставляет технические health checks:

- **Analytics Service:** `GET /api/v1/health` — проверка доступности HTTP API и готовности сервиса.
- **Notification Service:**
  - `GET /api/v1/health/live` — liveness probe: проверяет, что HTTP-процесс запущен и принимает запросы.
  - `GET /api/v1/health/ready` — readiness probe: проверяет доступность локальной PostgreSQL notification service и Redis Stream транспорта. Analytics не является runtime-зависимостью и не опрашивается.
- Состояние фоновых workers логируется структурно (heartbeat / processed count). Остановка worker не влияет на liveness веб-процессов.

## Observability Стек

Внедрён централизованный стек сбора метрик, логов и дашбордов:
- **Prometheus** (`prom/prometheus:v2.54.1`): сбор метрик через внутренние эндпоинты `/metrics` сервисов `backend:8080` и `notification:8081` каждые 10 секунд. Оценка эксплуатационных alert rules (`TargetDown`, `ReadinessFailed`, `NotificationWorkerHeartbeatMissing`, `OutboxBacklogGrowing`, `OutboxOldMessageStuck`, `IntegrationDeadLetterMessagesPresent`, `HttpHighErrorRate`).
- **Grafana Loki** (`grafana/loki:3.1.1`): единое хранилище структурированных JSON-логов с политикой хранения 7 дней (`retention_period: 168h`).
- **Grafana Alloy** (`grafana/alloy:v1.3.1`): контейнерный коллектор логов, считывающий stdout/stderr через сокет `/var/run/docker.sock` с нормализацией низкокардинальных лейблов (`service`, `container`).
- **Grafana** (`grafana/grafana:11.2.0`): автоматический provisioning источников (Prometheus, Loki) и встроенные дашборды:
  - *Service Overview*: доступность, RPS по маршрутам, 5xx ошибки, p95 latency, health dependencies;
  - *Background Processes*: outbox backlog, возраст сообщений, worker heartbeat, lag стримов, dead-letter очередь, job executions;
  - *Log Search*: кросс-сервисный поиск логов по `request_id`, `correlation_id`, `event_id`.

Эндпоинты `/metrics` изолированы внутри Docker-сети `internal` и не экспортируются в публичный интернет через реверс-прокси.

## Дальнейшая эволюция

- **OpenTelemetry / Tempo**: подключение distributed tracing при необходимости сквозного профилирования задержек по пути Next.js → Analytics → Outbox → Notification.
- **Внешние каналы оповещений**: настройка Alertmanager (Telegram, Email, Webhook) через секреты окружения.

Выбор конкретного инструмента не влияет на Domain Layer.
