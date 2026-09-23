# Phase 16 — Performance and Caching

## Кратко

Подготовить analytics-service к большему объёму данных через воспроизводимые измерения, а не через предположения:

`large dataset → baseline → EXPLAIN (ANALYZE, BUFFERS) → точечная оптимизация → selective cache → повторные измерения`

Phase 16 не меняет бизнес-смысл метрик и публичный HTTP API. PostgreSQL остаётся источником истины, Redis хранит только воспроизводимые результаты чтения, а любой кэш имеет явные scope, TTL, версию данных и путь безопасного обхода при недоступности Redis.

К реализации Phase 16 можно приступать только после закрытия Phase 12–15. Создание этого плана не запускает фазу и не меняет статус roadmap.

## Цель, scope и критерии готовности

### Результат

- Для репрезентативного большого набора данных зафиксированы размеры таблиц, окружение, сценарии, p50/p95, количество SQL-запросов и планы выполнения дорогих запросов.
- Каждая добавленная оптимизация связана с измеренным bottleneck и имеет сравнение before/after.
- Тяжёлые агрегации остаются в PostgreSQL; PHP не загружает полный аналитический dataset ради сортировки или пагинации.
- Кэшируются только повторяемые низкокардинальные агрегаты и filter options. Поисковые и пагинированные списки по умолчанию не кэшируются.
- Cache key изолирован по workspace, dataset, версии схемы результата, версии данных, имени операции и каноническим критериям.
- Успешное изменение аналитических фактов делает прежнюю версию кэша недоступной; TTL удаляет недостижимые старые записи.
- Недоступность Redis ухудшает только latency: запрос выполняется через PostgreSQL и не возвращает ошибку из-за кэша.
- Cold-cache, warm-cache и cache-disabled ответы семантически совпадают, а cross-workspace isolation и RBAC из Phase 15 сохраняются.
- Документация содержит cache freshness, invalidation, fallback, performance evidence и осознанное решение по projections/materialized views.

### Разрешённый write scope при будущей реализации

- `backend/app/Modules/{SalesAnalytics,InventoryAnalytics,SupplierAnalytics}/Infrastructure/**`
- `backend/app/Modules/DataIngestion/{Application,Infrastructure}/**` — только dataset-version/invalidation hook после фактической записи проекции
- `backend/app/Shared/Infrastructure/{Cache,Performance}/**`
- `backend/app/Console/Commands/**`
- `backend/app/Providers/AppServiceProvider.php`
- `backend/config/**`
- `backend/database/migrations/**`
- `backend/database/seeders/Performance/**`
- `backend/database/seeders/DemoDataSeeder.php` — только синхронизация версии данных после seed
- `backend/tests/{Unit,Feature,Integration,Performance}/**`
- `infra/.env.example`
- `infra/docker-compose*.yml` — только явная cache-конфигурация, без нового сервиса
- `Makefile`
- `scripts/benchmark-analytics.sh`
- `scripts/verify-integration.sh`
- `docs/performance/**`
- `docs/architecture/{06-data-and-analytics,09-infrastructure-deployment-observability,10-testing-and-quality,12-architecture-decisions}.md` — только если принято устойчивое решение
- `docs/roadmap/{16-performance-caching,ROADMAP}.md` — только после финального checkpoint

### Запрещённый scope

- изменение формул KPI, ABC/XYZ, stock health и supplier reliability;
- изменение OpenAPI response schemas или ручное редактирование generated frontend client;
- постоянный cache на стороне браузера/Next.js, CDN cache и новые UI-панели производительности;
- кэширование authorization decisions, пользовательских сессий или готового HTTP-ответа до проверки workspace/RBAC;
- глобальный `Cache::flush()`, Redis `KEYS`/`SCAN` для invalidation и очистка чужих workspace;
- добавление Elasticsearch, ClickHouse, нового broker, APM или иной инфраструктуры до измеренной необходимости;
- подмена PostgreSQL Redis-данными или использование Redis как источника истины;
- оптимизация import pipeline, не требуемая для корректного cache invalidation;
- production deploy и нагрузочное тестирование production-данных.

## Обязательный preflight-gate

До реализации проверить:

- Phase 12, 13, 14 и 15 отмечены `[x]` в `docs/roadmap/ROADMAP.md`, а их checkpoints пройдены.
- `make check` и `make integration` проходят до performance-изменений.
- Фактические RBAC semantics Phase 15 не меняют аналитический payload в зависимости от роли. Если меняют, capability fingerprint включается в cache key либо такой endpoint исключается из кэша.
- Зафиксированы актуальные mutation paths аналитических данных: Data Ingestion, demo/performance seeders и появившиеся к Phase 16 projection rebuild jobs.
- На отдельном performance workspace можно безопасно создавать большой dataset без удаления demo/user data.
- Redis cache использует отдельную connection/database от queue и integration-event transport.
- Текущие незавершённые пользовательские изменения сохранены; номера миграций выбираются после реально существующих файлов на момент старта фазы.

Если gate не пройден, Phase 16 не начинается: сначала закрывается соответствующая зависимость, а baseline не выдаётся за финальный результат.

## Архитектурные решения плана

### Измерения раньше оптимизаций

- Wall-clock assertions не входят в обычный CI: они нестабильны на shared runners.
- CI проверяет корректность, query count, cache semantics и выбранные plan invariants; p50/p95 измеряются в фиксированном Docker-профиле и публикуются в `docs/performance/`.
- Перед каждым замером фиксируются commit, CPU/RAM, версии PHP/PostgreSQL/Redis, container limits, row counts, cache state и параметры сценария.
- Для каждого сценария выполняются 5 warm-up и 30 измеряемых запусков. Отчёт хранит p50, p95, min/max, query count и размер ответа.
- Cold PostgreSQL означает новый backend process и очищенный PostgreSQL buffer state только когда это можно сделать в изолированном локальном окружении. Обычный сценарий отдельно маркируется как DB-warm/cache-cold или cache-warm.

### Reference dataset и performance budget

Профиль `large` создаётся только в workspace `perf-ws-1` и содержит не меньше:

- 100 000 orders;
- 300 000 order items;
- 500 000 inventory snapshots;
- 200 000 supplier deliveries;
- 10 000 products и достаточно dimension cardinality для реальных filter/selectivity сценариев.

Генератор потоковый и вставляет данные chunk-ами, не собирая весь dataset в PHP memory. Seed детерминирован; повторный запуск в том же performance workspace даёт те же business values и не затрагивает `ws-1`/`ws-2`.

Reference budgets для локального Docker-профиля фиксируются до оптимизаций:

| Класс сценария | Budget p95 | Дополнительное условие |
|---|---:|---|
| Overview/summary/filter query без Redis | ≤ 1 000 ms | без полного dataset в PHP memory |
| Paginated list и ABC/XYZ query без Redis | ≤ 1 500 ms | SQL pagination либо измеренно оправданная projection |
| Warm cached aggregate | ≤ 200 ms | payload равен uncached результату |
| Dashboard fan-out из типового набора widgets | ≤ 2 000 ms | одинаковые агрегаты не создают повторную тяжёлую работу |

Если reference host объективно не позволяет абсолютный budget, интегратор до оптимизаций фиксирует hardware-normalized budget в baseline. После начала оптимизации budget не ослабляется без отдельного объяснения. Для подтверждённого bottleneck требуется либо достижение budget, либо не менее 30% улучшения p95 вместе со снижением rows/buffers/SQL work; соседний сценарий не должен регрессировать более чем на 10% без обоснования.

### Selective cache

Первая cache allowlist:

- sales: overview и filter options;
- inventory: summary, filter options и ABC/XYZ summary;
- suppliers: overview и filter options.

Не кэшируются по умолчанию:

- sales records;
- inventory items и ABC/XYZ items;
- supplier performance и deliveries;
- alert lists, import status, dashboard configuration и любые commands.

Причина — высокая кардинальность page/search/sort keys и малая вероятность повторного использования. Endpoint можно добавить в allowlist только после отдельного hit-rate и memory evidence.

Default TTL:

- overview/summary: 120 секунд;
- filter options: 300 секунд;
- ABC/XYZ summary: 120 секунд.

TTL является safety bound, а не основным invalidation mechanism. Основной механизм — версия dataset для пары `(workspace_id, dataset)` в PostgreSQL. Новый результат записывается под новой версией; старые Redis keys становятся недостижимыми и исчезают по TTL без массового удаления.

Cache key имеет форму:

`analytics:{schema_version}:{dataset}:{workspace_id}:{dataset_version}:{operation}:{criteria_hash}`

- `criteria_hash` строится из канонического JSON: стабильный порядок полей, явные defaults и нормализованные даты/enums;
- `schema_version` меняется при несовместимом изменении сериализованного DTO;
- `user_id` не входит в key только если Phase 15 подтверждает одинаковый аналитический payload для всех допущенных ролей внутри workspace;
- cache lookup происходит внутри read-model после пройденной authentication/authorization boundary.

### Invalidation и отказоустойчивость

- `analytics_dataset_versions` хранит монотонную версию отдельно для `sales`, `inventory` и `suppliers` в каждом workspace.
- Data Ingestion повышает версию затронутого dataset один раз после batch, если хотя бы одна аналитическая запись действительно создана или обновлена.
- Частично успешный batch также повышает версию, потому что источник данных изменился.
- Demo/performance seeder повышает версии после завершения записи соответствующих facts.
- Projection/materialized view, если она будет выбрана, сначала публикует консистентную новую projection, затем повышает dataset version.
- Failure до первой записи не меняет версию; failure после записей не должен оставлять старую версию доступной намеренно.
- Redis read/write exception обрабатывается только на cache boundary: логируется безопасный warning и выполняется исходный PostgreSQL read model. Database/domain exceptions не маскируются.
- Cache invalidation/version failure не проглатывается. Import получает явный технический failure либо retry согласно фактической транзакционной модели Phase 16; TTL остаётся последним ограничителем staleness, а не оправданием silent failure.

### Indexes и projections

- Индекс добавляется только после `EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)` на `large` dataset.
- Каждый индекс должен соответствовать конкретным workspace/date/filter/join/order predicates и иметь before/after plan evidence.
- Миграции индексов forward-safe; для больших таблиц используется совместимый с production deployment способ создания, без длительной блокировки записей.
- Существующие индексы в Phase 16 не удаляются без отдельного доказательства их бесполезности и анализа write cost.
- Materialized view или projection не является обязательным результатом. Она создаётся только если SQL rewrite и индексы не достигают budget либо повторный расчёт действительно доминирует.
- Если projection не нужна, `docs/performance/phase-16-optimization-report.md` явно фиксирует измеренное решение «не добавлять».

## План реализации

### Task 1. Зафиксировать performance contract и матрицу сценариев

**Files:**

- Create: `docs/performance/README.md`
- Create: `docs/performance/phase-16-baseline.md`
- Create: `docs/performance/phase-16-cache-semantics.md`
- Create: `docs/performance/phase-16-optimization-report.md`

- [ ] Описать reference environment, dataset profile, warm-up/runs, cache states и способ сбора row counts.
- [ ] Зафиксировать budgets из этого плана и запрет wall-clock assertions в обычном CI.
- [ ] Создать scenario matrix для default и selective filters:
  - sales overview, filters и records;
  - inventory summary, items, filters, ABC/XYZ summary/items;
  - supplier overview, performance, deliveries и filters;
  - типовой dashboard fan-out с повторяющимися aggregate requests.
- [ ] Для каждого сценария указать workspace, criteria, ожидаемую selectivity, допустимый cache state и correctness oracle.
- [ ] Зафиксировать формат before/after evidence: latency, query count, rows, shared hit/read blocks, temp files, sort method и plan nodes.

Acceptance criteria:

- Один и тот же benchmark можно повторить без устных инструкций.
- Документация отличает PostgreSQL warm-up от Redis warm cache.
- Budget и correctness criteria определены до изменения SQL/indexes/cache.

### Task 2. Создать изолированный large-dataset generator и benchmark tooling

**Files:**

- Create: `backend/database/seeders/Performance/PerformanceDatasetProfile.php`
- Create: `backend/database/seeders/Performance/PerformanceDatasetGenerator.php`
- Create: `backend/database/seeders/Performance/PerformanceDatasetSeeder.php`
- Create: `backend/app/Shared/Infrastructure/Performance/BenchmarkScenario.php`
- Create: `backend/app/Shared/Infrastructure/Performance/AnalyticsBenchmarkRunner.php`
- Create: `backend/app/Shared/Infrastructure/Performance/ExplainPlanCollector.php`
- Create: `backend/app/Console/Commands/SeedPerformanceDatasetCommand.php`
- Create: `backend/app/Console/Commands/BenchmarkAnalyticsCommand.php`
- Create: `backend/tests/Unit/Performance/PerformanceDatasetGeneratorTest.php`
- Create: `backend/tests/Unit/Performance/BenchmarkScenarioTest.php`
- Create: `backend/tests/Feature/Performance/PerformanceCommandSafetyTest.php`
- Create: `scripts/benchmark-analytics.sh`
- Modify: `Makefile`

- [ ] Реализовать deterministic generator с `yield`/chunked inserts и профилями `small` и `large`.
- [ ] Разрешить seed/cleanup только для ID с префиксом `perf-` и только в `local`/`testing`; production command завершается до mutation.
- [ ] Не использовать `migrate:fresh`, truncate общих таблиц или broad delete.
- [ ] Добавить `performance:seed --profile=large --workspace=perf-ws-1 --seed=42`.
- [ ] Добавить `performance:benchmark --profile=large --runs=30 --warmup=5 --cache-state=disabled|cold|warm --format=json`.
- [ ] Добавить сбор `EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)` только для read-only сценариев с sanitized bindings.
- [ ] Не записывать планы с реальными credentials, headers или пользовательскими данными.
- [ ] Добавить Make targets `performance-seed` и `performance-benchmark`; не включать large benchmark в `make check`.

Acceptance criteria:

- Два запуска с одинаковым seed дают одинаковые row counts и business aggregates.
- Peak PHP memory не растёт пропорционально всему dataset.
- Команда отказывается затрагивать `ws-1`, `ws-2` и production environment.
- JSON output достаточно для заполнения baseline/optimization report без ручного пересчёта percentile.

### Task 3. Снять baseline и локализовать bottlenecks

**Files:**

- Modify: `docs/performance/phase-16-baseline.md`
- Create: `docs/performance/plans/phase-16-before/*.json`

- [ ] Запустить все сценарии с analytics cache disabled.
- [ ] Снять HTTP latency, read-model latency и SQL query count отдельно, чтобы не путать network/framework/database costs.
- [ ] Сохранить планы только для сценариев выше budget или с подозрительным scan/sort/temp work.
- [ ] Проверить фактические проблемы, уже видимые как кандидаты, но не считать их доказанными без замера:
  - in-memory sorting/pagination supplier performance;
  - полная ABC/XYZ выборка и классификация перед pagination;
  - повторные latest-snapshot и velocity aggregations;
  - повторные sales overview queries для widgets;
  - low-selectivity indexes, не совпадающие с workspace/date/filter/order predicates.
- [ ] Ранжировать bottlenecks по user impact и total DB work, а не по одному медленному synthetic query.

Acceptance criteria:

- Для каждой дальнейшей оптимизации есть scenario ID и before artifact.
- Отдельно перечислены сценарии, уже укладывающиеся в budget и потому не требующие изменений.
- Не добавлено ни одного индекса, cache entry или projection до завершения baseline.

### Task 4. Оптимизировать измеренные SQL paths и разбить oversized read model

**Files:**

- Modify: `backend/app/Modules/SalesAnalytics/Infrastructure/Persistence/PostgresSalesAnalyticsReadModel.php`
- Modify: `backend/app/Modules/InventoryAnalytics/Infrastructure/Persistence/PostgresInventoryAnalyticsReadModel.php`
- Modify: `backend/app/Modules/SupplierAnalytics/Infrastructure/Persistence/PostgresSupplierAnalyticsReadModel.php`
- Create when selected by evidence: `backend/app/Modules/InventoryAnalytics/Infrastructure/Persistence/Queries/**`
- Create when selected by evidence: `backend/app/Modules/SupplierAnalytics/Infrastructure/Persistence/Queries/**`
- Create: `backend/database/migrations/<next_timestamp>_add_phase_16_analytics_indexes.php`
- Create: `backend/tests/Feature/Performance/AnalyticsQueryPlanTest.php`
- Modify: existing analytics feature/unit tests affected by internal refactoring
- Modify: `docs/performance/phase-16-optimization-report.md`
- Create: `docs/performance/plans/phase-16-after-sql/*.json`

- [ ] Сохранить Application interfaces и DTOs; оптимизация остаётся внутри Infrastructure.
- [ ] Разделить `PostgresInventoryAnalyticsReadModel.php`, который уже превышает 700 строк, на небольшие query collaborators по use case; facade продолжает реализовывать прежний interface.
- [ ] Перенести sorting/pagination/count в SQL там, где baseline подтверждает загрузку полного набора в PHP.
- [ ] Устранить повторную тяжёлую агрегацию внутри одного use case через reusable CTE/subquery, не через скрытый глобальный state.
- [ ] Добавить только те composite/covering/partial indexes, для которых before plan показывает проблему и after plan подтверждает пользу.
- [ ] Проверить workspace первым ключом там, где это соответствует predicate, и не ухудшить cross-workspace selectivity.
- [ ] Выполнить `ANALYZE` performance tables перед повторным замером.
- [ ] Сравнить payload и rounding с pre-optimization fixtures; бизнес-формулы не переносить и не менять незаметно.

Acceptance criteria:

- Public interfaces и OpenAPI не изменились.
- PHP не сортирует и не пагинирует полный supplier/product dataset, если именно это было подтверждённым bottleneck.
- Каждый новый индекс имеет scenario ID, before/after plan и rollback.
- `PostgresInventoryAnalyticsReadModel.php` больше не является монолитом 700+ строк.
- Все выбранные SQL scenarios достигают budget либо документированного improvement gate.

### Task 5. Принять evidence-based решение по projection/materialized view

**Files when projection is justified:**

- Create: `backend/database/migrations/<next_timestamp>_create_<measured_projection>.php`
- Create: `backend/app/Modules/<Context>/Infrastructure/Projection/**`
- Create: `backend/app/Modules/<Context>/Infrastructure/Jobs/**`
- Create: `backend/tests/Feature/Performance/AnalyticsProjectionTest.php`
- Modify: `backend/app/Modules/DataIngestion/Application/Commands/ProcessImportBatchHandler.php`
- Modify: `backend/app/Providers/AppServiceProvider.php`
- Modify: `docs/architecture/06-data-and-analytics.md`
- Modify: `docs/architecture/12-architecture-decisions.md` only for a durable architectural decision
- Modify: `docs/performance/phase-16-optimization-report.md`

- [ ] Сначала повторить benchmark после SQL/index work.
- [ ] Если budgets достигнуты, не создавать projection и записать это решение с evidence.
- [ ] Если bottleneck сохраняется, выбрать минимальную projection только для измеренного use case, а не универсальную aggregate table.
- [ ] Зафиксировать owner, source tables, refresh trigger, idempotency, source watermark/version и failure behavior.
- [ ] Публиковать новую projection атомарно; consumer не должен видеть наполовину перестроенный набор.
- [ ] Не повышать dataset cache version до успешной публикации projection.
- [ ] Добавить parity test: projection и исходный запрос дают одинаковый результат на fixed fixtures.
- [ ] Измерить refresh duration и write amplification вместе с read improvement.

Acceptance criteria:

- В репозитории есть явное измеренное решение: projection реализована либо обоснованно отклонена.
- Реализованная projection имеет deterministic rebuild, freshness contract и parity tests.
- Domain Layer не зависит от projection, PostgreSQL или Laravel jobs.

### Task 6. Реализовать versioned selective cache на read-model boundary

**Files:**

- Create: `backend/config/analytics.php`
- Create: `backend/database/migrations/<next_timestamp>_create_analytics_dataset_versions_table.php`
- Create: `backend/app/Shared/Infrastructure/Cache/AnalyticsCacheKey.php`
- Create: `backend/app/Shared/Infrastructure/Cache/CanonicalCriteria.php`
- Create: `backend/app/Shared/Infrastructure/Cache/AnalyticsDatasetVersionStore.php`
- Create: `backend/app/Shared/Infrastructure/Cache/AnalyticsResultCache.php`
- Create: `backend/app/Modules/SalesAnalytics/Infrastructure/Persistence/CachedSalesAnalyticsReadModel.php`
- Create: `backend/app/Modules/InventoryAnalytics/Infrastructure/Persistence/CachedInventoryAnalyticsReadModel.php`
- Create: `backend/app/Modules/SupplierAnalytics/Infrastructure/Persistence/CachedSupplierAnalyticsReadModel.php`
- Modify: `backend/app/Providers/AppServiceProvider.php`
- Modify: `backend/app/Modules/DataIngestion/Application/Commands/ProcessImportBatchHandler.php`
- Modify: `backend/database/seeders/DemoDataSeeder.php`
- Modify: performance seeder from Task 2
- Modify: `infra/.env.example`
- Modify: `infra/docker-compose.yml`
- Modify: `infra/docker-compose.vps.yml`
- Create: `backend/tests/Unit/Shared/Infrastructure/Cache/AnalyticsCacheKeyTest.php`
- Create: `backend/tests/Unit/Shared/Infrastructure/Cache/AnalyticsResultCacheTest.php`
- Create: `backend/tests/Feature/Performance/AnalyticsCacheIntegrationTest.php`
- Create: `backend/tests/Feature/Modules/DataIngestion/AnalyticsCacheInvalidationTest.php`
- Modify: `docs/performance/phase-16-cache-semantics.md`

- [ ] Добавить version table с unique `(workspace_id, dataset)` и monotonic bigint version.
- [ ] Реализовать стабильную canonicalization criteria без зависимости от порядка полей и без `serialize()` пользовательского input.
- [ ] Обернуть только allowlist methods; остальные методы безусловно делегируют PostgreSQL read model.
- [ ] Настроить `ANALYTICS_CACHE_ENABLED`, schema version и TTLs через config/env с безопасными defaults.
- [ ] Зарегистрировать decorator composition в service container; testing может явно включать array/Redis store, а Domain/Application interfaces не меняются.
- [ ] Повышать версию затронутого dataset после фактической mutation, включая partial-success import.
- [ ] Не выполнять global eviction: смена version немедленно исключает старые keys из чтения, TTL освобождает память.
- [ ] При Redis failure выполнить delegate query и не записывать ложный cache hit.
- [ ] Логировать только dataset, workspace identifier, operation и failure class; payload, credentials и полный exception trace не включать в warning context.
- [ ] Проверить deployment cache schema: несовместимые DTO changes требуют новой `ANALYTICS_CACHE_SCHEMA_VERSION`.

Acceptance criteria:

- Повторный одинаковый aggregate request даёт cache hit и не выполняет тяжёлые SQL-запросы.
- Другой workspace, dataset version или criteria не получает чужой result.
- Sales import инвалидирует sales cache, но не inventory/supplier cache; inventory import ведёт себя симметрично.
- После partial-success import следующий read видит новую dataset version.
- Redis outage не меняет HTTP payload/status успешного PostgreSQL-запроса.
- Cache-disabled, cold и warm payloads совпадают на fixed fixtures.

### Task 7. Проверить dashboard fan-out без постоянного frontend cache

**Files:**

- Modify only if evidence requires request coalescing: `frontend/src/features/dashboard/model/widget-data-loader.ts`
- Modify only if evidence requires request coalescing: `frontend/src/features/dashboard/model/widget-data-loader.test.ts`
- Modify: `docs/performance/phase-16-optimization-report.md`

- [ ] Повторить типовой dashboard scenario после backend cache.
- [ ] Если budget достигнут, не добавлять frontend state/cache.
- [ ] Если одинаковые запросы одного render cycle всё ещё доминируют, добавить только request-scoped promise coalescing по canonical gateway request.
- [ ] Не переносить TTL/invalidation во frontend и не сохранять аналитические ответы между workspace/session.
- [ ] Проверить, что rejected promise удаляется из request-scoped map и может быть повторён.

Acceptance criteria:

- Нет второго независимого persistent cache policy во frontend.
- Любое frontend изменение подтверждено waterfall/benchmark evidence.
- Workspace switch не переиспользует response предыдущего workspace.

### Task 8. Correctness, failure и performance regression tests

**Files:**

- Modify/Create: `backend/tests/Feature/Performance/**`
- Modify/Create: `backend/tests/Integration/Performance/**`
- Modify: `scripts/verify-integration.sh`
- Modify: `docs/performance/phase-16-optimization-report.md`

- [ ] Добавить golden/parity проверки всех оптимизированных analytics responses.
- [ ] Проверить empty dataset, boundary dates, unknown filters, first/last page и deterministic sorting with tie-breaker.
- [ ] Проверить workspace isolation на cold/warm cache и одинаковых criteria.
- [ ] Проверить concurrent cold misses: correctness обязательна; stampede protection добавляется только если concurrent benchmark показывает проблему.
- [ ] Проверить cache key schema change, TTL expiry, version bump и Redis restart.
- [ ] Проверить, что query count после оптимизации не растёт с количеством returned items.
- [ ] Выполнить after benchmarks для cache-disabled, cold и warm состояний теми же сценариями и dataset.
- [ ] Зафиксировать любые trade-offs: дополнительные indexes, projection refresh cost, Redis memory estimate и write overhead.

Acceptance criteria:

- Correctness tests проходят при включённом и выключенном cache.
- Нет cross-workspace leakage и cache hit до authorization boundary.
- Все заявленные улучшения воспроизводятся теми же командами, что baseline.
- Regression или не достигнутый budget имеет конкретный blocker; он не скрывается средним значением вместо p95.

### Task 9. Integration checkpoint и закрытие Phase 16

**Files:**

- Modify: `docs/architecture/06-data-and-analytics.md`
- Modify: `docs/architecture/09-infrastructure-deployment-observability.md`
- Modify: `docs/architecture/10-testing-and-quality.md`
- Modify when a durable decision was made: `docs/architecture/12-architecture-decisions.md`
- Modify: `docs/roadmap/16-performance-caching.md`
- Modify: `docs/roadmap/ROADMAP.md`

- [ ] Проверить completeness baseline и optimization report: environment, row counts, p50/p95, plans, before/after и cache hit behavior.
- [ ] Зафиксировать cache allowlist, TTL, versioning, invalidation, fallback и максимальную freshness boundary.
- [ ] Зафиксировать принятое решение по projection/materialized view.
- [ ] Запустить unit, feature, integration, architecture и contract tests.
- [ ] Запустить полный `make check`, container builds и `make integration`.
- [ ] Повторить `performance:benchmark` на чистом reference dataset.
- [ ] Провести отдельный review cache correctness, SQL plans, migration safety, memory cardinality и RBAC/workspace isolation.
- [ ] Обновить прогресс Phase 16 и отметить `[x]` только после подтверждения каждого exit criterion.

Обязательные команды checkpoint:

```bash
composer --working-dir=backend test -- --testsuite=Unit
composer --working-dir=backend test -- --testsuite=Feature
composer --working-dir=backend test -- --filter=Performance
npm --prefix frontend test -- widget-data-loader.test.ts
make check
make build
make integration
make performance-seed
make performance-benchmark
```

Если Task 7 не изменил frontend, отдельный frontend test всё равно можно запустить как regression check, но он не считается доказательством backend performance.

## Матрица тестов

| Уровень | Что проверяет | Не проверяет |
|---|---|---|
| Unit | canonical keys, versions, TTL selection, fallback decisions, deterministic generator | реальный Redis/PostgreSQL |
| Feature | HTTP payload, authorization before cache, workspace isolation, import invalidation orchestration | стабильный wall-clock |
| Integration | реальный Redis, PostgreSQL version table, cache hit/miss, restart/failure path | production capacity |
| Query plan | выбранные indexes/scan/sort/buffers на `large` profile | бизнес-корректность ответа |
| Benchmark | p50/p95, query count, response size, dashboard fan-out | автоматический CI gate на heterogeneous runners |
| Full integration | browser/BFF → API → PostgreSQL/Redis и mutation → invalidation → fresh read | production deploy |

## Порядок исполнения и ownership

Работа выполняется последовательными gates, потому что indexes, projection и cache затрагивают одни и те же query paths:

1. Repository/performance analysis владеет Tasks 1–3 и не меняет runtime code до baseline.
2. Backend analytics owner выполняет Task 4 в `SalesAnalytics`, `InventoryAnalytics`, `SupplierAnalytics` и migrations.
3. Projection task запускается только после повторного checkpoint Task 4; решение фиксирует интегратор.
4. Cache/invalidation owner выполняет Task 6 после стабилизации SQL и projection shape.
5. Frontend Task 7 запускается только при недостигнутом dashboard budget и имеет отдельный write scope.
6. Независимый reviewer проверяет migrations, query plans, cache isolation/failure semantics и evidence до закрытия roadmap.

Нельзя параллельно менять один Postgres read model, общую migration или `AppServiceProvider.php`. Общий контракт и cache key format фиксируются до параллельной работы и не меняются до handoff.

## Риски и способы снижения

- **Синтетический dataset не похож на production.** Хранить cardinality/selectivity в профиле и явно маркировать выводы как reference, не как production capacity guarantee.
- **Wall-clock flakiness.** Не включать абсолютные latency assertions в обычный CI; сравнивать одинаковое окружение и хранить планы/query counts.
- **Cache leakage между workspace.** Workspace обязателен в key и проверяется integration tests на cold/warm paths.
- **Stale data после import.** Dataset version меняется после фактической mutation; TTL лишь ограничивает последствия аварийного path.
- **Redis outage ломает аналитику.** Cache boundary fail-open к PostgreSQL; ошибки исходного read model не маскируются.
- **Высокая cardinality съедает Redis memory.** Allowlist исключает search/page/sort endpoints; отчёт оценивает key count и serialized size.
- **Indexes ускоряют reads, но замедляют imports.** Измерять write overhead и не добавлять дублирующие indexes.
- **Materialized view создаёт второй источник истины.** Projection остаётся производной, имеет source version, parity test и атомарную публикацию.
- **RBAC влияет на payload.** Проверить Phase 15 до key design; при role-dependent representation включить capability fingerprint или исключить endpoint.
- **Oversized query classes затрудняют review.** Разделить Inventory read model по use cases одновременно с сохранением публичного interface.

## Definition of Done Phase 16

- Performance baseline задокументирован и воспроизводим на изолированном `large` dataset.
- Bottlenecks подтверждены timings и query plans, а не догадками.
- Все runtime-оптимизации имеют before/after evidence; уже быстрые paths не усложнены.
- Cache allowlist, TTL, key schema, dataset versioning, invalidation и Redis fallback однозначно описаны и протестированы.
- Решение по indexes и projection/materialized view измерено и задокументировано.
- Correctness, RBAC и workspace isolation не ухудшились при cache disabled/cold/warm.
- Применимые lint, static analysis, unit, feature, integration, contract, build и benchmark checks выполнены с записанными результатами.
- `docs/roadmap/16-performance-caching.md` содержит прогресс и проверку завершения.
- `[x]` в `docs/roadmap/ROADMAP.md` ставится только после полного integration checkpoint; наличие этого плана само по себе фазу не закрывает.

## Допущения

- Phase 15 сохраняет workspace-level доступ к общей аналитической representation. Если появится row/field-level authorization, cache key/scope пересматривается до реализации.
- Phase 10 остаётся единственным production mutation path для sales/inventory facts; новые paths, появившиеся в Phase 12–15, включаются в preflight inventory.
- Supplier ingestion может отсутствовать к Phase 16; тогда supplier version меняют seed/rebuild paths, а будущий ingestion обязан использовать тот же version contract.
- Redis уже входит в базовую инфраструктуру, поэтому новая runtime dependency для cache не нужна.
- Phase 17 добавит эксплуатационные metrics/observability; Phase 16 ограничивается benchmark artifacts и безопасными техническими warnings, не опережая следующий этап.
