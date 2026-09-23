# Phase 16 — Performance Baseline & Scenario Matrix

## Назначение документа

В данном документе зафиксированы:
1. Каталог эталонных сценариев бенчмаркинга аналитической подсистемы (Scenario Matrix) с параметрами фильтрации по умолчанию (default) и высокой селективности (selective).
2. Оракулы корректности (Correctness Oracles) и ожидаемая селективность для каждого сценария.
3. Протокол сбора исходных замеров (Baseline Measurement Protocol) до любых изменений кода, индексов или кэша.
4. Шаблоны фиксации метрик и артефактов планов `EXPLAIN (ANALYZE, BUFFERS)` для Task 3.

---

## Матрица сценариев бенчмаркинга (Scenario Matrix)

Все сценарии выполняются в изолированном рабочем пространстве `perf-ws-1` на профиле данных `large`:
- 100 000 заказов (`sales_orders`)
- 300 000 позиций заказов (`sales_order_items`)
- 500 000 снимков остатков (`inventory_snapshots`)
- 200 000 поставок от поставщиков (`supplier_deliveries`)
- 10 000 товаров (`products`), 10 категорий, 5 складов, 50 поставщиков, 8 регионов

### 1. Подсистема Sales Analytics (`SalesAnalyticsReadModelInterface`)

| ID | Сценарий | Метод Read Model | Критерии (Criteria DTO) | Селективность | Допустимый Cache State | Бюджет p95 | Оракул корректности |
|---|---|---|---|---|---|---:|---|
| **SALES-01** | Sales Overview (Default) | `getSalesOverview` | `SalesFilterCriteriaDto`:<br>`dateFrom: null`<br>`dateTo: null`<br>`categoryId: null`<br>`regionId: null` | 100% данных заказов (~100k заказов, ~300k items) | `disabled`<br>`cold`<br>`warm` | **≤ 1 000 ms** (без Redis)<br>**≤ 200 ms** (Redis warm) | `total_revenue = SUM(total_amount)`, `orders_count = COUNT(*)`, `average_order_value = total_revenue / orders_count`. Parity: `warm == cold == disabled`. |
| **SALES-02** | Sales Overview (Selective) | `getSalesOverview` | `SalesFilterCriteriaDto`:<br>`dateFrom: '2026-06-01'`<br>`dateTo: '2026-06-30'`<br>`categoryId: 'cat-electronics'`<br>`regionId: 'reg-north'` | Высокая селективность (~2-4% заказов, ~2 500 заказов) | `disabled`<br>`cold`<br>`warm` | **≤ 1 000 ms** (без Redis)<br>**≤ 200 ms** (Redis warm) | Соответствие фильтрам по индексам `(workspace_id, order_date, category_id, region_id)`. Суммы строго совпадают с прямым SQL `WHERE`. |
| **SALES-03** | Sales Filter Options | `getFilterOptions` | Нет параметров критериев (только `workspaceId: 'perf-ws-1'`) | Полный справочный охват измерений workspace | `disabled`<br>`cold`<br>`warm` | **≤ 1 000 ms** (без Redis)<br>**≤ 200 ms** (Redis warm) | Возвращает 10 категорий, 8 регионов, корректные `minDate` и `maxDate` заказов в `perf-ws-1`. |
| **SALES-04** | Sales Records (Default Pagination) | `getSalesRecords` | `SalesRecordsCriteriaDto`:<br>`page: 1, perPage: 20`<br>`sortBy: 'order_date'`<br>`sortDirection: 'desc'` | Полная выборка, лимит 20 строк, `total: 100 000` | `disabled` (НЕ кэшируется) | **≤ 1 500 ms** (без Redis) | `totalCount == 100 000`, ровно 20 записей, даты упорядочены по убыванию с вторичным tie-breaker по `id DESC`. |
| **SALES-05** | Sales Records (Selective Filtered) | `getSalesRecords` | `SalesRecordsCriteriaDto`:<br>`dateFrom: '2026-01-01'`<br>`dateTo: '2026-03-31'`<br>`categoryId: 'cat-electronics'`<br>`regionId: 'reg-west'`<br>`page: 5, perPage: 20`<br>`sortBy: 'total_amount'`<br>`sortDirection: 'desc'` | Селективность ~3% (~3 000 строк). Офсет `page=5` | `disabled` (НЕ кэшируется) | **≤ 1 500 ms** (без Redis) | `totalCount` равен числу строк с заданными фильтрами; смещение `OFFSET 80 LIMIT 20` строго детерминировано. |

---

### 2. Подсистема Inventory Analytics (`InventoryAnalyticsReadModelInterface`)

| ID | Сценарий | Метод Read Model | Критерии (Criteria DTO) | Селективность | Допустимый Cache State | Бюджет p95 | Оракул корректности |
|---|---|---|---|---|---|---:|---|
| **INV-01** | Inventory Summary (Default) | `getInventorySummary` | `InventorySummaryCriteriaDto`:<br>`warehouseId: null`<br>`asOfDate: null` (последний снимок) | 100% активных остатков на складах (~50 000 записей последнего снимка) | `disabled`<br>`cold`<br>`warm` | **≤ 1 000 ms** (без Redis)<br>**≤ 200 ms** (Redis warm) | `total_stock_value = SUM(qty * unit_cost)`, распределение по `stock_health` (`healthy`, `low_stock`, `out_of_stock`, `overstock`) суммируется в общий `total_items`. |
| **INV-02** | Inventory Summary (Selective) | `getInventorySummary` | `InventorySummaryCriteriaDto`:<br>`warehouseId: 'wh-central'`<br>`asOfDate: '2026-05-15'` | Срез по одному складу на историческую дату (~10 000 остатков) | `disabled`<br>`cold`<br>`warm` | **≤ 1 000 ms** (без Redis)<br>**≤ 200 ms** (Redis warm) | Точное совпадение со снимками `inventory_snapshots WHERE warehouse_id = 'wh-central' AND snapshot_date = '2026-05-15'`. |
| **INV-03** | Inventory Filter Options | `getFilterOptions` | Нет параметров критериев (только `workspaceId: 'perf-ws-1'`) | Справочники складов и категорий запасов | `disabled`<br>`cold`<br>`warm` | **≤ 1 000 ms** (без Redis)<br>**≤ 200 ms** (Redis warm) | 5 складов, перечень категорий товаров, актуальные даты снимков. |
| **INV-04** | Inventory Items (Default Pagination) | `getInventoryItems` | `InventoryItemsCriteriaDto`:<br>`page: 1, perPage: 20`<br>`sortBy: 'quantity_available'`<br>`sortDirection: 'asc'` | Полный список остатков, сортировка по дефициту | `disabled` (НЕ кэшируется) | **≤ 1 500 ms** (без Redis) | Первые 20 записей упорядочены по `quantity_available ASC`, `id ASC`. `totalCount` соответствует общему числу активных позиций. |
| **INV-05** | Inventory Items (Selective Search) | `getInventoryItems` | `InventoryItemsCriteriaDto`:<br>`warehouseId: 'wh-central'`<br>`stockHealth: 'low_stock'`<br>`search: 'Filter'`<br>`page: 2, perPage: 20` | Селективность ~0.5% (~50 товаров на складе в статусе `low_stock`) | `disabled` (НЕ кэшируется) | **≤ 1 500 ms** (без Redis) | Все возвращённые товары имеют статус `low_stock`, склад `wh-central`, имя/артикул содержат `Filter`. |
| **INV-06** | ABC/XYZ Summary (90 Days Default) | `getAbcXyzSummary` | `AbcXyzSummaryCriteriaDto`:<br>`periodDays: 90`<br>`warehouseId: null`<br>`categoryId: null`<br>`supplierId: null` | Анализ выручки и вариации спроса за 90 дней по всем 10 000 товаров | `disabled`<br>`cold`<br>`warm` | **≤ 1 000 ms** (без Redis)<br>**≤ 200 ms** (Redis warm) | Сумма долей выручки матричных групп AX..CZ равна 100%. Число классифицированных товаров равно числу товаров с продажами. |
| **INV-07** | ABC/XYZ Summary (Selective Filtered) | `getAbcXyzSummary` | `AbcXyzSummaryCriteriaDto`:<br>`periodDays: 30`<br>`warehouseId: 'wh-north'`<br>`categoryId: 'cat-auto-parts'`<br>`supplierId: 'sup-bosch'` | Селективность ~5% товаров (~500 товаров по категории и складу) | `disabled`<br>`cold`<br>`warm` | **≤ 1 000 ms** (без Redis)<br>**≤ 200 ms** (Redis warm) | Расчёт по правилам Парето (A=80%, B=15%, C=5%) и коэффициенту вариации XYZ только для выбранного среза. |
| **INV-08** | ABC/XYZ Items (Group AX Page 1) | `getAbcXyzItems` | `AbcXyzItemsCriteriaDto`:<br>`periodDays: 90`<br>`group: 'AX'`<br>`page: 1, perPage: 20`<br>`sortBy: 'total_revenue'`<br>`sortDirection: 'desc'` | Товары группы высокой выручки и стабильного спроса (~5-10% товаров) | `disabled` (НЕ кэшируется) | **≤ 1 500 ms** (без Redis) | Все 20 товаров принадлежат группе AX, выручка монотонно убывает (`total_revenue DESC`). |
| **INV-09** | ABC/XYZ Items (Selective Filtered) | `getAbcXyzItems` | `AbcXyzItemsCriteriaDto`:<br>`periodDays: 90`<br>`abcClass: 'A'`<br>`xyzClass: 'X'`<br>`categoryId: 'cat-electronics'`<br>`search: 'Sensor'`<br>`page: 1, perPage: 20` | Высокая селективность (~20-40 товаров) | `disabled` (НЕ кэшируется) | **≤ 1 500 ms** (без Redis) | Строгое соответствие классу A, классу X, категории и подстроке поиска `Sensor`. |

---

### 3. Подсистема Supplier Analytics (`SupplierAnalyticsReadModelInterface`)

| ID | Сценарий | Метод Read Model | Критерии (Criteria DTO) | Селективность | Допустимый Cache State | Бюджет p95 | Оракул корректности |
|---|---|---|---|---|---|---:|---|
| **SUP-01** | Supplier Overview (Default) | `getSupplierOverview` | `SupplierOverviewCriteriaDto`:<br>`dateFrom: null`<br>`dateTo: null`<br>`supplierId: null`<br>`warehouseId: null` | 100% поставок (200 000 поставок от 50 поставщиков) | `disabled`<br>`cold`<br>`warm` | **≤ 1 000 ms** (без Redis)<br>**≤ 200 ms** (Redis warm) | `total_spend = SUM(actual_amount)`, `deliveries_count = COUNT(*)`, `on_time_delivery_rate = on_time_count / total_count`. |
| **SUP-02** | Supplier Overview (Selective) | `getSupplierOverview` | `SupplierOverviewCriteriaDto`:<br>`supplierId: 'sup-valeo'`<br>`warehouseId: 'wh-central'`<br>`dateFrom: '2026-01-01'`<br>`dateTo: '2026-06-30'` | Срез по одному поставщику и складу за 6 месяцев (~2 000 поставок) | `disabled`<br>`cold`<br>`warm` | **≤ 1 000 ms** (без Redis)<br>**≤ 200 ms** (Redis warm) | Суммы затрат и число поставок строго соответствуют прямому SQL с фильтрами по `supplier_id` и `warehouse_id`. |
| **SUP-03** | Supplier Filter Options | `getFilterOptions` | Нет параметров критериев (только `workspaceId: 'perf-ws-1'`) | Справочник поставщиков, складов, статусов | `disabled`<br>`cold`<br>`warm` | **≤ 1 000 ms** (без Redis)<br>**≤ 200 ms** (Redis warm) | 50 поставщиков, 5 складов, список статусов поставок. |
| **SUP-04** | Supplier Performance (Default Paginated) | `getSupplierPerformance` | `SupplierPerformanceCriteriaDto`:<br>`page: 1, perPage: 20`<br>`sortBy: 'total_spend'`<br>`sortDirection: 'desc'` | Агрегация всех 50 поставщиков, пагинация первой страницы | `disabled` (НЕ кэшируется) | **≤ 1 500 ms** (без Redis) | `totalCount == 50`, ровно 20 поставщиков, упорядоченных по общему объёму затрат (`total_spend DESC`). |
| **SUP-05** | Supplier Performance (Selective Search) | `getSupplierPerformance` | `SupplierPerformanceCriteriaDto`:<br>`search: 'Auto'`<br>`warehouseId: 'wh-north'`<br>`dateFrom: '2026-01-01'`<br>`dateTo: '2026-06-30'`<br>`page: 1, perPage: 20` | Селективность по имени и складу (~5-10 поставщиков) | `disabled` (НЕ кэшируется) | **≤ 1 500 ms** (без Redis) | Все поставщики содержат подстроку `Auto` и имеют поставки на склад `wh-north` за указанный период. |
| **SUP-06** | Supplier Deliveries (Default Paginated) | `getSupplierDeliveries` | `SupplierDeliveriesCriteriaDto`:<br>`page: 1, perPage: 20`<br>`sortBy: 'order_date'`<br>`sortDirection: 'desc'` | Полный список 200 000 поставок, лимит 20 | `disabled` (НЕ кэшируется) | **≤ 1 500 ms** (без Redis) | `totalCount == 200 000`, 20 записей с сортировкой по дате заказа убывающей. |
| **SUP-07** | Supplier Deliveries (Selective Delayed) | `getSupplierDeliveries` | `SupplierDeliveriesCriteriaDto`:<br>`supplierId: 'sup-valeo'`<br>`warehouseId: 'wh-central'`<br>`status: 'delayed'`<br>`dateFrom: '2026-01-01'`<br>`dateTo: '2026-06-30'`<br>`page: 1, perPage: 20` | Селективность ~0.5% (~100 проблемных поставок) | `disabled` (НЕ кэшируется) | **≤ 1 500 ms** (без Redis) | Все возвращённые поставки имеют статус `delayed`, принадлежат поставщику `sup-valeo` и складу `wh-central`. |

---

### 4. Комплексные сценарии Dashboard Fan-out

| ID | Сценарий | Состав запросов (Fan-out composition) | Модель исполнения | Допустимый Cache State | Бюджет p95 | Оракул корректности |
|---|---|---|---|---|---:|---|
| **DASH-01** | Executive Dashboard Fan-out | 4 параллельных/последовательных запроса:<br>1. `getSalesOverview` (Default)<br>2. `getInventorySummary` (Default)<br>3. `getSupplierOverview` (Default)<br>4. `getFilterOptions` (Sales) | 4 независимых запроса от клиентских виджетов в рамках одного экрана | `disabled`<br>`cold`<br>`warm` | **≤ 2 000 ms** (суммарно без Redis)<br>**≤ 400 ms** (Redis warm) | Данные каждого виджета идентичны изолированным вызовам. Общее время не превышает бюджет. |
| **DASH-02** | Dashboard Duplicate Aggregates | 3 идентичных запроса `getSalesOverview` (Default) от разных виджетов одного экрана | Повторные запросы идентичных агрегатов в одном цикле отрисовки | `disabled`<br>`cold`<br>`warm` | **≤ 2 000 ms** (без Redis)<br>**≤ 200 ms** (Redis warm) | Все 3 ответа побитово идентичны. При теплом кэше повторные запросы отдаются за доли миллисекунд. |

---

## Протокол сбора Baseline замеров (Task 3 Protocol)

### Пошаговый алгоритм выполнения

1. **Генерация эталонного датасета `large`:**
   ```bash
   php artisan performance:seed --profile=large --workspace=perf-ws-1 --seed=42
   ```
2. **Проверка объёмов и целостности:**
   Выполнить SQL-запрос верификации row counts (см. `README.md`). Убедиться, что объёмы строго соответствуют целевым 100k / 300k / 500k / 200k / 10k.
3. **Обновление статистики PostgreSQL:**
   ```sql
   ANALYZE sales_orders;
   ANALYZE sales_order_items;
   ANALYZE inventory_snapshots;
   ANALYZE supplier_deliveries;
   ANALYZE products;
   ```
4. **Запуск бенчмаркинга в режиме `cache-disabled`:**
   ```bash
   php artisan performance:benchmark --profile=large --runs=30 --warmup=5 --cache-state=disabled --format=json > docs/performance/plans/phase-16-before/baseline-disabled.json
   ```
5. **Сбор планов `EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)`:**
   Для каждого сценария, latency p95 которого превышает установленный бюджет, runner автоматически или через CLI собирает план выполнения и сохраняет его в `docs/performance/plans/phase-16-before/{scenario_id}.json`.

---

## Формат фиксации свидетельств (Evidence Template)

Для каждого сценария в Task 3 заполняется сводная карточка свидетельств:

```markdown
### Evidence Card: [SCENARIO-ID] — [Название сценария]

- **Read Model Call:** `Module::method(criteria)`
- **Cache State:** disabled / cold / warm
- **Samples:** 5 warm-up + 30 measured runs
- **Timestamp:** YYYY-MM-DD HH:MM:SS UTC

| Метрика | Значение Before (Baseline) | Комментарий / Оценка |
|---|---|---|
| **Latency p50 / p95** | ... ms / ... ms | Соответствие бюджету (PASS / FAIL) |
| **Min / Max / Avg Latency** | ... / ... / ... ms | Дисперсия и стабильность замера |
| **SQL Query Count** | N запросов | Количество round-trips к PostgreSQL |
| **Total Rows Examined** | N строк | Оценка избыточности сканирования |
| **Rows Returned** | N строк | Полезный выход запроса |
| **Shared Hit / Read Blocks** | N hit / N read | Попадание в буферный кэш vs чтение с диска |
| **Temp Files & Temp Bytes** | N files / N MB | Сброс промежуточных данных сортировок на диск |
| **Sort Method & Memory** | quicksort / external merge | Потребление `work_mem` |
| **Dominant Plan Nodes** | Seq Scan / Hash Join / etc. | Архитектурные узкие места плана |
| **Plan Artifact** | `docs/performance/plans/phase-16-before/{id}.json` | Ссылка на полный JSON план |
```

---

## Таблица Baseline замеров (Заполняется в Task 3)

Ниже приведена структура таблицы для фиксации измеренных значений в Task 3:

| Scenario ID | Метод | Cache State | p50 (ms) | p95 (ms) | Min (ms) | Max (ms) | Queries | Rows Examined | Shared Reads | Temp Files | Статус бюджета |
|---|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---|
| **SALES-01** | `getSalesOverview` | disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **SALES-02** | `getSalesOverview` (Sel) | disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **SALES-03** | `getFilterOptions` | disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **SALES-04** | `getSalesRecords` | disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **SALES-05** | `getSalesRecords` (Sel) | disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **INV-01** | `getInventorySummary` | disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **INV-02** | `getInventorySummary` (Sel)| disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **INV-03** | `getFilterOptions` | disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **INV-04** | `getInventoryItems` | disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **INV-05** | `getInventoryItems` (Sel) | disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **INV-06** | `getAbcXyzSummary` | disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **INV-07** | `getAbcXyzSummary` (Sel) | disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **INV-08** | `getAbcXyzItems` | disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **INV-09** | `getAbcXyzItems` (Sel) | disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **SUP-01** | `getSupplierOverview` | disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **SUP-02** | `getSupplierOverview` (Sel)| disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **SUP-03** | `getFilterOptions` | disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **SUP-04** | `getSupplierPerformance`| disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **SUP-05** | `getSupplierPerformance`(S)| disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **SUP-06** | `getSupplierDeliveries` | disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **SUP-07** | `getSupplierDeliveries` (S)| disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **DASH-01** | Fan-out Executive | disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
| **DASH-02** | Fan-out Duplicate | disabled | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *T3* | *PENDING T3* |
