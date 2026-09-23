# Phase 11 — Supplier Analytics

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Добавить третью крупную аналитическую область системы AutoBI — аналитику поставщиков и поставок (Supplier Analytics).

## Функциональность

- **Supplier Overview:** карточки ключевых показателей (всего поставок, On-Time Delivery Rate, Fill Rate, уровень дефектности, общая сумма закупки).
- **Trends & Status Breakdown:** динамика поставок и показателей надежности во времени (по месяцам), структура статусов заказов (в срок, с задержкой, частичные, отмененные).
- **Supplier Performance Ranking:** рейтинг и сравнение поставщиков по объему, соблюдению сроков, проценту выполнения, уровню брака, среднему сроку поставки (lead time) и интегральному скорингу надежности (`SupplierReliabilityTier`).
- **Deliveries Journal:** детализированный журнал поставок с поиском, фильтрацией по статусам и складам, серверной пагинацией и сортировкой.
- **Фильтрация:** сквозные фильтры по диапазону дат, конкретному поставщику и складу назначения.

## Exit Criteria

Metric definitions явны, calculations backend-owned, interaction conventions едины, tests покрывают расчёты.

---

## Прогресс

### Что сделано

1. **Миграция БД и генератор демонстрационных данных:**
   - Разработана forward-safe миграция `2026_09_23_000040_add_defect_quantity_to_fact_supplier_deliveries.php`, добавившая поле `defect_quantity INT DEFAULT 0` в таблицу `fact_supplier_deliveries`.
   - Обновлен генератор сидов `DemoDatasetGenerator` для детерминированного наполнения дефектности и статусов поставок.
   - Покрыто структурными тестами миграций `AnalyticsSchemaMigrationStructureTest`.

2. **OpenAPI 3.0.3 Спецификация и генерация TypeScript-клиента:**
   - В `contracts/openapi/analytics-v1.yaml` спроектированы 4 эндпоинта:
     - `GET /analytics/suppliers/overview` — сводные KPI, тренды и распределение по статусам;
     - `GET /analytics/suppliers/filters` — опции фильтрации (поставщики, склады, статусы, границы дат);
     - `GET /analytics/suppliers/performance` — пагинированная аналитика эффективности поставщиков;
     - `GET /analytics/suppliers/deliveries` — пагинированный реестр поставок с фильтрами и сортировкой.
   - Проведена валидация спецификации Redocly CLI (`contracts:validate`).
   - Сгенерированы актуальные типы в `frontend/src/shared/api/generated/schema.ts`.
   - Покрыто тестом валидации контрактов `ApiContractTest`.

3. **Backend Domain Layer (`App\Modules\SupplierAnalytics\Domain`):**
   - Реализован чистый доменный сервис `SupplierMetrics` (без зависимостей от Laravel и внешних библиотек):
     - On-Time Delivery Rate: `(on_time_deliveries / total_deliveries) * 100`;
     - Fill Rate (Delivery Fulfillment Rate): `(received_quantity / ordered_quantity) * 100`;
     - Defect Rate: `(defect_quantity / received_quantity) * 100`;
     - Average Lead Time (дней между заказом и поставкой);
     - Интегральный Reliability Score: `0.5 * OnTime + 0.4 * Fulfillment - 0.1 * Defect`.
   - Доменные Enums `DeliveryStatus` и `SupplierReliabilityTier` (`EXCELLENT`, `GOOD`, `ACCEPTABLE`, `POOR`).
   - Тесты: 100% покрытие в `SupplierMetricsTest`, подтверждение чистоты границ в `ArchitectureTest` (170 assertions).

4. **Backend Application Layer (`App\Modules\SupplierAnalytics\Application`):**
   - Контракт `SupplierAnalyticsReadModelInterface`.
   - 12 типизированных DTO для критериев, сводки, трендов, перформанса и поставок.
   - CQRS-запросы и обработчики:
     - `GetSupplierOverviewQuery` / `GetSupplierOverviewHandler`;
     - `GetSupplierFilterOptionsQuery` / `GetSupplierFilterOptionsHandler`;
     - `GetSupplierPerformanceQuery` / `GetSupplierPerformanceHandler`;
     - `GetSupplierDeliveriesQuery` / `GetSupplierDeliveriesHandler`.
   - Покрыто тестами в `SupplierAnalyticsApplicationTest`.

5. **Backend Infrastructure Layer (`App\Modules\SupplierAnalytics\Infrastructure`):**
   - `InMemorySupplierAnalyticsReadModel` для быстрых изолированных тестов.
   - `PostgresSupplierAnalyticsReadModel` для продакшн аналитических запросов к таблицам `fact_supplier_deliveries`, `dim_suppliers`, `dim_warehouses` с изоляцией по `workspace_id`.
   - Регистрация синглтона в `AppServiceProvider`.
   - Покрыто тестами в `SupplierAnalyticsReadModelTest`.

6. **Backend Presentation Layer (`App\Modules\SupplierAnalytics\Presentation`):**
   - Валидация входных параметров в `GetSupplierOverviewRequest`, `GetSupplierPerformanceRequest`, `GetSupplierDeliveriesRequest`.
   - `SupplierAnalyticsController` с возвратом типизированных JSON ответов.
   - Регистрация роутов в `backend/routes/api.php` под защитой `AuthenticateUserIdMiddleware`.
   - Покрыто тестами в `SupplierAnalyticsApiTest`.

7. **Frontend Supplier Analytics Feature (`frontend/src/features/supplier-analytics`):**
   - Шлюз `supplierGateway` с методами `getOverview`, `getFilters`, `getPerformance`, `getDeliveries` и обработкой ошибок API.
   - Компоненты визуализации:
     - `SupplierKpiCards` (5 метрик с форматированием валюты и процентов);
     - `SupplierTrendsChart` (комбинированный график Recharts: столбцы поставок + линии On-Time % и Fill Rate %);
     - `SupplierStatusBreakdown` (карточка со статус-барами выполнения);
     - `SupplierFiltersBar` (быстрые пресеты периодов, выбор поставщика и склада, сброс фильтров).
   - Табличные представления:
     - `SupplierPerformanceTable` (сортировка по всем колонкам, цветовые бейджи рейтинга надежности, индикаторы KPI);
     - `SupplierDeliveriesTable` (строка поиска, быстрые фильтры по статусам, пагинация, отображение отклонений по срокам и количеству).
   - Интерактивный дашборд `SupplierTabsContainer` с вкладками "Обзор", "Показатели поставщиков", "Журнал поставок", индикаторами загрузки и обработкой ошибок.

8. **Интеграция страницы и навигации:**
   - Создана страница App Router `frontend/app/(dashboard)/suppliers/page.tsx` с проверкой сессии, получением контекста воркспейса и скелетоном Suspense.
   - Активирован раздел «Поставщики» (`/suppliers`) в `frontend/src/shared/ui/layout/sidebar.tsx` (снят флаг `disabled`, удален бейдж «Скоро»).
   - Добавлены модульные тесты страницы `page.test.tsx` и обновлены тесты навигации `sidebar.test.tsx`.

---

## Проверка завершения

- **Дата завершения:** 23 сентября 2026 г.
- **Подтверждение Exit Criteria:**
  - `Metric definitions явны`: Все формулы (On-Time Delivery, Fill Rate, Defect Rate, Lead Time, Reliability Score) формализованы в `SupplierMetrics` и OpenAPI схеме.
  - `Calculations backend-owned`: Фронтенд получает готовые рассчитанные агрегаты и проценты от бэкенда, не выполняя бизнес-расчетов в UI.
  - `Interaction conventions едины`: Интерфейс выдержан в едином дизайн-коде проекта (Tailwind, shadcn/ui, Recharts, карточки KPI, адаптивные таблицы с пагинацией и сортировкой, быстрые фильтры).
  - `Tests покрывают расчёты`: 100% покрытие доменных расчетов, запросов, API эндпоинтов и UI компонентов.

### Результаты автоматических проверок

1. **OpenAPI Контракт:**
   ```bash
   npm --prefix frontend run contracts:validate
   # Result: 0 errors, OpenAPI 3.0.3 valid
   ```
2. **Frontend Линтер и Контроль типов:**
   ```bash
   npm --prefix frontend run lint
   # Result: 0 errors, 0 warnings
   npm --prefix frontend run typecheck
   # Result: 0 errors
   ```
3. **Frontend Тестовый набор:**
   ```bash
   npm --prefix frontend test
   # Result: 43 test files passed, 177 tests passed (100%)
   ```
4. **Frontend Production Build:**
   ```bash
   npm --prefix frontend run build
   # Result: Compiled successfully in Turbopack, route /suppliers (Dynamic) built
   ```
5. **Backend Линтер:**
   ```bash
   composer --working-dir=backend lint
   # Result: [OK] No errors (Laravel Pint)
   ```
6. **Backend Тестовый набор:**
   ```bash
   composer --working-dir=backend test
   # Result: 218 tests, 28983 assertions passed (100%)
   ```
7. **Архитектурные ограничения DDD:**
   ```bash
   ./backend/vendor/bin/phpunit -c backend/phpunit.xml --filter ArchitectureTest
   # Result: 3 tests, 170 assertions passed (0 architectural violations)
   ```
