# Phase 7 — ABC/XYZ Analysis

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Добавить узнаваемую inventory BI-feature: совмещенный ABC/XYZ анализ для сегментации ассортимента, оптимизации структуры оборотного капитала и выявления зон риска (неликвиды, дефицит ключевых позиций).

## Функциональность

ABC, XYZ, combined matrix, period selection, product-level results, category/supplier filters, объяснение критериев в UI.

Если расчёт становится дорогим, переходить к projection/scheduled calculation.

## Exit Criteria

Algorithm документирован, boundary cases покрыты, product-level результат доступен, UI позволяет исследовать matrix, calculation strategy масштабируема.

---

## Прогресс

### Что сделано

1. **OpenAPI Контракт (`contracts/openapi/analytics-v1.yaml`):**
   - Добавлены эндпоинты `/analytics/inventory/abc-xyz/summary` и `/analytics/inventory/abc-xyz/items`.
   - Добавлены схемы `AbcXyzSummaryResponse`, `AbcXyzSummary`, `AbcXyzMatrixCell`, `AbcDistributionItem`, `XyzDistributionItem`, `AbcXyzItemsResponse`, `AbcXyzProductItem`.
   - Расширен `InventoryFilterOptionsResponse` полями `categories` и `suppliers`.
   - Сгенерирован TypeScript клиент `frontend/src/shared/api/generated/schema.ts` и обновлен `ApiContractTest`.
2. **Domain Layer (`App\Modules\InventoryAnalytics\Domain`):**
   - Добавлены Value Objects / Enums: `AbcClass` (A: $\le 80\%$, B: $80-95\%$, C: $>95\%$), `XyzClass` (X: $CV \le 15\%$, Y: $15-35\%$, Z: $>35\%$), `AbcXyzGroup` (AX...CZ с бизнес-наименованиями и рекомендациями по управлению запасами).
   - Разработан чистый доменный калькулятор `AbcXyzCalculator`, реализующий статистику ($CV$, выборочное стандартное отклонение), сортировку Парето и агрегацию 9 сегментов матрицы.
   - Написан изолированный модульный тест `AbcXyzCalculatorTest` (7 тестов, 66 проверок).
3. **Application Layer (`App\Modules\InventoryAnalytics\Application`):**
   - Добавлены DTO: `AbcXyzSummaryCriteriaDto`, `AbcXyzItemsCriteriaDto`, `AbcXyzMatrixCellDto`, `AbcDistributionDto`, `XyzDistributionDto`, `AbcXyzSummaryDto`, `AbcXyzProductItemDto`, `AbcXyzProductItemsPaginatedDto`.
   - Разработаны CQRS Queries и Handlers: `GetAbcXyzSummaryQuery`, `GetAbcXyzSummaryHandler`, `GetAbcXyzItemsQuery`, `GetAbcXyzItemsHandler`.
   - Добавлены методы в интерфейс `InventoryAnalyticsReadModelInterface` и реализованы в `InMemoryInventoryAnalyticsReadModel` с модульными тестами `AbcXyzApplicationTest`.
4. **Infrastructure Layer (`PostgresInventoryAnalyticsReadModel`):**
   - Реализована высокопроизводительная агрегация в PostgreSQL (`fetchRawAbcXyzProducts`) с вычислением продаж по временным интервалам, остатков и стоимости запасов.
   - Покрыто тестами `InventoryAnalyticsReadModelTest`.
5. **Presentation Layer (`App\Modules\InventoryAnalytics\Presentation`):**
   - Созданы Form Requests `GetAbcXyzSummaryRequest` и `GetAbcXyzItemsRequest` с валидацией периодов ($30, 90, 180, 365$), классов, групп и сортировок.
   - Реализованы контроллеры `InventoryAnalyticsController::abcXyzSummary` и `InventoryAnalyticsController::abcXyzItems`.
   - Маршруты зарегистрированы в `routes/api.php`.
   - Добавлены Feature-тесты `InventoryAbcXyzApiTest` (7 тестов, 292 assertions) с проверкой изоляции рабочих пространств и фильтрации.
6. **Frontend Gateway & UI (`frontend/src/features/inventory-analytics`):**
   - Расширен `inventoryGateway` методами `getAbcXyzSummary` и `getAbcXyzItems`.
   - Разработана интерактивная матрица 3×3 `AbcXyzMatrixGrid` с быстрой фильтрацией каталога по клику на ячейку.
   - Создана карточка методологии `AbcXyzMethodologyCard` со складным объяснением формул и стратегий для 9 групп.
   - Создана панель фильтров `AbcXyzFiltersBar` (период, склад, категория, поставщик, быстрый выбор группы, текстовый поиск).
   - Создана таблица номенклатуры `AbcXyzItemsTable` с пагинацией, бейджами классов и сортировкой по 8 колонкам.
   - Разработан корневой координатор `AbcXyzView`, вкладки навигации `InventoryTabsNav` и `InventoryTabsContainer`.
   - Интегрированы вкладки в страницу `app/(dashboard)/inventory/page.tsx`.
   - Написаны тесты компонентов `abc-xyz-components.test.tsx` (8 тестов).
7. **Документация и верификация:**
   - Создан документ [docs/architecture/abc-xyz-methodology.md](../architecture/abc-xyz-methodology.md).
   - Обновлен [docs/architecture/06-data-and-analytics.md](../architecture/06-data-and-analytics.md).
   - Обновлен скрипт интеграционной проверки `scripts/verify-integration.sh`.

---

## Проверка завершения

- **Дата завершения:** 2026-09-22
- **Статус:** Выполнено (все exit criteria подтверждены).

### Подтверждение Exit Criteria
1. **Algorithm документирован:** Документ `docs/architecture/abc-xyz-methodology.md` подробно описывает математическую модель (Парето по выручке, формулу выборочного стандартного отклонения $s$ и $CV = (s/\bar{x})\times 100\%$, дискретизацию временного ряда) и стратегии для всех 9 групп AX...CZ. Ссылка включена в `docs/architecture/06-data-and-analytics.md`.
2. **Boundary cases покрыты:** Тесты `AbcXyzCalculatorTest` покрывают краевые ситуации: пустой каталог ($N=0$), товары без продаж за период, случай $N=1$ временного бакета (исключение деления на $n-1$), нулевую общую выручку.
3. **Product-level результат доступен:** Реализован эндпоинт `/api/v1/analytics/inventory/abc-xyz/items` с пагинацией, фильтрацией и сортировкой, а также компонент `AbcXyzItemsTable`.
4. **UI позволяет исследовать matrix:** Интерактивная сетка 3×3 в `AbcXyzMatrixGrid` визуализирует доли и объемы выручки/остатков; клик по любой ячейке фильтрует каталог по соответствующей группе.
5. **Calculation strategy масштабируема:** Тяжелая агрегация временных рядов продаж и складских остатков выполняется на стороне PostgreSQL; расчет коэффициента вариации и кумулятивных долей в памяти PHP оптимизирован для произвольного количества товаров.

### Результаты автоматических проверок (`make check`)
- `npm --prefix frontend run contracts:validate`: OK (OpenAPI 3.0.3 valid)
- `npm --prefix frontend run format:check`: OK (Prettier)
- `npm --prefix frontend run lint`: OK (ESLint 0 errors)
- `npm --prefix frontend run typecheck`: OK (TypeScript 0 errors)
- `npm --prefix frontend test`: OK (17 test files, 60 tests passed)
- `npm --prefix frontend run build`: OK (Next.js 16 production build succeeded)
- `composer --working-dir=backend lint`: OK (Pint + PHPStan level max 0 errors)
- `composer --working-dir=backend test`: OK (90 tests, 27,864 assertions passed)
