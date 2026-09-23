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

### Что осталось в текущей фазе

1. Frontend UI: разработка панели фильтров дашборда (`DashboardFilterBar`) с поддержкой пресетов дат (`30d`, `90d`, `180d`, `365d`, `all`, custom range), фильтрами по категориям, регионам и складам.
2. Frontend UI: меню управления сохранёнными представлениями (Saved Views Selector) в `DashboardViewer`: сохранение текущей комбинации фильтров, переключение между сохранёнными пресетами, удаление пресетов, отображение дефолтного представления.
3. Frontend State & Loader: передача фильтров дашборда в `loadWidgetData` с учётом виджетных переопределений и автоматическим отсечением несовместимых фильтров датасетов.
4. Синхронизация активных фильтров с URL query-параметрами.
5. Сквозные E2E-тесты и закрытие чекпоинта Phase 9.

### Блокеры
- Отсутствуют.

### Следующий шаг
- Разработка UI-компонентов Shared Filters и селектора Saved Views на фронтенде (`frontend/src/features/dashboard`).
