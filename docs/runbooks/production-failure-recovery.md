# Руководство по ликвидации аварий и восстановлению (Production Failure Recovery Runbook)

Документ подготовлен в рамках [Phase 18 — Production Hardening](../roadmap/18-production-hardening.md) для дежурных инженеров и операторов сервисов AutoBI.

---

## 1. Сводная таблица кодов ответов и состояний задач

| Сценарий / Сбой | HTTP код | Поведение системы | Источник сигналов | Первичное действие оператора |
| :--- | :--- | :--- | :--- | :--- |
| **Превышение Rate Limit (login)** | `429 Too Many Requests` | Запрос отклоняется с `code: TOO_MANY_REQUESTS` и заголовком `Retry-After`. Состояние не меняется. | Логи Analytics (`429`), Nginx/Caddy access log | Проверить IP на признак брутфорс-атаки; лимит сбрасывается автоматически через 60 сек. |
| **Превышение Rate Limit (imports/API)** | `429 Too Many Requests` | Запрос отклоняется. Никаких записей в БД не создается. | Логи Analytics, заголовок `Retry-After` | Убедиться, что клиент соблюдает экспоненциальный backoff. |
| **Сбой подключения к PostgreSQL (Analytics)** | `500 Internal Server Error` | Ошибка логируется с маскированием учетных данных. Liveness (`/up`) жив, Readiness возвращает ошибку. | Логи `analytics`, alert `AnalyticsDatabaseDown` | Проверить статус контейнера `postgres` (`docker compose ps postgres`, `docker compose logs postgres`). |
| **Сбой подключения к Redis (кэш)** | `200 OK` (Fail-Open) | Система автоматически переходит в режим прямого чтения из PostgreSQL (Fail-open fallback). В логи пишется предупреждение `WARNING`. | Логи `JsonLogFormatter`: `Analytics cache read/write failure; fallback to PostgreSQL` | Проверить доступность Redis, память и CPU; сервис продолжает обслуживать запросы без деградации данных. |
| **Сбой подключения к Redis (очереди / Outbox)** | Фоновые задачи остаются в очереди / Outbox | Сообщения в таблице `outbox_messages` остаются в статусе `pending` / планируют `retry_scheduled`. | Логи `PublishOutboxMessagesJob`, метрика лага Outbox | Перезапустить контейнер `redis`. После восстановления запустить `php artisan outbox:publish`. |
| **Сбой воркера импорта (`ProcessImportJob`)** | Задание помещается в `failed_jobs` | При ошибке парсинга батч переходит в `failed`. При таймауте задача повторяется 3 раза (backoff: 5s, 15s, 30s) и попадает в `failed_jobs`. | Таблица `failed_jobs`, таблица `import_batches` (`status=failed`) | Проверить логи с `job_id`, исправить причину и перезапустить: `php artisan queue:retry <id>`. |
| **Краш Notification Worker (`notifications:consume`)** | Сообщения остаются в Pending Entries List (PEL) | Сообщения не теряются и не подтверждаются (нет `XACK`). При рестарте воркер вызывает `claimStale` и забирает зависшие сообщения. | Логи `notification-worker`, возраст PEL | Запустить воркер: `docker compose start notification-worker`. |
| **Ядовитое сообщение (Poison Message) в Stream** | Перемещение в Dead-Letter Stream | Невалидное событие переносится в `autobi.integration-events.dead-letter` с причиной, оригинальное сообщение подтверждается `XACK`. | Логи Notification `dead_letter`, стрим `dead-letter` | Изучить сообщение в Dead-Letter Stream, устранить ошибку в контракте или коде декодера. |

---

## 2. Действия при отказах сервисов

### 2.1. Отказ PostgreSQL (Analytics Service)

1. Проверить статус контейнера и дисковое пространство:
   ```bash
   docker compose ps postgres
   docker compose logs --tail=100 postgres
   df -h
   ```
2. Если контейнер остановлен:
   ```bash
   docker compose start postgres
   docker compose exec postgres pg_isready -U autobi -d autobi
   ```
3. Проверить healthcheck аналитики:
   ```bash
   curl -fsS http://localhost:8080/api/v1/health
   ```

### 2.2. Отказ Redis

1. Проверить статус контейнера:
   ```bash
   docker compose ps redis
   docker compose exec redis redis-cli ping
   ```
2. Аналитический кэш работает в режиме **Fail-Open**: запросы к аналитике продолжают выполняться напрямую в PostgreSQL.
3. После перезапуска Redis очереди и стримы восстанавливают работу автоматически:
   ```bash
   docker compose restart redis
   docker compose exec backend php artisan outbox:publish
   ```

### 2.3. Зависание сообщений в Outbox (Analytics Service)

Если сообщения накапливаются в таблице `outbox_messages` со статусом `pending`:
1. Проверить количество зависших сообщений:
   ```bash
   docker compose exec backend php artisan outbox:retry --dry-run
   ```
2. Принудительно сбросить статус и запустить повторную отправку:
   ```bash
   docker compose exec backend php artisan outbox:retry --force
   docker compose exec backend php artisan outbox:publish
   ```

### 2.4. Восстановление Notification Worker и обработка Dead-Letter

1. Проверить статус консьюмера и воркера:
   ```bash
   docker compose ps notification-worker
   docker compose logs --tail=100 notification-worker
   ```
2. Если воркер упал, при перезапуске он автоматически выполняет операцию `XCLAIM` для сообщений старше 60 секунд (`staleIdleMs = 60000`).
3. Для просмотра и ручного разбора ядовитых сообщений в Dead-Letter Stream:
   ```bash
   docker compose exec redis redis-cli -n 2 XREVRANGE autobi.integration-events.dead-letter + - COUNT 10
   ```
4. После устранения дефекта декодера повторная отправка события безопасна благодаря проверке `ConsumedEventModel::exists(event_id)`.
