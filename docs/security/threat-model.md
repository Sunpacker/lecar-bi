# Матрица угроз и границы безопасности (Threat Model & Security Verification)

Документ подготовлен в рамках [Phase 18 — Production Hardening](../roadmap/18-production-hardening.md) на основе требований [OWASP ASVS 5.0.0](https://owasp.org/projects/asvs/) и методологии STRIDE.

---

## 1. Публичная поверхность и сетевые границы

```
[ Интернет / Клиенты ]
          │
          ▼ HTTPS (443 / 80)
┌──────────────────────────────────────────────┐
│  Reverse Proxy (Caddy / Nginx)               │
│  - TLS termination                           │
│  - Строгие security headers                  │
│  - Ограничение размера тела запроса (50M)    │
└──────────────┬───────────────────────────────┘
               │
        ┌──────┴──────────────────────┐
        ▼                             ▼
┌──────────────────────┐      ┌─────────────────────────┐
│ Next.js Frontend/BFF │      │ Analytics API (Laravel) │
│ Port 3000            │      │ Port 8080               │
└──────────────────────┘      └───────────┬─────────────┘
                                          │
       ═══════════════════════════════════╪════════════════════════════════════
       Внутренняя изолированная сеть      │ (internal: true, порты закрыты)
       ═══════════════════════════════════╪════════════════════════════════════
               ┌──────────────────────────┼─────────────────────────┐
               ▼                          ▼                         ▼
      ┌─────────────────┐        ┌──────────────────┐      ┌──────────────────┐
      │ PostgreSQL      │        │ Redis 7          │      │ Notification     │
      │ (analytics, 5432│        │ - DB 0: Queues   │      │ Service (8081)   │
      │  не видна извне)│        │ - DB 1: Cache    │      │ & Worker         │
      └─────────────────┘        │ - DB 2: Streams  │      └─────────┬────────┘
                                 └──────────────────┘                │
                                                                     ▼
                                                           ┌──────────────────┐
                                                           │ Notification     │
                                                           │ PostgreSQL (5433)│
                                                           └──────────────────┘
```

- **Analytics DB (`postgres:5432`), Notification DB (`notification-postgres:5432`), Redis (`redis:6379`)** не имеют открытых портов наружу в production (`docker-compose.vps.yml`). Доступ возможен исключительно из внутренних контейнеров в сети `internal`.
- **Notification Service (`notification:8081`)** и его worker работают внутри сети `internal` и не публикуются в публичном обратном прокси. Доступ к HTTP-пробам возможен только локально для оркестратора.
- **Технические endpoints** (`/up`, `/api/v1/health`, worker probes) не раскрывают конфиденциальной информации и версии зависимостей.

---

## 2. Матрица угроз STRIDE и контроль OWASP ASVS 5.0.0

| Компонент | Угроза (STRIDE) | Вектор атаки | Механизм защиты и контроль ASVS | Статус / Владелец |
| :--- | :--- | :--- | :--- | :--- |
| **Браузер / Сессия** | Spoofing / Tampering | Подмена `userId` в session cookie для эскалации привилегий | **ASVS V3.2, V3.5:** Сессионные cookie подписаны криптографическим HMAC SHA-256 (`session.ts`). Атрибуты: `HttpOnly`, `SameSite=Lax`, `Secure` (в prod). При нарушении подписи сессия немедленно инвалидируется. | Исправлено / Frontend |
| **Браузер / DOM** | Tampering / Information Disclosure | XSS, Clickjacking, MIME-sniffing | **ASVS V14.4:** Внедрены `Content-Security-Policy`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`. | Исправлено / Frontend & Backend |
| **Next.js BFF** | Spoofing | Обход аутентификации страниц | **ASVS V3.1:** `proxy.ts` валидирует наличие и срок жизни подписанной сессии. Защищённые маршруты автоматически редиректят на `/login`. | Исправлено / Frontend |
| **Analytics API** | Spoofing / Elevation of Privilege | Неавторизованный доступ к данным чужого workspace | **ASVS V4.1, V4.2:** Двухуровневая серверная валидация: `AuthenticateUserIdMiddleware` + `RequireWorkspaceCapabilityMiddleware`. Backend проверяет членство пользователя в workspace и требуемую capability (`analytics.view`, `dashboards.manage`, etc.). Ошибка доступа возвращает 403 Forbidden. Client-side не является источником доверия. | Проверено / Backend |
| **API Auth / Login** | Denial of Service / Repudiation | Брутфорс паролей учетных записей | **ASVS V2.2, V13.1:** Redis-backed rate limiting `throttle:login`: максимум 5 попыток в минуту на один IP. При превышении возвращается `429 Too Many Requests`. Пароли хэшируются Argon2id/Bcrypt. | Исправлено / Backend |
| **Файловый импорт** | Tampering / Denial of Service | Загрузка исполняемых файлов, path traversal, бомбы распаковки | **ASVS V5.1, V5.5, V12.1:** Белый список расширений (`csv`, `json`, `jsonl`). Жесткое ограничение размера (50 МБ). Файлы сохраняются под случайным UUID в изолированной директории `storage/app/imports/{workspaceId}`. Потоковый парсинг без загрузки всего файла в память. | Проверено / Data Ingestion |
| **Redis** | Information Disclosure / Tampering | Доступ к данным между кэшем, очередями и брокером | **ASVS V14.2:** Строгое разделение: DB 0 для очередей, DB 1 для аналитического кэша, DB 2 для стрима интеграционных событий. Изолированный контейнер без публикации портов на внешнем интерфейсе. В production задается `REDIS_PASSWORD`. | Проверено / Infra |
| **Notification Consumer** | Tampering / Repudiation | Poison message / повторная обработка / сбой воркера | **ASVS V10.3:** At-least-once доставка с идемпотентной дедупликацией по `event_id` в таблице `consumed_events`. Невалидные события отправляются в Dead-Letter Stream `autobi.integration-events.dead-letter`. XACK только после успешного коммита транзакции. | Проверено / Notification |
| **PostgreSQL (обе БД)** | Information Disclosure / Elevation of Privilege | Доступ notification сервиса к фактам аналитики | **ASVS V1.14, V4.1:** Полная изоляция баз данных: `autobi` (analytics) и `notification` разнесены по разным контейнерам, томам и учетным данным. У notification service отсутствуют сетевые пути и credentials к аналитической БД. | Проверено / Infra |
| **Обработка ошибок** | Information Disclosure | Утечка SQL-запросов, переменных окружения и stack trace | **ASVS V7.4:** При `APP_ENV=production` параметр `APP_DEBUG` принудительно отключается. `ProductionSafetyCheck` блокирует запуск приложения, если в production включен debug. Все API-ошибки форматируются в унифицированный JSON (`message`, `code`). | Исправлено / Backend |

---

## 3. ZAP Automation Framework (DAST) интеграция

Для автоматизированного динамического сканирования безопасности API подготовлен план для OWASP ZAP Automation Framework:

- **Конфигурация:** `docs/security/zap-baseline.yaml`
- **Профиль проверок:**
  1. Импорт OpenAPI спецификации `contracts/openapi/analytics-v1.yaml`.
  2. Проверка CORS и security headers (`X-Frame-Options`, `X-Content-Type-Options`, `CSP`).
  3. Проверка ответов 401 Unauthorized на закрытых эндпоинтах без передачи `X-User-Id`.
  4. Проверка поведения при передаче некорректных форматов данных и больших нагрузок.
  5. Проверка сокрытия stack traces и чувствительных данных в телах ошибок.
- **Ограничение:** Сканирование запускается только на тестовых данных в изолированном Docker-окружении (`infra/docker-compose.yml`), без воздействия на продуктивные системы.

---

## 4. Реестр найденных проблем и статус устранения

| № | Уязвимость / Риск | Компонент | Исходный уровень | Принятые меры | Статус |
| :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | Сессионные cookie не проверяли целостность | Frontend | **High** | Добавлена криптографическая подпись HMAC-SHA256 (`session.ts`) и проверка с `timingSafeEqual`. | **Resolved** |
| 2 | Отсутствовали лимиты на эндпоинте `/auth/login` | Backend | **Medium** | Внедрен Redis-backed Rate Limiter на 5 попыток/мин по IP (`throttle:login`) с ответом HTTP 429. | **Resolved** |
| 3 | Отсутствие security headers в ответах API и Next.js | Frontend / Backend | **Medium** | Добавлены `SecurityHeadersMiddleware` в Laravel и `headers()` в `next.config.mjs`. | **Resolved** |
| 4 | Риск случайного запуска в production с `APP_DEBUG=true` | Backend | **High** | Реализован `ProductionSafetyCheck` в `AppServiceProvider::boot()`, блокирующий запуск при нарушении инвариантов. | **Resolved** |
| 5 | Отсутствие таймаутов на сетевых подключениях к DB и Redis | Backend / Notification | **Medium** | Сконфигурированы явные таймауты подключения и чтения (`DB_TIMEOUT=5`, `REDIS_TIMEOUT=3.0`, `REDIS_READ_TIMEOUT=3.0`). | **Resolved** |
| 6 | Несогласованность таймаута фоновых задач и `retry_after` | Backend Queue | **Medium** | Заданы `$timeout = 60` для воркеров при `retry_after = 90` в `queue.php`, исключая дублирование задач. | **Resolved** |
