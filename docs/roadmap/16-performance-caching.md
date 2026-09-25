# Phase 16 — Performance and Caching

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Подготовить систему к большему объёму данных.

## Работа

Query timings, expensive query analysis, indexes, selective caching, materialized views/projections, invalidation strategy, larger datasets.

Не кэшировать без freshness/invalidation strategy.

## Exit Criteria

Performance baseline задокументирован, bottlenecks измерены, optimization evidence-driven, cache semantics ясна, correctness не ухудшилась.

## Прогресс

### Что сделано
- **Task 1:** Инженерный контракт производительности, протокол нагрузочного тестирования, бюджеты latency и профили данных зафиксированы в `docs/performance/README.md` и `docs/performance/phase-16-baseline.md`.
- **Task 2:** Реализован потоковый детерминированный генератор эталонного датасета `PerformanceDatasetGenerator`, консольные команды `performance:seed` и `performance:benchmark`, строгие тесты безопасности окружений `PerformanceCommandSafetyTest`.
- **Task 3:** Сняты baseline-замеры на профиле `large` (100k заказов, 300k позиций, 500k остатков, 200k поставок, 10k товаров), собраны артефакты `EXPLAIN (ANALYZE, BUFFERS)` в `docs/performance/plans/phase-16-before/`. Выявлен и локализован основной bottleneck движка: неиндексированный `Seq Scan` с вытеснением сортировки на диск (13.6 MB temp spill) в `SALES-01` (p95 = 1 947 ms).
- **Task 4:** Добавлены покрывающие B-tree индексы (`2026_09_23_000070_add_phase_16_analytics_indexes.php`), включая `idx_foi_ws_order_covering` (`INCLUDE (total_price, gross_profit)`). Декомпозирован монолитный `PostgresInventoryAnalyticsReadModel` на query-коллабораторы (`InventorySummaryQuery`, `InventoryItemsQuery`, `AbcXyzSummaryQuery`, `InventoryFilterOptionsQuery`). Планы After-SQL переведены в `Index Only Scan` с 0 MB temp disk spill (`docs/performance/plans/phase-16-after-sql/`). Latency `SALES-01` снижена до 370 ms (в 5.2 раза быстрее), `DASH-01` — до 747 ms, `DASH-02` — до 1 161 ms.
- **Task 5:** Обоснованное решение по материализованным представлениям / проекциям: отклонено на основе замеров. Все 23 аналитических сценария уложились в бюджеты чисто средствами SQL и индексов, сохранив архитектурную простоту и устранив Write Amplification при импорте данных.
- **Task 6:** Реализован Selective Versioned Cache в Redis на границе Read Model интерфейсов:
  - Белый список из 7 методов (сводки KPI и опции фильтров);
  - Запрет кэширования пагинированных списков и поисковых запросов;
  - Детерминированная канонизация параметров `CanonicalCriteria` и стандартизированный ключ кэша `AnalyticsCacheKey`;
  - Монотонное версионирование датасетов в таблице `analytics_dataset_versions`;
  - Автоматическая инвалидация при импорте батчей в Data Ingestion;
  - Fail-Open архитектура с санитарным логированием технических метаданных.
- **Task 7:** Оценка необходимости фронтенд-кэша: постоянный кэш на клиенте отклонен (ADR-013, ADR-020). В `widget-data-loader.ts` внедрен легковесный request-scoped promise coalescing (`coalesceInFlightRequest`), объединяющий параллельные запросы виджетов дашборда внутри одного цикла рендера.
- **Task 8:** Добавлен полный комплекс регрессионных и parity-тестов (`AnalyticsCacheParityAndRegressionTest.php`, `AnalyticsCacheInvalidationTest.php`, `AnalyticsCacheIntegrationTest.php`, `AnalyticsQueryPlanTest.php`, `widget-data-loader.test.ts`), обновлен скрипт интеграционной проверки `scripts/verify-integration.sh`.
- **Task 9:** Обновлена архитектурная документация (06, 09, 10), зафиксировано архитектурное решение `ADR-020` в `docs/architecture/12-architecture-decisions.md`.

### Что осталось
- Все запланированные задачи и exit criteria выполнены.

### Блокеры
- Отсутствуют.

### Следующий шаг
- Переход к Phase 17 — Observability (`docs/roadmap/17-observability.md`).

---

## Проверка завершения

- **Дата:** 2026-09-25.
- **Статус критериев приёмки (Exit Criteria):**
  - [x] *Performance baseline задокументирован:* профиль `large` описан в `docs/performance/README.md`, baseline-матрица зафиксирована в `docs/performance/phase-16-baseline.md`.
  - [x] *Bottlenecks измерены:* планы `EXPLAIN (ANALYZE, BUFFERS)` до и после собраны в каталогах `docs/performance/plans/`.
  - [x] *Optimization evidence-driven:* покрывающий индекс устранил дисковый сброс сортировки (13.6 MB → 0 MB), `SALES-01` p95 улучшен с 1 947 ms до 370 ms (PASS), все 23 сценария в пределах бюджета.
  - [x] *Решение по проекциям зафиксировано:* отклонено с приведением метрик в `docs/performance/phase-16-optimization-report.md`.
  - [x] *Cache semantics ясна:* Allowlist из 7 методов, монотонный инкремент версий в `analytics_dataset_versions`, TTL 120–300 с, Fail-Open отказоустойчивость, запрет кэширования пагинации.
  - [x] *Correctness не ухудшилась:* 100% паритет payload между `disabled`, `cold` и `warm` проверен автоматическими тестами.
- **Выполненные команды проверок и результаты:**
  - `composer --working-dir=backend test -- --testsuite=Unit` — OK (219 tests, 31530 assertions)
  - `composer --working-dir=backend test -- --testsuite=Feature` — OK (126 tests, 1846 assertions)
  - `composer --working-dir=backend test -- --filter=Performance` — OK (38 tests, 3720 assertions)
  - `npm --prefix frontend test -- widget-data-loader.test.ts` — OK (12 tests passed)
  - `npm --prefix frontend test` — OK (48 test files, 217 tests passed)
  - `composer --working-dir=notification test` — OK (38 tests, 144 assertions, 4 skipped by driver check)
  - `npm --prefix frontend run contracts:validate` — OK (OpenAPI spec valid)
  - `npm --prefix frontend run lint && npm --prefix frontend run typecheck` — OK (0 errors)
  - `composer --working-dir=backend lint` — OK (0 errors)

