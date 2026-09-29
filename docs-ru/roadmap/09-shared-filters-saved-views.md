# Phase 9 — Shared Filters and Saved Views

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Сделать dashboard переиспользуемыми.

## Функциональность

Dashboard-level filters, widget-level filters где нужно, saved presets, reusable date ranges, consistent serialization, restoring saved state.

## Exit Criteria

Filtered view сохраняется/восстанавливается, filter semantics едина, ownership соблюдается, несовместимые combinations обрабатываются явно.

## Integration Checkpoint

Перед завершением этапа пройти [интеграционную проверку](ROADMAP.md#integration-checkpoints).

---

## Прогресс

### Что сделано

1. **OpenAPI 3.0.3 Контракт (`contracts/openapi/analytics-v1.yaml`):**
   - Добавлены маршруты управления сохранёнными представлениями дашборда:
     - `GET /dashboards/{dashboardId}/views` — список сохранённых представлений и фильтр-пресетов;
     - `POST /dashboards/{dashboardId}/views` — создание сохранённого представления;
     - `GET /dashboards/{dashboardId}/views/{viewId}` — получение сохранённого представления;
     - `PUT /dashboards/{dashboardId}/views/{viewId}` — обновление представления (название, фильтры, признак `is_default`);
     - `DELETE /dashboards/{dashboardId}/views/{viewId}` — удаление представления (204 No Content).
   - Спроектированы семантические схемы: `DashboardFilterValues` (`date_range`, `date_from`, `date_to`, `category_id`, `region_id`, `warehouse_id`, `stock_health`), `DashboardSavedView`, `DashboardSavedViewListResponse`, `DashboardSavedViewResponse`, `CreateDashboardSavedViewRequest`, `UpdateDashboardSavedViewRequest`.
   - В схему `WidgetQueryConfig` добавлено свойство `filters` (`DashboardFilterValues`) для поддержки локальных фильтр-оверрайдов на уровне виджета.
   - Валидация контракта пройдена (`npm run contracts:validate`), сгенерированы TypeScript-типы (`npm run api:generate`).
   - Добавлены assertions в `ApiContractTest`.

2. **Domain Layer (`App\Modules\Dashboard\Domain`):**
   - Создан чистый Value Object `SavedViewId` с генерацией UUID v4 (RFC 4122) без сторонних фреймворковых зависимостей.
   - Создан Value Object `DashboardFilters` с проверкой корректности диапазонов дат (`date_from <= date_to`, выброс `InvalidFilterException`) и методами проекции на датасеты: `forSalesDataset()` (отсекает неподдерживаемые `warehouse_id`, `stock_health`) и `forInventoryDataset()` (отсекает неподдерживаемый `region_id`).
   - Создана Entity `SavedView` с инвариантами названия, сериализацией фильтров, управлением признаком `is_default` и временными метками.
   - Определён интерфейс репозитория `SavedViewRepositoryInterface` (`findById`, `findByDashboardId`, `save`, `delete`, `clearDefault`).
   - Созданы доменные исключения `SavedViewNotFoundException` и `InvalidFilterException`.
   - Чистота доменного слоя проверена `ArchitectureTest` (0 запрещённых зависимостей).
   - Покрыто юнит-тестами `SavedViewDomainTest`.

3. **Application Layer (`App\Modules\Dashboard\Application`):**
   - Разработаны DTO: `DashboardFiltersDto`, `SavedViewDto`.
   - Реализованы CQRS команды и обработчики: `CreateSavedViewHandler`, `UpdateSavedViewHandler`, `DeleteSavedViewHandler`.
   - Реализованы CQRS запросы и обработчики: `GetSavedViewsByDashboardHandler`, `GetSavedViewByIdHandler`.
   - Реализована строгая проверка прав доступа: доступ к дашборду и его представлениям разрешён только внутри рабочего пространства пользователя (`workspace_id`), межпространственные запросы отклоняются с `DashboardNotFoundException` / `404` или `403 Forbidden`.
   - Реализована логика эксклюзивности представления по умолчанию (`clearDefault` снимает признак с других представлений дашборда при установке нового).
   - Покрыто юнит-тестами `SavedViewApplicationTest`.

4. **Infrastructure Layer (`App\Modules\Dashboard\Infrastructure`):**
   - Создана миграция PostgreSQL `2026_09_23_000022_create_dashboard_saved_views_table.php` с внешним ключом к таблице `dashboards` (`cascadeOnDelete`), полем `filters` типа `jsonb` и составным индексом `(dashboard_id, created_at)`.
   - Создана Eloquent-модель `DashboardSavedViewModel` с типизацией и кастингом `filters` в массив и `is_default` в boolean.
   - В модель `DashboardModel` добавлена связь `savedViews(): HasMany`.
   - Реализован `EloquentSavedViewRepository` с транзакционной логикой сброса флага по умолчанию.
   - Реализован `InMemorySavedViewRepository` для изолированного модульного тестирования.
   - Зарегистрирован биндинг `SavedViewRepositoryInterface` в `AppServiceProvider`.
   - Покрыто тестами `SavedViewRepositoryTest`.

5. **Presentation Layer (`App\Modules\Dashboard\Presentation`):**
   - Разработаны Form Requests с валидацией входных данных: `CreateSavedViewRequest` и `UpdateSavedViewRequest` (проверка строковых полей, допустимых значений enum периодов, валидности дат и условия `date_to >= date_from`).
   - Реализован REST-контроллер `DashboardSavedViewController` (`index`, `store`, `show`, `update`, `destroy`) с интеграцией `GetCurrentWorkspaceHandler` и стандартизированными кодами ответов (200, 201, 204, 403, 404, 422).
   - Маршруты зарегистрированы в `backend/routes/api.php` внутри защищённой middleware-группы `AuthenticateUserIdMiddleware`.
   - Комплексно протестировано в `DashboardSavedViewApiTest` и `DashboardFilterSemanticsTest`.

6. **Тестирование и интеграция:**
   - Добавлены проверки чистоты семантического контракта фильтров в `DashboardContractSemanticsTest`.
   - Расширен интеграционный bash-скрипт `scripts/verify-integration.sh` шагами создания, чтения, модификации, изоляции и удаления сохранённых представлений дашборда.
   - Все автоматические проверки (`make check`: OpenAPI lint, Prettier, ESLint, TypeScript, Vitest, Pint, PHPStan, PHPUnit) пройдены без ошибок (134 теста бэкенда, 104 теста фронтенда).

7. **Frontend API Gateway & Сериализация фильтров (`frontend/src/features/dashboard/api`):**
   - Расширен `DashboardGateway` методами управления сохранёнными представлениями: `listSavedViews`, `getSavedView`, `createSavedView`, `updateSavedView`, `deleteSavedView`.
   - Покрыто юнит-тестами `dashboard-gateway.test.ts` (11 тестов).

8. **Frontend Доменная логика фильтров и проекция на датасеты (`frontend/src/features/dashboard/model`):**
   - Реализован модуль `filter-resolver.ts`:
     - Резолвинг пресетов дат: `30d`, `90d`, `180d`, `365d`, `all`, `custom`;
     - Слияние фильтров дашборда и локальных оверрайдов виджета (`mergeFilters`);
     - Семантическая проекция и санитизация фильтров по датасетам (`sanitizeFiltersForDataset`): отсечение `warehouse_id` и `stock_health` для Sales, отсечение `region_id` для Inventory;
     - Сравнение на равенство фильтров (`isFiltersEqual`) и проверка активности фильтров (`hasActiveFilters`).
   - Покрыто юнит-тестами `filter-resolver.test.ts` (11 тестов).

9. **Frontend State & Синхронизация с URL (`frontend/src/features/dashboard/model/use-dashboard-filters.ts`):**
   - Разработан хук `useDashboardFilters`:
     - Поддержка активного представления (`activeViewId`), отслеживание изменений относительно сохранённого пресета (`isModifiedFromActiveView`);
     - Двусторонняя синхронизация фильтров с URL query-параметрами (`window.history.replaceState` без лишних перерендеров);
     - Автоматический выбор дефолтного представления (`is_default = true`) при загрузке через чистый derived state;
     - Функции управления: `setFilters`, `patchFilters`, `resetFilters`, `applyView`, `saveCurrentAsView`, `updateCurrentView`, `removeView`, `toggleDefaultView`.
   - Покрыто юнит-тестами `use-dashboard-filters.test.ts` (4 теста).

10. **Frontend Загрузка данных виджетов (`frontend/src/features/dashboard/model/widget-data-loader.ts`):**
    - Обновлён `loadWidgetData`: поддержка фильтров дашборда, объединение с локальными оверрайдами виджета, резолвинг диапазонов дат, санитизация параметров под датасет, маппинг `stock_health` (`low_stock` -> `critical`, `in_stock` -> `optimal`).
    - Покрыто тестами `widget-data-loader.test.ts` (9 тестов).

11. **Frontend UI Компоненты (`frontend/src/features/dashboard/ui`):**
    - `DashboardFilterBar`: панель фильтров с кнопками периодов дат, селекторами `date_from`/`date_to`, выбором категорий, регионов, складов, статуса запасов и кнопкой сброса активных фильтров.
    - `DashboardSavedViewsMenu`: меню выбора сохранённых представлений, индикатор изменённости текущих фильтров (*), создание нового пресета (с возможностью назначения по умолчанию), переключение дефолтного пресета (звёздочка), удаление пресетов.
    - Интеграция в `DashboardViewer` и сквозная передача фильтров через `DashboardGrid` в `WidgetRenderer`.
    - Компоненты покрыты тестами: `dashboard-filter-bar.test.tsx` (3 теста), `dashboard-saved-views-menu.test.tsx` (2 теста), `dashboard-viewer.test.tsx` (4 теста).

12. **Сквозное E2E тестирование (`frontend/src/features/dashboard/ui/dashboard-saved-views-flow.test.tsx`):**
    - Написан комплексный интеграционный тест: автоматическая загрузка дефолтного представления -> смена фильтра периода (30d -> 90d) -> запрос sales overview с обновлённым интервалом -> сохранение нового пресета через модальное меню -> сброс фильтров.
    - Пройден полный цикл валидации frontend (Prettier format:check, ESLint, TypeScript check, 134 теста Vitest).

### Что осталось в текущей фазе

Все запланированные задачи фазы 9 успешно выполнены.

### Блокеры
- Отсутствуют.

### Следующий шаг
- Фаза 9 завершена. Переход к Phase 10 (Data Ingestion) в соответствии с Roadmap.

---

## Проверка завершения

- **Дата завершения:** 2026-09-23
- **Статус:** Выполнено (все exit criteria подтверждены).

### Подтверждение Exit Criteria

1. **Filtered view сохраняется/восстанавливается:**
   - Подтверждено бэкенд-тестами: `DashboardSavedViewApiTest.php` (сохранение через POST, чтение списка через GET, восстановление по ID, обновление через PUT, удаление через DELETE);
   - Подтверждено фронтенд-тестами: `dashboard-saved-views-flow.test.tsx` (сохранение пресета из активных фильтров через меню, восстановление дефолтного пресета при монтировании дашборда);
   - Подтверждено интеграционным скриптом: `scripts/verify-integration.sh` (шаги 29–33).

2. **Filter semantics едина:**
   - Единый контракт `DashboardFilterValues` зафиксирован в OpenAPI 3.0.3 (`date_range`, `date_from`, `date_to`, `category_id`, `region_id`, `warehouse_id`, `stock_health`);
   - Подтверждено бэкенд-тестами семантики: `DashboardFilterSemanticsTest.php` (строгая валидация периодов, проверка `date_from <= date_to`);
   - Подтверждено фронтенд-тестами: `filter-resolver.test.ts` (вычисление дат по пресетам `30d`, `90d`, `180d`, `365d`, `all`, `custom`).

3. **Ownership соблюдается:**
   - Строгая изоляция по `workspace_id` и `user_id` реализована в `DashboardSavedViewController` и `WorkspaceAccessGuard`;
   - Попытки доступа пользователя к чужим представлениям возвращают `403 Forbidden` (`SavedViewApplicationTest.php`, `DashboardSavedViewApiTest.php`, `scripts/verify-integration.sh` шаг 32).

4. **Несовместимые combinations обрабатываются явно:**
   - Реализована проекция параметров по наборам данных:
     - Sales Dataset: отсекаются `warehouse_id` и `stock_health`;
     - Inventory Dataset: отсекается `region_id`;
   - Подтверждено domain-тестами бэкенда (`DashboardFilters::forSalesDataset()`, `DashboardFilters::forInventoryDataset()`);
   - Подтверждено фронтенд-моделью (`filter-resolver.ts: sanitizeFiltersForDataset`).

### Выполненные проверки

- `npm run contracts:validate` — OpenAPI валиден (0 ошибок).
- `npm run lint`, `format:check`, `typecheck` — Frontend статический анализ чист (0 ошибок).
- `npm test` — 33 тестовых файла, 134 теста Vitest успешно пройдены.
- `npm run build` — Production сборка Next.js 16 собрана без ошибок.
- `composer validate --strict` — `composer.json` валиден.
- `composer lint` — Pint и Larastan (максимальный уровень) без замечаний.
- `composer test` — 134 теста PHPUnit (28141 assertions) успешно пройдены.
- `scripts/verify-integration.sh` — Все 35 сквозных шагов интеграции пройдены успешно.
