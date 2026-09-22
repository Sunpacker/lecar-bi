# Phase 4 — Sales Analytics Vertical Slice

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Первый полный BI-сценарий от PostgreSQL до UI.

## Backend

Sales summary, revenue, order count, average order value, sales trend, category breakdown, regional breakdown, date/category/region filters.

## Frontend

Sales dashboard, KPI cards, time-series, category/region visualizations, filters, loading/empty/error states.

## Правила

До параллельной frontend/backend реализации зафиксировать OpenAPI. Aggregation выполнять на backend/database, не в браузере.

## Exit Criteria

Dashboard работает на backend data, filters синхронны, calculations покрыты tests, API contract-tested, frontend не считает бизнес-метрики. Сверить с `docs/architecture/03-frontend-nextjs.md`, `06-data-and-analytics.md`, `07-api-and-integration.md`.

## Integration Checkpoint

Перед завершением этапа пройти [интеграционную проверку](ROADMAP.md#integration-checkpoints).

## Прогресс

- Зафиксирован OpenAPI 3.0.3 контракт для аналитики продаж в `contracts/openapi/analytics-v1.yaml` (`/analytics/sales/overview` и `/analytics/sales/filters`) и сгенерирован типизированный клиент TypeScript.
- Реализован Bounded Context `SalesAnalytics` в архитектуре Laravel DDD:
  - **Domain Layer**: Value Objects `DateRange` и `SalesMetrics` (AOV, маржинальность, расчет долей выручки), исключение `InvalidDateRangeException`. Чистый PHP без зависимостей от фреймворка, проверен `ArchitectureTest`.
  - **Application Layer**: DTO-модели (`SalesOverviewDto`, `SalesSummaryDto`, `SalesTrendPointDto`, `SalesCategoryBreakdownDto`, `SalesRegionBreakdownDto`, `SalesFilterOptionsDto`, `SalesFilterCriteriaDto`), Queries и Handlers (`GetSalesOverviewQuery`/`Handler`, `GetSalesFilterOptionsQuery`/`Handler`), контракт `SalesAnalyticsReadModelInterface`.
  - **Infrastructure Layer**: Высокопроизводительный `PostgresSalesAnalyticsReadModel` с агрегацией SQL непосредственно в СУБД по таблицам `fact_order_items`, `dim_categories`, `dim_regions` с обязательной фильтрацией по `workspace_id`. Реализован детерминированный `InMemorySalesAnalyticsReadModel` для изоляции юнит-тестов.
  - **Presentation Layer**: `SalesAnalyticsController`, FormRequest валидация параметров фильтрации (`GetSalesOverviewRequest`), регистрация маршрутов в `routes/api.php` под `AuthenticateUserIdMiddleware` и разграничение доступа по `workspace_id` через `WorkspaceAccessGuard`.
- Реализован frontend-модуль `features/sales-analytics` в Next.js 15:
  - Клиентский шлюз `salesGateway` на основе `analyticsClient`;
  - KPI-карточки (`SalesKpiCards`): Выручка, Заказы, Средний чек, Маржинальность;
  - SVG-график динамики продаж во времени (`SalesTrendChart`);
  - Визуализация срезов выручки по категориям (`SalesCategoryBreakdownView`) и регионам (`SalesRegionalBreakdownView`);
  - Панель синхронных фильтров (`SalesFiltersBar`) по датам, категории и региону со сбросом;
  - Интерактивный дашборд (`SalesDashboard`) с состояниями загрузки (скелетон), отсутствия данных (empty state) и ошибок (с повтором);
  - Интеграция дашборда на главную страницу приложения `app/page.tsx` с поддержкой переключения рабочего пространства.

## Проверка завершения

Дата: 2026-09-22.

Exit criteria полностью подтверждены: дашборд работает на реальных данных PostgreSQL, фильтры синхронны, расчеты покрыты тестами, API проверен по контракту OpenAPI, фронтенд не считает бизнес-метрики. Соответствие `docs/architecture/03-frontend-nextjs.md`, `06-data-and-analytics.md`, `07-api-and-integration.md` подтверждено.

- `make check` — Redocly OpenAPI validation, TypeScript generation, ESLint, Prettier, TypeScript typecheck, 17 тестов Vitest, сборка Next.js production, Composer strict validation, Pint, PHPStan и 40 тестов PHPUnit (27 117 assertions) успешно пройдены.
- `docker compose ... build` — образы бэкенда и фронтенда успешно пересобраны.
- `scripts/verify-integration.sh` подтвердил в работающем окружении:
  1. Корректную отдачу фильтров и границ дат `/api/v1/analytics/sales/filters`;
  2. Корректную отдачу агрегированных данных продаж `/api/v1/analytics/sales/overview` из PostgreSQL (4 453 заказа для ws-1, выручка 138 367 270 ₽, валовая прибыль 51 310 830 ₽, AOV 31 072.82 ₽);
  3. Сохранение полной изоляции арендаторов (попытка перекрестного доступа к ws-2 пользователем user-1 возвращает 403 Forbidden);
  4. Работоспособность и рендеринг дашборда аналитики продаж в пользовательском интерфейсе.
- `git diff --check` — форматирование и diff корректны.
