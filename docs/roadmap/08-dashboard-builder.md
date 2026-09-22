# Phase 8 — Dashboard Builder

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Позволить пользователю собирать собственные dashboard.

## Функциональность

Create/rename/delete dashboard, add/remove/move/resize widget, metric/dimension/options, save/reload, workspace ownership, drag-and-drop grid, edit/view modes.

Backend contract должен опираться на семантические widget concepts, а не React implementation details.

## Exit Criteria

Dashboard собирается и восстанавливается, ownership enforced, frontend-specific state не протекает в public contract без причины, основные builder flows покрыты E2E. Сверить с `docs/architecture/05-bounded-contexts.md` и `07-api-and-integration.md`.

## Integration Checkpoint

Перед завершением этапа пройти [интеграционную проверку](ROADMAP.md#integration-checkpoints).

---

## Прогресс

### Что сделано

1. **Предварительное исправление:**
   - Устранена регрессия в `frontend/src/shared/ui/layout/header.tsx`: добавлен компонент `ThemeToggle` с `aria-label="Переключить цветовую тему"`, все 60 тестов фронтенда успешно проходят.

2. **OpenAPI Контракт (`contracts/openapi/analytics-v1.yaml`):**
   - Добавлены эндпоинты `/dashboards` (GET, POST) и `/dashboards/{id}` (GET, PUT, DELETE).
   - Определены семантические схемы: `DashboardSummary`, `DashboardListResponse`, `DashboardDetail`, `DashboardDetailResponse`, `WidgetDetail`, `WidgetInput`, `WidgetGridPosition` (12-колоночная сетка `x, y, w, h`), `WidgetQueryConfig` (dataset `sales|inventory`, семантические метрики, измерения и диапазоны дат), `CreateDashboardRequest`, `UpdateDashboardRequest`.
   - Контракт полностью валиден (Redocly CLI), сгенерированы TypeScript-типы в `frontend/src/shared/api/generated/schema.ts`, добавлен тест в `ApiContractTest`.

3. **Domain Layer (`App\Modules\Dashboard\Domain`):**
   - Разработаны чистые Value Objects: `DashboardId` (с генерацией UUID RFC 4122), `WidgetId`, `WidgetGridPosition` (с инвариантами 12-колоночной сетки), `WidgetQueryConfig`.
   - Добавлены доменные Enums: `WidgetType`, `DatasetType`, `MetricType`, `DimensionType`.
   - Разработана сущность `Widget` и Aggregate Root `Dashboard` (управление виджетами, переименование, инварианты).
   - Интерфейс репозитория `DashboardRepositoryInterface` и доменные исключения `DashboardNotFoundException`, `InvalidGridPositionException`.
   - Полное соответствие архитектурным границам (`ArchitectureTest` подтверждает 0 запрещённых зависимостей), покрыто юнит-тестами `DashboardDomainTest`.

4. **Application Layer (`App\Modules\Dashboard\Application`):**
   - Созданы DTOs: `WidgetGridPositionDto`, `WidgetQueryConfigDto`, `WidgetDto`, `DashboardSummaryDto`, `DashboardDetailDto`.
   - Реализованы CQRS Commands & Handlers: `CreateDashboardHandler`, `UpdateDashboardHandler`, `DeleteDashboardHandler`.
   - Реализованы CQRS Queries & Handlers: `GetDashboardsHandler`, `GetDashboardByIdHandler`.
   - Покрыто изолированными тестами `DashboardApplicationTest`.

5. **Infrastructure Layer (`App\Modules\Dashboard\Infrastructure`):**
   - Созданы миграции PostgreSQL: `2026_09_22_000020_create_dashboards_table.php` и `2026_09_22_000021_create_dashboard_widgets_table.php` с каскадным удалением и индексами.
   - Реализованы Eloquent модели `DashboardModel` и `DashboardWidgetModel`.
   - Реализован `EloquentDashboardRepository` с транзакционной синхронизацией виджетов и `InMemoryDashboardRepository` для быстрого изолированного тестирования.
   - Зарегистрирован биндинг `DashboardRepositoryInterface` в `AppServiceProvider`.
   - Покрыто тестами `DashboardRepositoryTest`.

6. **Presentation Layer (`App\Modules\Dashboard\Presentation`):**
   - Разработаны Form Requests с валидацией: `CreateDashboardRequest` и `UpdateDashboardRequest`.
   - Реализован `DashboardController` (`index`, `store`, `show`, `update`, `destroy`) со строгой изоляцией по `workspace_id` через `GetCurrentWorkspaceHandler` и аутентификацией.
   - Маршруты зарегистрированы в `backend/routes/api.php`.
   - Создан комплекс Feature API тестов `DashboardApiTest` (изоляция тенантов, CRUD, валидация 422, ошибки 401/403/404).

7. **Демонстрационные данные и интеграция:**
   - Создан сидер `DashboardDatabaseSeeder` со стартовыми дашбордами для `ws-1` и `ws-2`, содержащими семантические виджеты выручки, заказов, динамики продаж и остатков. Зарегистрирован в `DatabaseSeeder`.
   - Расширен скрипт `scripts/verify-integration.sh` проверками дашбордов и изоляции рабочих пространств.

8. **Frontend Dashboard Management & Viewer (View Mode):**
   - Реализован типизированный API шлюз `dashboardGateway` (`list`, `getById`, `create`, `update`, `delete`) со строгой изоляцией по `workspace_id` и обработкой ошибок.
   - Разработан адаптер данных виджетов `widgetDataLoader` (`loadWidgetData`, `formatMetricValue`), связывающий семантическую конфигурацию (`dataset`, `metric`, `dimension`, `date_range`) с аналитическими эндпоинтами продаж и складского учёта.
   - Разработано семейство компонентов семантических виджетов: `WidgetKpiCard`, `WidgetLineChart`, `WidgetBarChart`, `WidgetDonutChart`, `WidgetTable` и диспетчер `WidgetRenderer` с поддержкой скелетонов загрузки и состояний ошибок.
   - Реализована адаптивная 12-колоночная сетка `DashboardGrid` с позиционированием по координатам `x, y, w, h`.
   - Создана страница списка дашбордов `/dashboards` (`DashboardListView`) с карточками, модальной панелью (Sheet) создания дашборда и удалением.
   - Создана страница детального просмотра `/dashboards/[id]` (`DashboardViewer`) с кнопкой обновления, возвратом и навигацией.
   - Добавлен пункт `Dashboards` в боковую навигацию (`Sidebar`).
   - Покрыто изолированными юнит- и компонентными тестами (все 23 тестовых сьюта и 82 теста фронтенда успешно проходят, typecheck и lint чистые).

### Что осталось в текущей фазе

1. **Frontend Dashboard Builder UI & Grid Editor (Edit Mode):**
   - Интерактивная 12-колоночная сетка с drag-and-drop и изменением размеров виджетов;
   - Модальное окно добавления/редактирования семантического виджета (выбор dataset, metric, dimension, date_range, параметров);
   - Сохранение и перезагрузка конфигурации дашборда.
2. **E2E тестирование и финальный Integration Checkpoint:**
   - Покрытие builder flows сквозными тестами;
   - Подтверждение всех exit criteria Phase 8.

### Блокеры
- Отсутствуют.

### Следующий шаг
- Реализация задачи: Frontend Dashboard Builder UI & Grid Editor (Edit Mode — интерактивный drag-and-drop, изменение размеров и модалка настройки виджетов).
