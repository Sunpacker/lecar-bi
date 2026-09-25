# Эксплуатационный Runbook: Мониторинг и реагирование на инциденты

## 1. Обзор стека Observability

Инфраструктура наблюдаемости AutoBI разворачивается в Docker-окружении:
- **Prometheus** (`prom/prometheus:v2.54.1`) — сбор и хранение метрик, оценка alert rules каждые 10 секунд.
- **Grafana Loki** (`grafana/loki:3.1.1`) — централизованное хранилище структурированных логов.
- **Grafana Alloy** (`grafana/alloy:v1.3.1`) — агент сбора stdout/stderr логов Docker контейнеров.
- **Grafana** (`grafana/grafana:11.2.0`) — дашборды сервисов, фоновых процессов и поиск по логам.

### Политика хранения (Retention)
- **Loki:** 7 дней (`168h`), автоматическая очистка старых чанков через compactor (`retention_enabled: true`).
- **Prometheus:** 15 дней (по умолчанию `--storage.tsdb.retention.time=15d`), локальный TSDB том `prometheus-data`.
- **Сеть и безопасность:** эндпоинты `/metrics` и интерфейсы Prometheus/Loki/Alloy доступны только внутри Docker-сети `internal`. Внешний доступ изолирован.

---

## 2. Эксплуатационные правила (Alert Rules)

### TargetDown
- **Условие:** `up == 0` в течение 30 секунд.
- **Критичность:** `critical`.
- **Диагностика:**
  1. Проверить статус контейнеров: `docker compose ps`.
  2. Проверить логи упавшего сервиса: `docker compose logs -n 100 <service_name>`.
  3. Проверить доступность liveness/readiness изнутри сети:
     `docker compose exec backend curl -fsS http://backend:8080/api/v1/health/live`
- **Действия по восстановлению:**
  1. Перезапустить контейнер: `docker compose restart <service_name>`.
  2. Если контейнер падает циклически (CrashLoop), проверить ошибки подключения к БД/Redis в логах.
  3. При нехватке памяти/CPU проверить системные ресурсы хоста (`free -m`, `docker stats`).

---

### ReadinessFailed
- **Условие:** `dependency_up == 0` в течение 30 секунд.
- **Критичность:** `critical`.
- **Диагностика:**
  1. Определить упавшую зависимость из лейбла `dependency` (`database` или `redis`).
  2. Для PostgreSQL: проверить статус и логи контейнера `postgres` или `notification-postgres`.
     `docker compose exec postgres pg_isready -U autobi -d autobi`
  3. Для Redis: проверить статус сервиса `redis`:
     `docker compose exec redis redis-cli ping`
- **Действия по восстановлению:**
  1. При деградации пула соединений PostgreSQL: проверить активные подключения в `pg_stat_activity`.
  2. При отказе Redis: перезапустить сервис `redis`. Сервисы аналитики и уведомлений продолжат работу с безопасным fail-open режимом для кэша.

---

### NotificationWorkerHeartbeatMissing
- **Условие:** `worker_up{service="notification"} == 0` в течение 1 минуты.
- **Критичность:** `critical`.
- **Диагностика:**
  1. Проверить статус процесса воркера через CLI:
     `docker compose exec notification-worker php artisan notifications:worker-health --json`
  2. Проверить логи контейнера `notification-worker`:
     `docker compose logs -n 100 notification-worker`
  3. Проверить статус consumer group в Redis:
     `docker compose exec redis redis-cli -n 2 XINFO CONSUMERS autobi.integration-events notification-service-v1`
- **Действия по восстановлению:**
  1. Перезапустить процесс воркера: `docker compose restart notification-worker`.
  2. После запуска убедиться в возобновлении heartbeat:
     `docker compose exec notification curl -fsS http://localhost:8081/api/v1/health/worker`

---

### OutboxBacklogGrowing
- **Условие:** `outbox_backlog_total > 50` в течение 2 минут.
- **Критичность:** `warning`.
- **Диагностика:**
  1. Запустить CLI-диагностику outbox:
     `docker compose exec backend php artisan outbox:health --json`
  2. Проверить логи публикации событий:
     `docker compose logs -n 100 backend | grep -i outbox`
  3. Проверить доступность брокера Redis Streams:
     `docker compose exec redis redis-cli -n 2 PING`
- **Действия по восстановлению:**
  1. Принудительно запустить итерацию публикации:
     `docker compose exec backend php artisan outbox:publish`
  2. Если сообщения блокируются из-за ошибок валидации, проверить таблицу `outbox_messages` со статусом `failed`.

---

### OutboxOldMessageStuck
- **Условие:** `outbox_oldest_message_age_seconds > 120` в течение 1 минуты.
- **Критичность:** `warning`.
- **Диагностика:**
  1. Выполнить `docker compose exec backend php artisan outbox:health`.
  2. Найти идентификатор застрявшего сообщения:
     `docker compose exec backend php artisan tinker --execute="echo \DB::table('outbox_messages')->where('status', 'pending')->orderBy('occurred_at')->value('id');"`
  3. Проверить причину в логах по `event_id`.
- **Действия по восстановлению:**
  1. Запустить повторную отправку: `docker compose exec backend php artisan outbox:retry`.
  2. Если сообщение невалидно, перевести в архив или исправить запись в БД.

---

### IntegrationDeadLetterMessagesPresent
- **Условие:** `integration_dead_letter_total > 0` в течение 1 минуты.
- **Критичность:** `warning`.
- **Диагностика:**
  1. Проверить размер и последние записи в dead-letter stream:
     `docker compose exec redis redis-cli -n 2 XLEN autobi.integration-events.dead-letter`
     `docker compose exec redis redis-cli -n 2 XREVRANGE autobi.integration-events.dead-letter + - COUNT 5`
  2. Найти логи перемещения события:
     В Grafana Logs Search ввести запрос: `{service="notification"} |= "dead_letter"`.
- **Действия по восстановлению:**
  1. Изучить причину отбраковки события (`reason`, декодер, несовместимость схемы).
  2. После исправления схемы или кода повторно опубликовать сообщения из dead-letter стрима.

---

### HttpHighErrorRate
- **Условие:** Доля HTTP 5xx ответов превышает 5% за 5-минутное окно (`> 0.05`) в течение 2 минут.
- **Критичность:** `critical`.
- **Диагностика:**
  1. Открыть дашборд Grafana `AutoBI - Service Overview` для определения сбоящего сервиса и маршрута.
  2. Открыть дашборд `AutoBI - Log Search`, отфильтровать по уровню `ERROR`:
     `{service="backend"} |= "\"level\":\"ERROR\""`
  3. Проверить коды ошибок и стектрейсы в JSON логах.
- **Действия по восстановлению:**
  1. При ошибках БД: проверить блокировки и таймауты PostgreSQL.
  2. При ошибках зависимостей: перезапустить деградировавший сервис.
