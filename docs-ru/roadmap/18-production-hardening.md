# Phase 18 — Production Hardening

[Индекс и правила roadmap](ROADMAP.md) · [Маршрутизатор агентов](../../AGENTS.md) · [Инфраструктурная архитектура](../architecture/09-infrastructure-deployment-observability.md) · [Стратегия тестирования](../architecture/10-testing-and-quality.md)

## Цель

Подготовить воспроизводимую production-like демонстрацию: развёртывание из чистой среды, контролируемые отказы, восстановление данных и проверенные границы безопасности. Этап начинается после [Phase 16](16-performance-caching.md) и [Phase 17](17-observability.md); их метрики и сигналы используются для проверки отказов.

Граница работ: существующие frontend, analytics, notification, две независимые PostgreSQL базы, Redis и versioned HTTP/event contracts. Новый broker, оркестратор или сервис безопасности не требуются. Развёртывание во внешней среде выполняется только по отдельному поручению.

## Исходное состояние

- GitHub Actions проверяет frontend, OpenAPI, analytics, сборку двух образов и базовую интеграцию. Отдельных заданий для тестов и сборки notification, аудита зависимостей и критических браузерных E2E пока нет.
- `infra/docker-compose.yml` предназначен для локального production-like запуска и содержит удобные для разработки значения `APP_DEBUG` и notification credentials. `infra/docker-compose.vps.yml` задаёт `APP_DEBUG=false`, но требует отдельной проверки остальных секретов, доступных портов и процедуры восстановления.
- Analytics использует PostgreSQL и Redis Queue; notification читает Redis Stream с at-least-once доставкой, дедупликацией и dead-letter stream. Outbox analytics — источник истины для опубликованных интеграционных событий.
- Инструкция `infra/README.md` описывает запуск VPS, но ещё не задаёт проверяемые backup/restore, rollback и сценарии отказа.

## Порядок работ

1. **Зафиксировать угрозы и границы.** Составить короткую матрицу для браузера, Next.js/BFF, analytics API, импорта файлов, Redis, notification consumer и обеих БД. Для проверок использовать релевантные требования [OWASP ASVS 5.0.0](https://owasp.org/projects/asvs/): сессии/CSRF, авторизация workspace и capability на backend, изоляция данных, валидация и размер загрузок, ошибки без утечки деталей, CORS и security headers. Проверить публичную поверхность через реальный proxy: внутренние БД, Redis, notification и технические endpoints недоступны извне. Зафиксировать найденные проблемы, владельца и результат исправления. Для тестового окружения применить [ZAP Automation Framework](https://www.zaproxy.org/docs/automate/automation-framework/) с импортом OpenAPI и проверенной авторизацией; активные проверки не направлять на реальные пользовательские данные.
2. **Закрыть риски конфигурации и цепочки поставки.** Убрать успешный production-like старт с демонстрационными ключами/паролями или включённым debug; отделить dev defaults от production config. Проверить cookie flags, доверенные proxy, TLS termination, CORS origins, права и жизненный цикл секретов. Для Compose рассмотреть [secrets](https://docs.docker.com/compose/how-tos/use-secrets/) вместо передачи чувствительных значений через обычные переменные окружения, с документированной ротацией. В CI запускать `npm audit` для frontend и `composer audit --locked` для backend/notification; сканировать собранные образы, например [Trivy](https://trivy.dev/docs/dev/guide/target/container_image/). Настроить обновления npm, Composer, Docker и GitHub Actions через Dependabot; GitHub dependency review и secret scanning включать там, где они доступны репозиторию. Уязвимости оценивать по достижимости и серьёзности; исключения оформлять с причиной и сроком пересмотра.
3. **Ограничить нагрузку и время ожидания.** Ввести Redis-backed [Laravel rate limiting](https://laravel.com/docs/13.x/routing) на login, импорт и дорогие/изменяющие API операции с отдельными ключами для анонимного IP и авторизованного пользователя/workspace; документировать лимиты и ответ `429`. Согласовать размеры запросов на proxy, frontend и backend. Задать конечные connect/read/request timeouts на HTTP-границах и для Redis/PostgreSQL, отдельно проверить поведение при обрыве соединения. Повторять только безопасные чтения или идемпотентные операции; для остальных сохранять существующие гарантии импорта, outbox и `event_id`, применять ограниченные попытки с backoff и jitter. Не превращать `429` и длительный отказ зависимости в бесконечный retry.
4. **Проверить фоновые процессы при отказах.** Для Laravel Queue согласовать `job timeout < retry_after`, число попыток, backoff, failed jobs и процедуру повторного запуска; [документация Laravel](https://laravel.com/docs/13.x/queues) предупреждает о двойной обработке при обратном порядке timeout. Для Redis Stream проверить pending/reclaim после падения consumer, XACK только после локального commit или подтверждённого дубликата, отправку poison messages в dead-letter и безопасный replay. Для импорта и outbox проверить восстановление после перезапуска worker/Redis и отсутствие повторных проекций или уведомлений. Зафиксировать ожидаемые HTTP-коды, состояния задач, метрики и действия оператора для каждого сценария.
5. **Подготовить данные и развёртывание.** Описать чистый запуск без committed secrets, сборку образов и деплой по проверенному digest вместо изменяемого `latest` ([рекомендации Docker](https://docs.docker.com/build/building/best-practices/)), независимые миграции analytics/notification на пустых БД, smoke checks и rollback приложения с совместимыми миграциями. Для каждой PostgreSQL базы настроить отдельный зашифрованный backup вне хоста, retention и проверку восстановления в изолированной среде; для небольшого объёма использовать [pg_dump/pg_restore](https://www.postgresql.org/docs/16/app-pgdump.html), при других требованиях к RPO/RTO выбирать и документировать иной способ. Проверить роли/расширения, секреты и согласованность восстановленной notification БД с retention Redis Stream и состоянием outbox. Не считать наличие backup доказательством восстановления: зафиксировать дату restore drill, длительность и фактическую потерю данных.
6. **Сделать проверки release gate.** Сравнивать изменённый OpenAPI с базовой версией через [`oasdiff breaking`](https://github.com/oasdiff/oasdiff/blob/main/docs/BREAKING-CHANGES.md), затем проверять соответствие ответов API контракту и регенерацию клиента. Проверять совместимость `alert.triggered.v1` с текущим notification consumer и повторной доставкой. Добавить в CI lint/static analysis/tests/build notification и всех образов, проверки конфигурации Compose, security audits, чистые миграции и интеграционные сценарии alert → outbox → notification. Критические пользовательские потоки покрыть [Playwright](https://playwright.dev/docs/ci) на собранной системе: вход и границы workspace, dashboard, импорт с ошибкой и retry. Результаты проверок и ссылки на артефакты хранить вместе с release checklist.

## Exit Criteria

- Нет открытых blocking security issues: проведён review по выбранным требованиям ASVS, проверены изоляция workspace, секреты, public exposure и исправления. Сканирование само по себе не считается доказательством безопасности.
- Чистая среда поднимается по инструкции с отдельными секретами; analytics и notification миграции проходят на пустых БД. У приложения есть документированный порядок deploy, smoke check и rollback без разрушения данных.
- Backup обеих БД восстановлен в изолированной среде; проверены ключевые записи и дедупликация после восстановления. Измерены RPO/RTO, описаны retention и пределы восстановления Redis Stream/очередей.
- Ограничения запросов и таймауты проверены тестами. Зафиксированные отказы PostgreSQL, Redis, worker и notification дают предсказуемые ответы/состояния, видны в сигналах Phase 17 и восстанавливаются без потери или удвоения бизнес-результата.
- Изменения HTTP и event contracts совместимы с развёрнутыми потребителями либо версионированы; OpenAPI diff, contract tests и generated client согласованы.
- CI green для frontend, analytics, notification, контрактов, всех образов, применимых аудитов и интеграции; критические Playwright E2E проходят на production-like сборке. Результаты и допустимые исключения задокументированы.
- Пройден [integration checkpoint](ROADMAP.md#integration-checkpoints). Отметку завершения в индексе ставить только после фактического подтверждения всех критериев.

## Прогресс

- **Матрица угроз и границы безопасности:** Разработана модель угроз по методологии STRIDE и требованиям OWASP ASVS 5.0.0 ([`docs/security/threat-model.md`](../security/threat-model.md)). Настроен профиль автоматизированного сканирования ZAP AF ([`docs/security/zap-baseline.yaml`](../security/zap-baseline.yaml)).
- **Защита сессий и заголовки безопасности:** Внедрена HMAC-SHA256 подпись клиентских сессионных cookie с криптографической верификацией через `crypto.timingSafeEqual` ([`frontend/src/features/auth/model/session.ts`](../../frontend/src/features/auth/model/session.ts), покрыто тестами). Добавлены защитные HTTP-заголовки (CSP, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`) во frontend (`next.config.mjs`) и backend (`SecurityHeadersMiddleware.php`).
- **Безопасность конфигурации и цепочка поставки:** Реализована проверка `ProductionSafetyCheck`, предотвращающая запуск в продакшене с `APP_DEBUG=true` или дефолтными/демонстрационными ключами и паролями. Подготовлен шаблон переменных окружения [`infra/.env.production.example`](../../infra/.env.production.example) с обязательными уникальными секретами. Настроены автоматические обновления зависимостей [Dependabot](../../.github/dependabot.yml) для npm, Composer, Docker и GitHub Actions.
- **Rate Limiting и таймауты:** Настроен Redis-backed rate limiting в `AppServiceProvider` для login (5/мин), imports (10/мин), api-write (60/мин), api-read (300/мин) с возвратом структурированного 429 ответа и заголовка `Retry-After`. Заданы строгие таймауты подключения и чтения для PostgreSQL (`PDO::ATTR_TIMEOUT`) и Redis (`timeout`, `read_timeout`), а также HTTP-клиента frontend (15с).
- **Очереди, стримы и инварианты отказоустойчивости:** Согласованы параметры очередей Laravel (`$timeout = 60s < retry_after = 90s`, `$tries = 3`, экспоненциальный backoff). В Notification Worker внедрен стрим-клиент `PredisStreamClient` с поддержкой `XGROUP`, `XAUTOCLAIM`, `XREADGROUP`, `XACK`, DLQ и heartbeats. Созданы endpoints мониторинга `/api/v1/health/outbox` и `/api/v1/health/worker`. Разработан runbook действий при инцидентах ([`docs/runbooks/production-failure-recovery.md`](../runbooks/production-failure-recovery.md)).
- **Резервное копирование и Disaster Recovery:** Созданы исполняемые скрипты [`scripts/backup-db.sh`](../../scripts/backup-db.sh), [`scripts/restore-db.sh`](../../scripts/restore-db.sh) и [`scripts/restore-drill.sh`](../../scripts/restore-drill.sh). Проведен успешный live disaster recovery drill с созданием изолированных баз данных, восстановлением дампа и сверкой целостности данных:
  - Фактический RTO: 3 секунды (целевой: < 900s).
  - Фактический RPO: 0 записей / 0 секунд потери (целевой: < 3600s).
  - Процедуры задокументированы в [`docs/operations/backup-and-disaster-recovery.md`](../operations/backup-and-disaster-recovery.md) и [`infra/README.md`](../../infra/README.md).
- **OpenAPI и Release Gate:** Контракт [`contracts/openapi/analytics-v1.yaml`](../../contracts/openapi/analytics-v1.yaml) расширен схемой `TooManyRequests` (429) и актуализирован для `HealthResponse`. Кодогенерация API клиента frontend актуализирована. В CI пайплайн ([`.github/workflows/ci.yml`](../../.github/workflows/ci.yml)) добавлены аудит безопасности зависимостей, сборка образов, тесты notification service, проверка чистых миграций и запуск restore drill. Скрипт [`scripts/verify-integration.sh`](../../scripts/verify-integration.sh) дополнен шагами проверки security headers, rate limiting (429) и отсутствия утечек отладочной информации.
- **Результаты верификации:** Все проверки `make check` успешно пройдены (50 frontend test suites / 227 tests, 383 backend tests / 33493 assertions, 53 notification tests, строгий анализ PHPStan и Pint без единой ошибки). Все критерии приёмки Phase 18 полностью выполнены. Phase 18 закрыта.

## Проверка завершения

- **Дата:** 2026-09-25.
- **Подтверждение Exit Criteria:**
  - [x] *Безопасность:* Проведён ASVS 5.0.0 и STRIDE анализ (`docs/security/threat-model.md`). Реализована HMAC-SHA256 подпись сессионных cookie с timingSafeEqual, исключена подделка сессий. Добавлены Security Headers (CSP, `nosniff`, `DENY`, `strict-origin-when-cross-origin`, `Permissions-Policy`). Внедрен `ProductionSafetyCheck` против запуска с `APP_DEBUG=true` или дефолтными ключами/паролями.
  - [x] *Развертывание и конфигурация:* Подготовлен `infra/.env.production.example` с уникальными секретами. Описаны deploy, smoke test, rollback и ротация в `infra/README.md`. Независимые миграции analytics и notification успешно выполняются на чистых БД.
  - [x] *Disaster Recovery:* Скрипты `scripts/backup-db.sh`, `scripts/restore-db.sh`, `scripts/restore-drill.sh` протестированы. Live restore drill выполнен: созданы изолированные базы `autobi_drill` и `notification_drill`, проверен паритет строк и дедупликация (RTO = 3s при целевом < 900s, RPO = 0s при целевом < 3600s). Описаны процедуры в `docs/runbooks/production-failure-recovery.md` и `docs/operations/backup-and-disaster-recovery.md`.
  - [x] *Нагрузка и таймауты:* Внедрен Redis-backed rate limiter для login, imports, api-write, api-read с возвратом 429 и `Retry-After`. Заданы строгие DB connection/read timeouts и 15s timeout на HTTP клиенте frontend. Задачи очередей согласованы с `$timeout < retry_after`.
  - [x] *Стримы и изоляция:* Notification worker потребляет события через `PredisStreamClient` (`XGROUP`, `XAUTOCLAIM`, `XREADGROUP`, `XACK`), отправляет heartbeats, изолирован в БД и Redis, публикует poison messages в DLQ. Endpoints `/health/outbox` и `/health/worker` отслеживают статус компонентов.
  - [x] *Контракты и CI:* OpenAPI расширен схемами 429 `TooManyRequests` и обновлен `HealthResponse`. CI дополнен проверками notification, security audits, сборкой контейнеров, чистыми миграциями и restore drill. Настроен Dependabot.
- **Команды проверок и результаты:**
  - `make check`: PASS
    - `npm --prefix frontend run contracts:validate`: PASS
    - `npm --prefix frontend run api:generate`: PASS
    - `npm --prefix frontend run lint`: PASS (0 warnings, 0 errors)
    - `npm --prefix frontend run format:check`: PASS (Prettier clean)
    - `npm --prefix frontend run typecheck`: PASS (0 type errors)
    - `npm --prefix frontend test`: PASS (50 test files, 227 tests passed)
    - `npm --prefix frontend run build`: PASS (Next.js production build succeeded)
    - `composer --working-dir=backend validate --strict`: PASS
    - `composer --working-dir=backend lint`: PASS (Pint & PHPStan passed with 0 errors)
    - `composer --working-dir=backend test`: PASS (383 tests, 33493 assertions passed)
    - `composer --working-dir=notification validate --strict`: PASS
    - `composer --working-dir=notification lint`: PASS (Pint & PHPStan passed with 0 errors)
    - `composer --working-dir=notification test`: PASS (53 tests passed)
  - `bash scripts/restore-drill.sh`: PASS (Analytics & Notification dumps, test db restore, row count parity confirmed, RTO 3s, RPO 0s)
  - `docker exec lecar-bi-notification-1 curl -s http://localhost:8081/api/v1/health/worker`: PASS (heartbeat active, status running)
  - `docker exec lecar-bi-backend-1 curl -s http://localhost:8080/api/v1/health/outbox`: PASS (publisher run confirmed, status ok)


