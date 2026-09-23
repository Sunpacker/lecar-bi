# Phase 17 — Observability

## Кратко

Сделать путь одного пользовательского действия диагностируемым через все deployable-процессы AutoBI:

`browser → Next.js → analytics HTTP → Laravel queue/outbox → Redis Stream → notification consumer`

Phase 17 вводит общий telemetry contract, JSON logs, отдельные request/correlation identifiers, W3C Trace Context, health/readiness probes, Prometheus-compatible metrics и отключаемый Sentry error tracking. Полный distributed tracing backend и отдельный стек Grafana/Loki/Tempo не входят в этап: система подготавливает совместимый trace context и экспортируемые сигналы без преждевременного добавления observability-платформы.

Phase 17 начинается только после закрытия Phase 12–16. Продуктовые функции, изменение бизнес-правил, новый message broker и пользовательский monitoring UI не входят в scope.

## Цель, scope и критерии готовности

### Результат

- Любой входящий HTTP request получает новый `request_id` и стабильный для всей цепочки `correlation_id`; оба идентификатора возвращаются в response headers и присутствуют в structured logs.
- Next.js передаёт correlation и W3C `traceparent` в analytics; analytics сохраняет context при постановке Laravel jobs и публикации integration events; notification восстанавливает context при чтении Redis Stream.
- Web, analytics и notification пишут JSON Lines в stdout/stderr с единым обязательным набором полей и без credentials, cookies, request bodies, event payloads и полных exception dumps.
- У HTTP-процессов раздельные liveness, readiness и агрегированный operational status; worker-процессы имеют собственный heartbeat/health probe.
- Web, analytics и notification отдают защищённые Prometheus-compatible metrics с HTTP, queue, outbox, import и integration-consumer сигналами без high-cardinality labels.
- Необработанные ошибки HTTP, jobs и consumers попадают в Sentry при наличии DSN; без DSN сервисы работают штатно через no-op adapter.
- По одному `correlation_id` можно восстановить сквозной сценарий в логах и связать его с error event; `traceparent` готов к последующему подключению OpenTelemetry Collector без смены межсервисных контрактов.
- Integration checkpoint автоматически проверяет propagation, health degradation, metrics, error-reporting seam и отсутствие sensitive data.

### Разрешённый write scope

- `frontend/**`
- `backend/**`
- `notification/**`
- `contracts/openapi/**`
- `infra/**`
- `scripts/**`
- `.github/workflows/**`
- `Makefile`
- `README.md`
- `docs/architecture/{01-system-architecture,02-monorepo-and-services,08-events-outbox-async,09-infrastructure-deployment-observability,10-testing-and-quality,12-architecture-decisions}.md`
- `docs/runbooks/**`
- `docs/roadmap/{17-observability,ROADMAP}.md` — только на финальном checkpoint

### Запрещённый scope

- Изменение Domain Layer ради logging, metrics или error tracking.
- Breaking change HTTP API или опубликованного `alert.triggered.v1` envelope/payload.
- Передача `workspace_id`, `user_id`, `event_id`, `request_id`, `correlation_id`, URL, exception message или иных unbounded values в metric labels.
- Логирование passwords, session cookies, authorization headers, DSN, request/import bodies, raw event payloads и полных stack traces в application logs.
- Установка Prometheus, Grafana, Loki, Tempo/Jaeger или отдельного log collector в production orchestration.
- Пользовательский observability dashboard, alert rules инфраструктурного мониторинга и on-call интеграции.
- Изменение caching semantics Phase 16, event delivery semantics Phase 13–14 или RBAC Phase 15.

## Обязательный preflight-gate

До реализации проверить:

- Phase 12–16 отмечены `[x]` в `docs/roadmap/ROADMAP.md`, а их integration checkpoints пройдены и задокументированы.
- Фактический состав сервисов после Phase 14 зафиксирован: `frontend`, `backend`, `notification`, их HTTP/worker/scheduler processes, PostgreSQL ownership и Redis connections.
- Зафиксированы фактические Laravel queue names, Redis Stream name/field/group, outbox statuses, import lifecycle и worker entry points. Имена из планов прошлых фаз не принимать за реализованный contract без проверки.
- `alert.triggered.v1` schema и canonical fixture проходят validation. Phase 17 не добавляет обязательные поля в immutable `v1` envelope.
- Phase 16 предоставляет performance baseline, чтобы overhead instrumentation можно было измерить до и после изменений.
- Все существующие health endpoints, Compose healthchecks и reverse-proxy routes инвентаризированы; совместимые aliases не удаляются в Phase 17.
- Перед добавлением SDK сверить актуальные installation/configuration APIs по официальной документации Next.js, Laravel, Sentry и Prometheus client. Версии фиксировать lock-файлами, не копировать команды из этого плана вслепую.

Если gate не выполнен, Phase 17 не стартует: сначала закрывается соответствующий exit criterion предыдущей фазы. Допускается скорректировать пути файлов под фактическую реализацию Phase 13–16, но не ослаблять telemetry contract и acceptance criteria.

## Архитектурные решения этапа

### Telemetry context

Использовать три разных идентификатора, не смешивая их смысл:

| Поле | Жизненный цикл | Назначение |
|---|---|---|
| `request_id` | новый на каждой HTTP/worker execution boundary | поиск одной локальной операции и возврат пользователю |
| `correlation_id` | стабилен для всей sync/async business flow | поиск связанных логов разных сервисов |
| `traceparent` | W3C Trace Context, новый parent/span на каждом hop | совместимость с будущим distributed tracing backend |

- Внешние `X-Request-ID` не переиспользовать как локальный request ID: каждый сервис генерирует собственный UUID/ULID.
- Валидный `X-Correlation-ID` принимать только в ограниченном UUID/ULID формате и длине; отсутствующий или некорректный identifier заменять новым.
- `traceparent`/`tracestate` валидировать по W3C Trace Context; некорректный input не отражать и не логировать, а начинать новый trace.
- Response возвращает `X-Request-ID`, `X-Correlation-ID` и актуальный `traceparent` только участвующему caller.
- Для jobs создавать новый execution `request_id`, сохраняя исходные `correlation_id`, `traceparent` и безопасный `causation_id`.
- Для scheduled jobs без upstream request создавать новый correlation/trace root.
- Для Redis Stream передавать optional `correlation_id`, `traceparent` и `tracestate` отдельными transport fields рядом с canonical `event` field. JSON `alert.triggered.v1` не менять; consumer обязан поддерживать отсутствие telemetry metadata и использовать `event_id` как fallback correlation seed.

### Structured logging

Каждая JSON log entry содержит:

- `timestamp`, `level`, `message`, `service`, `environment`, `release`;
- доступные `request_id`, `correlation_id`, `trace_id`, `span_id`;
- bounded operation fields: `http.method`, normalized `http.route`, `http.status_code`, `duration_ms`, `queue`, `job`, `outcome`;
- безопасные business references только когда они нужны для диагностики: `workspace_id`, `batch_id`, `event_id`, `stream_message_id`.

Правила:

- route template (`/imports/{id}`), а не raw URL с query string;
- exception class и внутренний error code допустимы, exception message и stack trace остаются в Sentry, а не в обычном JSON log;
- один completion log на operation; не писать start/success messages на каждый внутренний шаг;
- ожидаемые `4xx` validation/auth outcomes не отправлять в error tracker;
- observability adapter failure не должен ломать business request/job, но один раз за bounded interval пишет sanitized fallback log.

### Metrics

- Формат — Prometheus text exposition.
- PHP HTTP/worker processes используют Redis-backed registry с отдельным prefix/DB per service, потому что PHP workers не разделяют память.
- Next.js использует process-local registry: Prometheus scrape model агрегирует экземпляры снаружи; приложение не пытается суммировать replicas самостоятельно.
- `/metrics` защищён отдельным `OBSERVABILITY_TOKEN`, не session/RBAC пользователя. При отсутствии token production startup/readiness завершается ошибкой конфигурации; local/test может использовать явно заданный test token.
- Labels только bounded: service, normalized route, method, status class, queue, известный job type, dataset type, outcome/event type/version.
- Counts и durations не выводятся из logs и не записываются в business tables.

Минимальный metric catalog:

| Metric | Type | Labels |
|---|---|---|
| `autobi_http_requests_total` | counter | `service`, `method`, `route`, `status_class` |
| `autobi_http_request_duration_seconds` | histogram | `service`, `method`, `route` |
| `autobi_queue_jobs_total` | counter | `service`, `queue`, `job`, `outcome` |
| `autobi_queue_job_duration_seconds` | histogram | `service`, `queue`, `job` |
| `autobi_queue_depth` | gauge at scrape | `service`, `queue` |
| `autobi_queue_oldest_job_age_seconds` | gauge at scrape | `service`, `queue` |
| `autobi_import_batches_total` | counter | `dataset_type`, `outcome` |
| `autobi_import_rows_total` | counter | `dataset_type`, `outcome` |
| `autobi_import_duration_seconds` | histogram | `dataset_type`, `outcome` |
| `autobi_outbox_messages` | gauge at scrape | `status` |
| `autobi_outbox_oldest_pending_age_seconds` | gauge at scrape | none |
| `autobi_integration_events_total` | counter | `service`, `event_type`, `event_version`, `outcome` |
| `autobi_integration_consumer_pending` | gauge at scrape | `service`, `group` |
| `autobi_integration_consumer_lag` | gauge at scrape | `service`, `group` |

Не добавлять label только ради удобства единичного расследования: request/correlation identifiers принадлежат logs/traces, а не metrics.

### Health model

- `live`: текущий HTTP process отвечает; никаких сетевых dependency checks.
- `ready`: только обязательные для этого process dependencies с короткими timeouts. Возвращает `200` при готовности и `503` при недоступности.
- aggregate `health`: sanitized component statuses и latency для PostgreSQL, Redis и background process heartbeats; `degraded` допускает работающий HTTP process при сбое необязательного background component.
- Worker/scheduler/consumer containers имеют собственную CLI health command или process-local probe, проверяющую heartbeat freshness и их обязательные dependencies.
- HTTP readiness не подменяет worker health: Compose проверяет каждый process отдельно.
- Response не содержит hostnames, credentials, SQL, exception details или topology secrets.

Существующие `/api/health` и `/api/v1/health` остаются совместимыми aggregate aliases до отдельного deprecation decision. Новые canonical endpoints:

- web: `/api/health/live`, `/api/health/ready`, `/api/health`;
- analytics: `/api/v1/health/live`, `/api/v1/health/ready`, `/api/v1/health`;
- notification: `/api/v1/health/live`, `/api/v1/health/ready`, `/api/v1/health`.

### Error tracking и tracing preparation

- Sentry — optional adapter для error tracking, а не источник application logs или бизнес-метрик.
- Web и каждый PHP service имеют отдельный DSN/project config, общий `environment`, `release` и correlation tags.
- SDK disabled при пустом DSN; unit/integration tests используют fake/in-memory transport и не отправляют данные наружу.
- Scrubbing удаляет cookies, authorization headers, passwords, uploaded data, event payload и raw request body до отправки.
- Performance tracing Sentry выключен в Phase 17; sampling не настраивается двумя конкурирующими SDK.
- W3C `traceparent`/`tracestate` остаются vendor-neutral. Подключение OpenTelemetry SDK/Collector/OTLP exporter — последующее решение после появления tracing backend и sampling/retention policy.

## План реализации

### Task 1. Зафиксировать telemetry contract и ADR

**Files:**

- Modify: `docs/architecture/01-system-architecture.md`
- Modify: `docs/architecture/02-monorepo-and-services.md`
- Modify: `docs/architecture/08-events-outbox-async.md`
- Modify: `docs/architecture/09-infrastructure-deployment-observability.md`
- Modify: `docs/architecture/10-testing-and-quality.md`
- Modify: `docs/architecture/12-architecture-decisions.md`
- Create: `docs/runbooks/observability.md`

- [ ] Инвентаризировать фактические service/process boundaries после Phase 16 и перечислить telemetry hops.
- [ ] Зафиксировать следующим свободным ADR: JSON stdout logs, W3C Trace Context, Prometheus exposition, optional Sentry, отсутствие bundled monitoring stack.
- [ ] Описать canonical fields, header validation, async propagation, metric labels/cardinality rules и privacy policy.
- [ ] Зафиксировать health semantics и разницу между liveness, readiness, aggregate health и worker heartbeat.
- [ ] В runbook добавить поиск по `correlation_id`, диагностику HTTP → job → outbox → notification, проверку metrics/health и трактовку degraded states.

Acceptance criteria:

- Один документ однозначно отвечает, какой identifier создаётся/сохраняется на каждом hop.
- Архитектура не требует общего runtime package между независимыми сервисами и не проводит observability в Domain.
- Runbook позволяет расследовать сценарий без чтения исходного кода и без доступа к sensitive payload.

### Task 2. Обновить технические HTTP-контракты

**Files:**

- Modify: `contracts/openapi/analytics-v1.yaml`
- Generated: `frontend/src/shared/api/generated/schema.ts`
- Modify: `backend/tests/Feature/ApiContractTest.php`
- Create/Modify: notification technical contract/tests according to Phase 14 implementation

- [ ] Добавить schemas для `LiveHealthResponse`, `ReadinessHealthResponse`, `ServiceHealthResponse`, `HealthCheckResult` и обязательных statuses.
- [ ] Добавить analytics endpoints `/health/live`, `/health/ready`; сохранить `/health` как aggregate compatibility endpoint.
- [ ] Документировать correlation response headers для analytics endpoints через reusable OpenAPI headers.
- [ ] Metrics endpoint документировать как technical `text/plain` interface с bearer token; не генерировать для него product gateway methods.
- [ ] Согласовать одинаковую семантику health response между analytics и notification без общего runtime DTO/package.

Acceptance criteria:

- Redocly validation и generated-client diff проходят.
- Старый health client продолжает компилироваться или мигрируется атомарно вместе с совместимым alias.
- Contract tests проверяют body, status codes и headers, а не только наличие paths.

### Task 3. Реализовать request/correlation context в Next.js

**Files:**

- Rename/Modify: `frontend/middleware.ts` → `frontend/proxy.ts`
- Create: `frontend/instrumentation.ts`
- Create: `frontend/src/shared/observability/telemetry-context.ts`
- Create: `frontend/src/shared/observability/structured-logger.ts`
- Create: `frontend/src/shared/observability/http-metrics.ts`
- Modify: `frontend/src/shared/api/analytics-client.ts`
- Modify: server-side gateways/route handlers that bypass the shared client, if found in preflight
- Test: `frontend/src/shared/observability/*.test.ts`
- Test: `frontend/proxy.test.ts`

- [ ] Мигрировать deprecated Next.js 16 middleware convention на `proxy.ts`, сохранив auth behavior/matcher и добавив validated correlation/trace headers в forwarded request.
- [ ] Использовать Node.js runtime для telemetry-dependent Route Handlers; Edge runtime не вводить.
- [ ] Создавать локальный request context без module-global mutable state, чтобы параллельные SSR requests не смешивали identifiers.
- [ ] Подключить middleware openapi-fetch в единственной `analyticsClient` boundary: outgoing request получает correlation и trace headers из текущего server context либо новый root context для browser call.
- [ ] Возвращать identifiers в Route Handler responses и писать один JSON completion/error log с normalized route.
- [ ] Добавить `instrumentation.ts` только для server initialization/uncaught request error hook; не помещать business fetching в instrumentation hook.

Acceptance criteria:

- Два параллельных requests не обмениваются context.
- SSR/Route Handler request и вызванный analytics request имеют общий `correlation_id`, разные `request_id` и валидную trace chain.
- Auth redirects, static assets и public health routes сохраняют текущее поведение.
- Next.js build и tests проходят без Edge-incompatible dependency errors.

### Task 4. Добавить web health и metrics endpoints

**Files:**

- Modify: `frontend/app/api/health/route.ts`
- Create: `frontend/app/api/health/live/route.ts`
- Create: `frontend/app/api/health/ready/route.ts`
- Create: `frontend/app/api/metrics/route.ts`
- Modify/Create: `frontend/src/shared/config/env.ts`
- Modify: `frontend/package.json`, `frontend/package-lock.json`
- Test: `frontend/app/api/health/**/*.test.ts`
- Test: `frontend/app/api/metrics/route.test.ts`

- [ ] Live probe проверяет только web process; ready probe валидирует обязательную config и analytics dependency с жёстким timeout.
- [ ] Aggregate health возвращает web и analytics statuses, но не раскрывает downstream URL/error.
- [ ] Добавить process-local Prometheus registry и HTTP metrics для web routes; при нескольких replicas метрики не агрегировать внутри приложения.
- [ ] Защитить metrics endpoint constant-time token comparison и вернуть `401/503` для invalid/missing production config.
- [ ] Проверить compatibility старого `/api/health` и обновить system-health gateway/UI только если новый response требует mapping.

Acceptance criteria:

- Падение analytics оставляет `/live` успешным, делает `/ready` неготовым и aggregate health — degraded/unavailable согласно contract.
- Metrics имеют корректный content type, HELP/TYPE lines и не содержат dynamic identifiers.
- Health/metrics routes не требуют пользовательской session и не попадают в auth redirect loop.

### Task 5. Реализовать analytics HTTP context, JSON logs и health

**Files:**

- Create: `backend/app/Shared/Infrastructure/Observability/TelemetryContext.php`
- Create: `backend/app/Shared/Infrastructure/Observability/TraceContext.php`
- Create: `backend/app/Shared/Infrastructure/Observability/HttpTelemetryMiddleware.php`
- Create: `backend/app/Shared/Infrastructure/Observability/JsonLogContextProcessor.php`
- Create: `backend/app/Shared/Infrastructure/Health/**`
- Create: `backend/app/Shared/Presentation/HealthController.php`
- Modify: `backend/bootstrap/app.php`
- Modify: `backend/config/logging.php`
- Create: `backend/config/observability.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Observability/HttpTelemetryTest.php`
- Test: `backend/tests/Feature/Observability/HealthEndpointTest.php`
- Test: `backend/tests/Unit/Shared/Observability/TraceContextTest.php`

- [ ] Валидировать/generate context в global API middleware, установить scoped Monolog context и гарантированно очистить его после request.
- [ ] Перевести stderr channel на JSON formatter; сохранить log levels и fail-safe stderr fallback.
- [ ] Писать completion log после response с route template, status и duration; health/metrics success logs либо семплировать, либо исключить для снижения шума.
- [ ] Реализовать live, ready и aggregate health через маленькие independent checks для PostgreSQL, Redis и background heartbeats с bounded timeouts.
- [ ] Не использовать Eloquent models, Domain services и product repositories для технических checks.

Acceptance criteria:

- Incoming correlation отражается в response и downstream context только после validation.
- Sequential и concurrent requests не сохраняют чужой Monolog context.
- Недоступный PostgreSQL/Redis даёт deterministic readiness status без exception details.
- Existing auth/workspace middleware получает неизменённый request и проходит regression tests.

### Task 6. Инструментировать Laravel queues, imports и outbox

**Files:**

- Create: `backend/app/Shared/Application/Observability/TelemetryContextCarrier.php`
- Create: `backend/app/Shared/Infrastructure/Observability/QueueTelemetrySubscriber.php`
- Create: `backend/app/Shared/Infrastructure/Observability/WorkerHeartbeat.php`
- Create: `backend/app/Shared/Infrastructure/Observability/PrometheusRegistry.php`
- Create: `backend/app/Shared/Presentation/MetricsController.php`
- Modify: `backend/app/Providers/AppServiceProvider.php` or dedicated provider
- Modify: queue dispatch boundaries/jobs created by Data Ingestion, Alerting and Outbox phases
- Modify: outbox Redis Stream transport from Phase 13
- Create: `backend/app/Modules/DataIngestion/Application/Contracts/ImportTelemetryInterface.php`
- Create: `backend/app/Modules/DataIngestion/Infrastructure/Observability/PrometheusImportTelemetry.php`
- Modify: `backend/app/Modules/DataIngestion/Application/Commands/ProcessImportBatchHandler.php`
- Modify: `backend/composer.json`, `backend/composer.lock`
- Test: `backend/tests/Feature/Observability/QueueTelemetryTest.php`
- Test: `backend/tests/Feature/Observability/ImportMetricsTest.php`
- Test: `backend/tests/Feature/Observability/OutboxMetricsTest.php`
- Test: `backend/tests/Feature/Observability/MetricsEndpointTest.php`

- [ ] Сериализовать immutable context carrier в job payload; job middleware создаёт новый execution request ID, восстанавливает correlation/trace context и очищает его в `finally`.
- [ ] Центрально слушать queue lifecycle events для duration/outcome counters и worker heartbeat, не копировать logging boilerplate во все jobs.
- [ ] Явно передавать context для dispatches, происходящих после HTTP response или из scheduled command; не полагаться на process-global state long-running worker.
- [ ] Добавить узкий `ImportTelemetryInterface`: batches/rows/duration фиксируются после устойчивого state transition; telemetry error не меняет import result.
- [ ] Собирать queue depth, oldest queued job age, outbox counts и oldest pending age на scrape через bounded infrastructure collectors.
- [ ] При `XADD` добавлять optional telemetry transport fields, не меняя canonical event JSON и at-least-once semantics.
- [ ] Защитить metrics endpoint и выделить Redis metric prefix/DB, не пересекающий cache, queue и integration-stream keys.

Acceptance criteria:

- HTTP-triggered import job сохраняет correlation; scheduled alert/outbox jobs получают новый валидный root context.
- Retry одного job сохраняет flow correlation и получает новый execution request ID/attempt field.
- Queue/import/outbox metric values меняются ожидаемо на success/failure/retry и не удваиваются из-за HTTP retries вне фактического lifecycle.
- Недоступность metrics registry не ломает import, queue или outbox delivery.
- Domain tests и architecture tests подтверждают отсутствие observability dependencies в Domain.

### Task 7. Инструментировать notification HTTP и Redis consumer

**Files:**

- Create/Modify: `notification/app/Shared/Infrastructure/Observability/**`
- Modify: notification health controller/routes/config from Phase 14
- Modify: notification Redis Stream consumer/router from Phase 14
- Modify: `notification/composer.json`, `notification/composer.lock`
- Test: `notification/tests/Feature/Observability/HealthEndpointTest.php`
- Test: `notification/tests/Feature/Observability/MetricsEndpointTest.php`
- Test: `notification/tests/Unit/Integration/ConsumerTelemetryTest.php`

- [ ] Реализовать те же semantic fields и health statuses локально, без импорта PHP-классов из `backend/`.
- [ ] При чтении message восстанавливать optional transport correlation/trace fields; при их отсутствии создавать context из `event_id` без отклонения валидного legacy message.
- [ ] Один consumer iteration получает новый execution request ID; logs содержат event/message identifiers, outcome, duration и attempt/pending metadata, но не payload.
- [ ] Записывать processed/duplicate/ignored/dead-letter/transient-failure counters и consumer pending/lag gauges.
- [ ] Consumer loop обновляет heartbeat даже при отсутствии messages; graceful shutdown оставляет final heartbeat/status, различимый health probe.
- [ ] Метрики и error tracking не меняют ack/no-ack matrix Phase 14.

Acceptance criteria:

- Один event виден в analytics publisher и notification consumer logs по общему correlation ID.
- Legacy event без telemetry fields продолжает обрабатываться и дедуплицироваться.
- Duplicate/redelivery не создаёт side effect и отражается отдельным bounded outcome metric.
- Consumer health отличает idle healthy worker от зависшего/остановленного процесса.

### Task 8. Подключить безопасный error tracking

**Files:**

- Modify/Create: `frontend/instrumentation.ts`, client/server Sentry config required by current SDK
- Modify: `frontend/next.config.*`
- Modify: `frontend/package.json`, `frontend/package-lock.json`
- Create: `backend/app/Shared/Infrastructure/Observability/SentryErrorReporter.php`
- Create: `notification/app/Shared/Infrastructure/Observability/SentryErrorReporter.php`
- Modify: Laravel exception configuration and service providers
- Modify: service `.env.example` files
- Test: error reporter unit/feature tests in each service

- [ ] Подключить official Next.js и Laravel Sentry SDKs только после сверки current-version docs и framework compatibility.
- [ ] Инициализировать adapter только при наличии DSN; disabled/no-op mode является штатным и покрывается tests.
- [ ] Добавить `service`, `environment`, `release`, `correlation_id`, `request_id`, safe workspace/event references; не устанавливать email, cookies или payload as context.
- [ ] Настроить `beforeSend`/equivalent scrubbing и ignore rules для ожидаемых validation/auth/not-found outcomes.
- [ ] Capture unhandled HTTP exceptions, failed queue jobs и permanent notification consumer failures ровно один раз на execution boundary.
- [ ] Не включать performance tracing/session replay и не публиковать source maps без отдельного CI token/permission decision.

Acceptance criteria:

- Fake transport получает sanitized error event с correlation ID и release.
- Expected 4xx не создают error events; unexpected 5xx/job failure создают один event.
- В disabled mode отсутствуют network calls, startup warnings и изменение response/job semantics.
- Tests подтверждают удаление authorization/cookie/password/body/event payload fields.

### Task 9. Обновить orchestration, configuration и CI

**Files:**

- Modify: `infra/docker-compose.yml`
- Modify: `infra/docker-compose.dev.yml`
- Modify: `infra/docker-compose.vps.yml`
- Modify: `infra/.env.example`
- Modify: `infra/README.md`
- Modify: service Dockerfiles/entrypoints when worker health commands require it
- Modify: `Makefile`
- Modify: `.github/workflows/ci.yml`
- Modify: `README.md`

- [ ] Добавить non-secret `SERVICE_NAME`, `APP_ENV`, `RELEASE_SHA`, log/metric config и optional Sentry DSNs; реальные secrets не помещать в example/Compose defaults.
- [ ] Передавать один release identifier всем service images в пределах deployment.
- [ ] Перевести HTTP Compose healthchecks на canonical `/ready`, оставить liveness для orchestrator restart semantics.
- [ ] Добавить отдельные healthchecks для analytics queue/scheduler/outbox и notification consumer containers по их heartbeat/CLI probe.
- [ ] Не делать frontend/backend readiness зависимой от notification availability.
- [ ] Добавить `check-observability` с contract/static/tests и включить его в aggregate `make check` без внешней Sentry/Prometheus сети.
- [ ] CI проверяет production builds с error tracking disabled и test-only fake transports.

Acceptance criteria:

- `docker compose config` проходит для local и VPS overlays без missing optional secrets.
- Каждый process имеет корректный healthcheck, а stop одного worker отражается только в его health/aggregate status.
- Все service logs остаются в stdout/stderr и пригодны для `docker compose logs --no-log-prefix | jq`.
- Один release value виден в logs и fake error events всех сервисов.

### Task 10. Провести end-to-end observability verification

**Files:**

- Modify: `scripts/verify-integration.sh`
- Create: `scripts/verify-observability.sh`
- Create: fixtures/helpers только внутри соответствующих test directories

- [ ] Поднять clean stack после миграций Phase 12–16 и дождаться health всех HTTP/worker processes.
- [ ] Отправить request с заранее заданным валидным correlation ID через web к analytics, запустить import или alert/outbox flow и дождаться notification consumer.
- [ ] Собрать container logs и доказать наличие одного correlation ID в web, analytics HTTP, queue/outbox и notification entries при разных local request IDs.
- [ ] Отправить malformed/oversized identifiers и доказать безопасную регенерацию без reflection в headers/logs.
- [ ] Остановить PostgreSQL, Redis и отдельный worker по одному; проверить live/ready/aggregate/worker semantics и восстановление после запуска.
- [ ] Scrape metrics с valid/invalid token; проверить counters/histograms/gauges, content type и отсутствие forbidden labels/values.
- [ ] Создать controlled unexpected exception через test-only route/job/consumer seam, проверить sanitized fake error event и удалить/disable seam outside test environment.
- [ ] Повторить Phase 13–14 failure scenarios: Redis redelivery, duplicate event, stale pending claim и consumer outage; observability не меняет delivery/ack semantics.
- [ ] Сравнить HTTP/import throughput и latency с Phase 16 baseline; задокументировать overhead и устранить регрессии сверх согласованного порога.

Acceptance criteria:

- Скрипт автоматически завершается с ошибкой при потере correlation hop, неверном health status, missing metric или утечке forbidden field.
- Сквозной request находится по одному correlation ID без ручного сопоставления timestamps.
- Dependency/worker outage диагностируется однозначно и не вызывает restart loop живого процесса.
- Instrumentation overhead измерен; correctness и Phase 16 performance guarantees не ухудшены.

### Task 11. Integration checkpoint и закрытие Phase 17

**Files:**

- Modify: `docs/roadmap/17-observability.md`
- Modify: `docs/roadmap/ROADMAP.md`

- [ ] Выполнить фактические проверки всех существующих сервисов, минимум:

```bash
make check
docker compose --env-file infra/.env.example -f infra/docker-compose.yml config
docker compose --env-file infra/.env.example -f infra/docker-compose.yml build
make integration
scripts/verify-observability.sh
```

- [ ] Отдельно выполнить service-local observability tests и architecture tests для frontend, analytics и notification.
- [ ] Проверить diff на payload/credential logging, high-cardinality labels, Domain coupling, process-global context leaks, broken health compatibility и accidental tracing enablement.
- [ ] Записать в Phase 17 команды, результаты, measured overhead, known limitations и operational runbook link.
- [ ] Подтвердить каждый exit criterion Phase 17: cross-service request visibility, job correlation context, process/dependency health separation, metrics и error tracking.
- [ ] Только после успешного checkpoint отметить Phase 17 `[x]` в `docs/roadmap/ROADMAP.md`.

## Порядок исполнения и безопасный parallelism

1. Task 1–2 выполняются последовательно: telemetry и HTTP contracts замораживаются до изменения сервисов.
2. После frozen contract Tasks 3–4 (frontend), 5–6 (analytics) и 7 (notification) можно выполнять параллельно с непересекающимися write scopes.
3. Task 8 выполняется после появления service-local context/error boundaries, чтобы SDK не определял архитектуру.
4. Task 9 собирает service results и владеет высококонфликтными Compose, root Makefile и CI files.
5. Tasks 10–11 выполняет интегратор после merge всех service slices.

Не назначать двум исполнителям одновременно `infra/docker-compose*`, `contracts/openapi/**`, root `Makefile`, CI или один service provider. Handoff каждого service slice должен перечислять context headers, log fields, metric names, health semantics, проверки и оставшиеся риски.

## Матрица проверок

| Требование | Проверка |
|---|---|
| Request прослеживается между frontend/backend logs | HTTP propagation contract + E2E log correlation |
| Async jobs сохраняют context | queue serialization/retry tests + import/outbox E2E |
| Notification связана с producer | Redis transport metadata test + consumer E2E |
| Process и dependencies различимы | live/ready/aggregate tests + controlled outages |
| Worker state различим | heartbeat freshness, idle/stopped/recovered scenarios |
| Structured logs machine-readable | JSON schema/assertions и `jq` smoke check |
| Sensitive data не утекают | forbidden-field fixtures для logs и Sentry transport |
| Metrics полезны и bounded | metric catalog contract + label-cardinality assertions |
| Error tracking optional | enabled fake transport + disabled no-network tests |
| Tracing vendor-neutral | W3C parser/propagation tests, Sentry tracing disabled |
| Business semantics неизменны | Phase 13–16 regression/integration suite |

## Риски и меры

- **Context leak в long-running processes.** Использовать scoped context и обязательный cleanup после каждого request/job/message; отдельные concurrency/regression tests.
- **Breaking event contract.** Telemetry остаётся transport metadata; immutable `alert.triggered.v1` JSON не меняется, consumer принимает metadata как optional.
- **Metrics cardinality explosion.** Разрешить только catalog labels и normalized route/job names; CI запрещает identifiers и arbitrary strings в labels.
- **PHP metrics теряются между workers.** Использовать Redis-backed registry с service-specific namespace, не process memory.
- **Observability ломает business flow при сбое Redis/Sentry.** Metrics/error adapters fail-open с rate-limited fallback log; health честно показывает degradation.
- **Health checks создают restart cascade.** Liveness не проверяет сеть; readiness ограничена direct dependencies; worker health отделён от HTTP process.
- **Двойное tracing и sampling.** В Phase 17 только W3C propagation; Sentry performance tracing и OpenTelemetry exporter выключены.
- **PII/secrets в telemetry.** Allowlist fields, centralized scrubbing и automated forbidden-field tests вместо попытки фильтровать произвольные payloads после записи.
- **Инструментация искажает Phase 16 baseline.** До/после измерения обязательны; expensive gauges вычисляются при scrape с timeouts и без full-table scans.
- **Scope creep до monitoring platform.** Prometheus/Grafana/Loki/Tempo deployment, dashboards и alert rules откладываются до явных SLO/retention/on-call требований.

## Допущения

- Phase 13 реализует outbox publisher и Redis Stream transport, Phase 14 — независимый notification service/consumer, Phase 15 — RBAC, Phase 16 — baseline и caching semantics.
- Redis уже является обязательной technical dependency; отдельный metrics namespace не создаёт новую инфраструктурную технологию.
- Sentry projects/DSNs могут отсутствовать в local/CI. Реальная внешняя отправка требует отдельного provisioning и secrets, но отсутствие credentials не блокирует локальную проверку через fake transport.
- Reverse proxy позволяет маршрутизировать health endpoints, а metrics endpoints могут быть ограничены internal network/token без пользовательской session.
- Existing user changes in unfinished Phase 12–13 remain untouched while this plan is created; execution must re-run preflight against the then-current repository state.

## Справочные стандарты

- W3C Trace Context: `https://www.w3.org/TR/trace-context/`
- Prometheus exposition format: `https://prometheus.io/docs/instrumenting/exposition_formats/`
- Prometheus instrumentation practices: `https://prometheus.io/docs/practices/instrumentation/`
- Next.js App Router/self-hosting docs: `https://nextjs.org/docs/app`
- Sentry Next.js and Laravel guides: `https://docs.sentry.io/platforms/javascript/guides/nextjs/`, `https://docs.sentry.io/platforms/php/guides/laravel/`
