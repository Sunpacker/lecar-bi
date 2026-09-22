# Phase 6 — Inventory Intelligence

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Добавить вторую крупную BI-область.

## Backend

Current inventory, stock quantity, average sales velocity, days of stock, critical stock, overstock, stock health, warehouse breakdown, product drill-down.

Inventory business rules принадлежат backend Domain/Application. При необходимости используются read models.

## Frontend

Inventory dashboard, critical stock, overstock, product details, warehouse/status filters.

## Exit Criteria

Расчёты покрыты тестами, classifications детерминированы, frontend не дублирует rules, queries производительны. Сверить с `docs/architecture/05-bounded-contexts.md` и `06-data-and-analytics.md`.

## Integration Checkpoint

Перед завершением этапа пройти [интеграционную проверку](ROADMAP.md#integration-checkpoints).

## Прогресс

- Зафиксирован контракт OpenAPI 3.0.3 для аналитики товарных запасов: эндпоинты `GET /analytics/inventory/summary`, `GET /analytics/inventory/items`, `GET /analytics/inventory/filters`, схемы данных `InventorySummaryResponse`, `InventorySummary`, `StockHealthBreakdownItem`, `WarehouseStockBreakdownItem`, `InventoryItemsResponse`, `InventoryItem`, `InventoryFilterOptionsResponse` в `contracts/openapi/analytics-v1.yaml`.
- Сгенерированы актуальные TypeScript-типы клиента API в `frontend/src/shared/api/generated/schema.ts`.
- Реализован backend Bounded Context `InventoryAnalytics`:
  - **Domain:**
    - `StockHealthStatus`: enum со значениями `out_of_stock`, `critical`, `optimal`, `overstock`;
    - `InventoryMetrics`: чистые доменные формулы расчета скорости продаж (daily sales velocity за 30 дней), доступности запасов в днях (days of stock), детерминированной классификации здоровья запаса по safety stock и reorder point, вычисления долей; покрыт 100% юнит-тестами без внешних зависимостей.
  - **Application:**
    - DTO: `InventorySummaryDto`, `InventoryItemDto`, `WarehouseStockDto`, `StockHealthBreakdownDto`, `InventoryFilterOptionsDto`, критерии `InventorySummaryCriteriaDto` и `InventoryItemsCriteriaDto`, пагинированный результат `InventoryItemsPaginatedDto`;
    - Queries & Handlers: `GetInventorySummaryQuery` / `GetInventorySummaryHandler`, `GetInventoryItemsQuery` / `GetInventoryItemsHandler`, `GetInventoryFilterOptionsQuery` / `GetInventoryFilterOptionsHandler`;
    - Контракт read model: `InventoryAnalyticsReadModelInterface`.
  - **Infrastructure:**
    - `PostgresInventoryAnalyticsReadModel`: оптимизированные SQL-агрегации на основе таблиц схемы `fact_inventory_daily`, `fact_order_items`, `dim_products`, `dim_warehouses`, `dim_categories` со строгой изоляцией по `workspace_id`, эффективным вычислением скорости продаж за последние 30 дней и вычислением агрегатов на уровне БД;
    - `InMemoryInventoryAnalyticsReadModel`: детерминированная in-memory реализация для изолированного тестирования;
    - Регистрация связывания интерфейса в `AppServiceProvider`.
  - **Presentation:**
    - Requests: `GetInventorySummaryRequest`, `GetInventoryItemsRequest` с валидацией допустимых сортировок, фильтров по статусам и складам;
    - `InventoryAnalyticsController`: эндпоинты `/analytics/inventory/summary`, `/analytics/inventory/items`, `/analytics/inventory/filters` с проверкой прав доступа через `WorkspaceAccessGuard` и аутентификацией `AuthenticateUserIdMiddleware`;
    - Регистрация маршрутов в `backend/routes/api.php`.
- Реализован Frontend Next.js 15:
  - `inventoryGateway`: шлюз запросов к API с типизацией и автоматической передачей заголовков пользователя и рабочей области;
  - Набор UI-компонентов:
    - `InventoryKpiCards`: карточки ключевых показателей (всего позиций, доступно/в резерве, общая стоимость, средний DOS, критический дефицит, out-of-stock);
    - `InventoryHealthBreakdown`: визуализация структуры запасов по статусам здоровья с цветовой дифференциацией и индикатором распределения;
    - `InventoryWarehouseBreakdown`: распределение запасов по складам компании;
    - `InventoryFiltersBar`: панель управления с поиском по SKU и названию, селектами складов и статусов, кнопкой сброса;
    - `InventoryItemsTable`: интерактивная таблица товаров с серверной сортировкой по колонкам (название, доступное кол-во, стоимость, скорость продаж, дни запаса), бейджами статусов, индикаторами риска и пагинацией;
  - Страница дашборда запасов `frontend/app/(dashboard)/inventory/page.tsx` и `InventoryDashboard` с URL-персистентностью фильтров и пагинации;
  - Активация ссылки на страницу управления запасами в навигационном меню (`Sidebar`).
- Набор автоматических тестов:
  - Backend: `InventoryMetricsTest` (Domain), `InventoryAnalyticsApplicationTest` (Application), `InventoryAnalyticsReadModelTest` (Infrastructure), `InventoryAnalyticsApiTest` (Feature/API), `ApiContractTest` (OpenAPI Contract);
  - Frontend: `inventory-gateway.test.ts`, `inventory-components.test.tsx`, `inventory-dashboard.test.tsx`.
- Скрипт интеграционной проверки `scripts/verify-integration.sh` дополнен шагами верификации фильтров, сводки, списка товаров и проверки межпространственной изоляции данных запасов.

## Проверка завершения

Дата: 2026-09-22.

Exit criteria полностью подтверждены:
1. Расчёты покрыты юнит- и интеграционными тестами (`InventoryMetricsTest`, `InventoryAnalyticsApplicationTest`).
2. Классификация статусов здоровья запасов строго детерминирована на бэкенде.
3. Frontend не дублирует бизнес-правила и отображает вычисленные бэкендом метрики.
4. Запросы производительны, используют агрегаты PostgreSQL с изоляцией по `workspace_id`.
5. Архитектурные границы соблюдены (`ArchitectureTest` проходит, Domain изолирован от Laravel).
6. Проведен полный цикл проверок `make check`:
   - Redocly validation OpenAPI 3.0.3: OK;
   - Client API generation: OK;
   - Frontend ESLint, Prettier, TypeScript `tsc --noEmit`, Vitest (15 test files, 49 tests), Next.js production build: OK;
   - Backend Composer strict validation, Laravel Pint, Larastan / PHPStan level max, PHPUnit (71 tests, 27 460 assertions): OK.

