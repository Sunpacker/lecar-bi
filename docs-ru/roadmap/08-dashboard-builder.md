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

9. **Frontend Dashboard Builder UI & Grid Editor (Edit Mode):**
   - Разработан хук состояния и логики сетки `useDashboardBuilder` (`frontend/src/features/dashboard/model/use-dashboard-builder.ts`):
     - Управление режимами (`view` / `edit`), редактирование названия и описания;
     - Строгие инварианты 12-колоночной сетки (`0 <= x <= 11`, `1 <= w <= 12`, `x + w <= 12`, `y >= 0`, `1 <= h <= 24`);
     - Добавление, редактирование, удаление, перемещение (`moveWidget`) и изменение размеров (`resizeWidget`) виджетов;
     - Отслеживание изменений (`isDirty`), сохранение через `dashboardGateway.update` и сброс изменений (`discardChanges`).
   - Разработана slide-over панель настройки виджетов `WidgetConfigSheet` (`frontend/src/features/dashboard/ui/widget-config-sheet.tsx`):
     - Семантическая настройка: название, тип визуализации (`kpi_card`, `line_chart`, `bar_chart`, `donut_chart`, `table`), датасет (`sales`, `inventory`), метрики, измерения (`dimension`), периоды дат (`date_range`) и сеточные размеры;
     - Динамическое переключение доступных метрик и разрезов при смене набора данных;
     - Идиоматический сброс состояния формы по ключу (React 19 без лишних side-effects).
   - Разработан интерактивный редактор сетки `DashboardGridEditor` и карточка `WidgetEditorCard` (`frontend/src/features/dashboard/ui/dashboard-grid-editor.tsx`, `widget-editor-card.tsx`):
     - Тулбар с ручкой перетаскивания (drag handle), названием, бейджем типа, кнопками настройки и удаления;
     - Нижняя панель с кнопками позиционирования (влево, вправо, вверх, вниз) с блокировкой на краях сетки;
     - Кнопки пошагового изменения ширины (`w`) и высоты (`h`);
     - Поддержка HTML5 drag-and-drop для перемещения и перестановки виджетов;
     - Пустое состояние сетки с кнопкой создания первого виджета.
   - Обновлен компонент `DashboardViewer` (`frontend/src/features/dashboard/ui/dashboard-viewer.tsx`):
     - Тулбар переключения режимов «Просмотр» / «Редактировать»;
     - В режиме редактирования: редактируемые поля названия и описания дашборда, бейдж «Несохранённые изменения», кнопки «Добавить виджет», «Сохранить» (с индикацией статуса) и «Отмена»;
     - Переключение отображения между `DashboardGrid` и `DashboardGridEditor`, монтирование `WidgetConfigSheet`.
   - Комплексно покрыто тестами:
     - Юнит-тесты хука `use-dashboard-builder.test.ts` (8 тестов);
     - Компонентные тесты `widget-config-sheet.test.tsx` (4 теста);
     - Компонентные тесты редактора сетки `dashboard-grid-editor.test.tsx` (4 теста);
     - Интеграционные тесты `dashboard-viewer.test.tsx` (3 теста);
     - Полный прогон фронтенд-тестов: 26 файлов, 100 тестов проходят без ошибок, `typecheck`, `lint` и `format:check` чистые (0 ошибок);
     - Backend-тесты: 114 тестов (27997 assertions) и Pint/PHPStan без ошибок.

10. **E2E тестирование и финальный Integration Checkpoint:**
    - Разработан сквозной E2E integration test пользовательских сценариев редактора `DashboardViewer` (`frontend/src/features/dashboard/ui/dashboard-builder-flow.test.tsx`):
      - Полный цикл редактирования: переключение в режим редактирования, изменение метаданных (заголовок, описание), добавление семантического виджета через `WidgetConfigSheet`, сдвиг виджета по 12-колоночной сетке и изменение размеров, сохранение через `dashboardGateway.update` с валидацией чистоты отправляемого контракта;
      - Сценарий отмены и сброса изменений без модификации исходного состояния дашборда.
    - Разработан сквозной integration test управления списком дашбордов (`frontend/src/features/dashboard/ui/dashboard-list-flow.test.tsx`):
      - Создание нового дашборда через форму в Sheet с валидацией вызова `dashboardGateway.create` и добавлением карточки в DOM;
      - Удаление дашборда с подтверждением в диалоговом окне браузера и вызовом `dashboardGateway.delete`.
    - Разработан тест чистоты семантического контракта и защиты от утечки frontend-состояний (`backend/tests/Feature/Modules/Dashboard/DashboardContractSemanticsTest.php`):
      - Проверка OpenAPI-схем `WidgetInput`, `WidgetGridPosition`, `WidgetQueryConfig` на отсутствие UI-специфичных полей (`className`, `style`, `pixelWidth`, `domId` и т.д.);
      - Проверка API на очистку и отсечение лишних frontend-свойств при обновлении и получении дашбордов;
      - Проверка изоляции тенантов и защиты владения (cross-workspace update / delete возвращает 403 Forbidden).
    - Расширен интеграционный скрипт `scripts/verify-integration.sh` проверками полного жизненного цикла CRUD для дашбордов:
      - Создание пользовательского дашборда через `POST /api/v1/dashboards`;
      - Чтение и восстановление через `GET /api/v1/dashboards/{id}`;
      - Обновление конфигурации виджетов и перемещение через `PUT /api/v1/dashboards/{id}`;
      - Проверка изоляции прав доступа (попытка доступа от `user-2` возвращает 403 Forbidden);
      - Аутентифицированная проверка рендеринга страниц `/dashboards` и `/dashboards/{id}` на фронтенде;
      - Удаление через `DELETE /api/v1/dashboards/{id}` и проверка 404 Not Found при повторном чтении.

### Что осталось в текущей фазе

Все запланированные задачи фазы 8 успешно выполнены.

### Блокеры
- Отсутствуют.

### Следующий шаг
- Фаза 8 завершена. Переход к Phase 9 (Shared Filters and Saved Views) в соответствии с Roadmap.

---

## Проверка завершения

- **Дата завершения:** 2026-09-23
- **Статус:** Выполнено (все exit criteria подтверждены).

### Подтверждение Exit Criteria
1. **Dashboard собирается и восстанавливается:**
   Подтверждено юнит-, компонентными, интеграционными и сквозными тестами. Пользователь может создавать дашборды, добавлять любые семантические типы виджетов (`kpi_card`, `line_chart`, `bar_chart`, `donut_chart`, `table`), настраивать их источники (`sales`, `inventory`), метрики, временные диапазоны, свободно перемещать и масштабировать блоки в 12-колоночной сетке (`x, y, w, h`), сохранять состояние на бэкенде и восстанавливать при загрузке.
2. **Ownership enforced:**
   Подтверждено тестами `DashboardApiTest`, `DashboardContractSemanticsTest` и скриптом `scripts/verify-integration.sh`. Дашборды строго привязаны к `workspace_id` и `user_id`. Межпространственные запросы чтения, обновления и удаления немедленно пресекаются со статусом `403 Forbidden`.
3. **Frontend-specific state не протекает в public contract без причины:**
   Подтверждено автоматическим тестом `DashboardContractSemanticsTest::test_openapi_contract_does_not_leak_frontend_state` и валидацией схем `WidgetInput`, `WidgetGridPosition`, `WidgetQueryConfig`. Контракт оперирует исключительно доменными концептами без примеси React-специфики, CSS-стилей или пиксельной разметки.
4. **Основные builder flows покрыты E2E:**
   Разработан комплекс сквозных тестов:
   - `dashboard-builder-flow.test.tsx` (полный цикл View/Edit режимов, создание, позиционирование, ресайз, сохранение, сброс);
   - `dashboard-list-flow.test.tsx` (создание и удаление дашбордов из каталога);
   - `scripts/verify-integration.sh` (сквозной цикл создания, чтения, модификации, изоляции и удаления через HTTP API и Next.js SSR).

### Результаты автоматических проверок (`make check`)
- `npm --prefix frontend run contracts:validate`: OK (OpenAPI 3.0.3 valid)
- `npm --prefix frontend run format:check`: OK (Prettier — all files formatted)
- `npm --prefix frontend run lint`: OK (ESLint 0 errors)
- `npm --prefix frontend run typecheck`: OK (TypeScript 0 errors)
- `npm --prefix frontend test`: OK (28 test files, 104 tests passed)
- `npm --prefix frontend run build`: OK (Next.js 16 production build succeeded, all static and dynamic routes compiled)
- `composer --working-dir=backend validate --strict`: OK (composer.json valid)
- `composer --working-dir=backend lint`: OK (Pint + PHPStan level max 0 errors)
- `composer --working-dir=backend test`: OK (117 tests, 28,037 assertions passed)
