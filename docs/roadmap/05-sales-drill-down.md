# Phase 5 — Sales Drill-Down and BI Interaction Model

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Сделать dashboard интерактивным BI-инструментом.

## Функциональность

Cross-filtering, drill-down, drill-through, URL-persisted filter state где оправдано, общий filter model, reset behavior, detail queries, pagination, sorting, clickable visualizations и detail tables.

## Exit Criteria

Пользователь проходит от summary к detail, filter semantics едина, refresh не ломает analytical context, большие datasets обслуживаются backend pagination/sorting, ключевые flows имеют interaction tests.

## Integration Checkpoint

Перед завершением этапа пройти [интеграционную проверку](ROADMAP.md#integration-checkpoints).

## Прогресс

- Зафиксирован контракт OpenAPI 3.0.3 для детальных записей продаж: эндпоинт `GET /analytics/sales/records`, параметры фильтрации (`date_from`, `date_to`, `category_id`, `region_id`), серверной пагинации (`page`, `per_page`), сортировки (`sort_by`, `sort_direction`), схемы `SalesRecordsResponse`, `SalesRecordItem`, `PaginationMetadata` в `contracts/openapi/analytics-v1.yaml`. Сгенерированы TypeScript-типы в `frontend/src/shared/api/generated/schema.ts`.
- Реализована backend application-логика в Bounded Context `SalesAnalytics`:
  - DTO: `SalesRecordDto`, `SalesRecordsCriteriaDto`, `SalesRecordsPaginatedDto`;
  - Query и Handler: `GetSalesRecordsQuery`, `GetSalesRecordsHandler`;
  - Расширен интерфейс `SalesAnalyticsReadModelInterface` методом `getSalesRecords(string $workspaceId, SalesRecordsCriteriaDto $criteria)`.
- Реализована инфраструктура доступа к данным:
  - `PostgresSalesAnalyticsReadModel`: SQL-запрос с объединением таблиц Star Schema (`fact_order_items`, `fact_orders`, `dim_products`, `dim_categories`, `dim_regions`, `dim_brands`), строгой изоляцией по `workspace_id`, фильтрацией по срезам, безопасным whitelist для сортировки (`order_date`, `order_number`, `product_name`, `total_price`, `quantity`, `gross_profit`) и эффективной пагинацией через `LIMIT`/`OFFSET` с подсчетом `COUNT(*) OVER()`;
  - `InMemorySalesAnalyticsReadModel` для изолированного детерминированного юнит-тестирования.
- Реализован Presentation-слой:
  - Валидация входных параметров `GetSalesRecordsRequest`;
  - Метод `records` в `SalesAnalyticsController` с проверкой доступа через `WorkspaceAccessGuard` и аутентификацией `AuthenticateUserIdMiddleware`;
  - Маршрут `GET /analytics/sales/records` в `backend/routes/api.php`.
- Реализован frontend gateway и компоненты в Next.js 15:
  - Шлюз `salesGateway.getRecords` с автоматической передачей заголовков пользователя и рабочей области;
  - Кросс-фильтрация в визуализациях: кликабельные срезы категорий (`SalesCategoryBreakdownView`) и регионов (`SalesRegionalBreakdownView`) с подсветкой активных элементов и кнопками индивидуального сброса фильтра; интерактивные точки на графике динамики продаж (`SalesTrendChart`);
  - Компонент детальной таблицы `SalesDetailTable` с серверной сортировкой по колонкам, серверной пагинацией, бейджами статусов заказов, форматированием денежных сумм и обработкой состояний загрузки/пустых данных;
  - Интеграция в `SalesDashboard` с единой моделью состояния фильтров, синхронизацией с URL query-параметрами (`useSearchParams` и `window.history.replaceState`), параллельной загрузкой сводки и детальных записей, сохранением контекста при обновлении страницы (F5) и кнопкой полного сброса всех фильтров;
  - Оборачивание `SalesDashboard` в `<Suspense>` на главной странице `app/page.tsx`.
- Комплекс тестов:
  - Unit-тесты обработчика `GetSalesRecordsHandlerTest`;
  - Инфраструктурные тесты read model `SalesAnalyticsReadModelRecordsTest`;
  - Feature-тесты API `SalesAnalyticsRecordsApiTest` (авторизация, валидация, пагинация, сортировка, фильтрация, изоляция тенантов);
  - Юнит-тесты frontend-шлюза `sales-gateway.test.ts`;
  - Компонентные и интеграционные тесты взаимодействия: `sales-components.test.tsx`, `sales-detail-table.test.tsx`, `sales-dashboard.test.tsx`.

## Проверка завершения

Дата: 2026-09-22.

Exit criteria полностью подтверждены: пользователь бесшовно переходит от сводных показателей к детализированным данным заказов/позиций, семантика фильтров синхронна и едина между визуализациями и таблицей, обновление страницы (F5) сохраняет аналитический контекст из URL, выборки больших объемов данных эффективно обслуживаются серверной пагинацией и сортировкой PostgreSQL, ключевые пользовательские сценарии покрыты тестами взаимодействия.

- `make check` — успешно пройдены:
  - Redocly OpenAPI 3.0.3 validation (0 errors, 0 warnings);
  - openapi-typescript генерация типов клиента;
  - ESLint (0 errors, 0 warnings);
  - Prettier check (форматирование корректно);
  - TypeScript typecheck `tsc --noEmit` без ошибок;
  - Vitest: 26 тестов (включая unit, gateway, component и dashboard interaction tests) пройдены;
  - Next.js production build (`npm run build`) успешно скомпилирован;
  - Composer strict validation (`./composer.json is valid`);
  - Laravel Pint (тесты форматирования пройдены);
  - Larastan / PHPStan level max ([OK] No errors);
  - PHPUnit: 47 тестов, 27 164 assertions успешно пройдены.
- `make build` и `make infra-up` — образы бэкенда и фронтенда пересобраны и контейнеры запущены в healthy-состоянии.
- `make integration` (`scripts/verify-integration.sh`) успешно подтвердил:
  1. Доступность и валидность эндпоинта детальных записей `/api/v1/analytics/sales/records?page=1&per_page=10&sort_by=total_price&sort_direction=desc`;
  2. Наличие метаданных пагинации (`pagination.total`, `pagination.page`, `pagination.per_page`, `pagination.total_pages`) и массива записей `items`;
  3. Строгую изоляцию тенантов при обращении к записям продаж (403 Forbidden при попытке запросить записи чужого workspace_id);
  4. Корректный рендеринг дашборда и таблицы деталей продаж на фронтенде;
  5. Целостность генератора демонстрационных данных в PostgreSQL (4 453 заказа в ws-1, 4 455 заказов в ws-2).
