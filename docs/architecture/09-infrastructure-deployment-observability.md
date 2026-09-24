# 09. Infrastructure, Deployment and Observability

## Контейнеризация

Каждый deployable-сервис должен иметь собственный Docker image.

Локальная среда должна позволять запускать всю систему через единый orchestration-файл для разработки.

## Базовые инфраструктурные компоненты

В состав инфраструктуры входят:

- `frontend` — Next.js UI / BFF;
- `backend` — Laravel Analytics Service;
- `postgres` (analytics) — PostgreSQL с pgvector, хранилище аналитики, support knowledge index и outbox;
- `support-generation-worker` — отдельная очередь `ai-generation`, один платный chat-вызов на claim;
- `support-indexing-worker` — отдельная очередь `ai-indexing` и бюджет embeddings;
- `backend-scheduler` — dispatcher сохранённых generation и maintenance lease/retention;
- `notification` — Laravel Notification Service (HTTP health/readiness endpoints);
- `notification-worker` — процесс потребления событий `notifications:consume`;
- `notification-postgres` — отдельная база данных PostgreSQL и volume для сервиса уведомлений;
- `redis` — общий транспорт интеграционных событий (Streams), кэш и очереди.

Notification service не получает учетных данных от `postgres` аналитики и не имеет сетевой зависимости от нее.
AI workers не обслуживают outbox. Недоступность AI provider не входит в общую readiness Analytics.

## Независимый деплой

Frontend, Analytics и Notification разворачиваются независимо:

- Каждый сервис имеет собственный Dockerfile и build target.
- Изменение Notification Service не требует пересборки Analytics или Frontend.
- Остановка `notification` или `notification-worker` не влияет на readiness/liveness сервисов Analytics и Frontend.
- Скрипты миграций выполняются независимо для каждой базы данных.

## Конфигурация

Конфигурация среды должна храниться вне бизнес-кода.

Секреты не должны находиться в репозитории.

RAG adapters выбираются серверной конфигурацией. Детерминированные adapters разрешены только в
testing и локальной среде; production по умолчанию использует `disabled`, пока не утверждены provider,
регион обработки, retention/training policy и категории данных.
Backend image собирается из корня репозитория и включает `docs/support` как read-only build input в
`/var/www/docs/support`; ingestion worker не зависит от checkout или writable volume на сервере.
`SESSION_SECRET` подписывает Next.js session cookie, а отдельный `SUPPORT_BFF_SHARED_SECRET`
аутентифицирует только внутренний путь Next.js BFF → Analytics. Оба значения server-only,
различаются между собой, не имеют `NEXT_PUBLIC_` префикса и передаются через secret storage среды.

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

## Health Checks

Каждый сервис предоставляет технические health checks:

- **Analytics Service:** `GET /api/v1/health` — проверка доступности HTTP API и готовности сервиса.
- **Notification Service:**
  - `GET /api/v1/health/live` — liveness probe: проверяет, что HTTP-процесс запущен и принимает запросы.
  - `GET /api/v1/health/ready` — readiness probe: проверяет доступность локальной PostgreSQL notification service и Redis Stream транспорта. Analytics не является runtime-зависимостью и не опрашивается.
- Состояние фоновых workers логируется структурно (heartbeat / processed count). Остановка worker не влияет на liveness веб-процессов.
- Support generation пишет безопасные структурные события lifecycle/latency по IDs и error codes,
  без raw prompt, документов, credentials и полного provider exception.

## Будущие инструменты

По мере развития могут быть подключены:

- OpenTelemetry;
- Prometheus;
- Grafana;
- Sentry;
- централизованный сбор логов.

Выбор конкретного инструмента не должен влиять на Domain Layer.
