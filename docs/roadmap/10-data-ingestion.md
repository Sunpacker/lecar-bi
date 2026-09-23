# Phase 10 — Data Ingestion

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Перейти к реальному import pipeline.

## Функциональность

Upload, validation, import record, staging, async processing, progress/status, validation failures, normalization, analytics projection update, safe retry.

Использовать Laravel queues. Невалидный import не должен повреждать валидные данные.

## Распределение

Gemini Pro анализирует formats/validation. Claude реализует или review bounded context. GPT-5.6 отвечает за pipeline integration и projection strategy.

## Exit Criteria

Import работает, progress виден, invalid rows понятны, retries безопасны, duplicate processing не портит данные, projection rebuild протестирован. Сверить с `docs/architecture/06-data-and-analytics.md` и `08-events-outbox-async.md`.

## Integration Checkpoint

Перед завершением этапа пройти [интеграционную проверку](ROADMAP.md#integration-checkpoints).

---

## Прогресс

### Что сделано

1. **DTOs и репозиторий Staging (`App\Modules\DataIngestion`):**
   - Разработаны DTO: `ImportBatchDto`, `ImportFailureDto`, `PaginatedListDto`.
   - Доменная модель `RowError` расширена опциональными полями `id` и `createdAt` для точного отслеживания.
   - Спроектирован интерфейс `StagingRecordRepositoryInterface` и реализован `EloquentStagingRecordRepository` для работы с таблицами `staging_sales_records` и `staging_inventory_records`:
     - пакетная вставка сырых записей со статусом `pending`;
     - постраничная выборка записей по батчу и статусу;
     - обновление статуса записей (`projected`, `failed`).
   - Реализован `InMemoryStagingRecordRepository` для быстрого изолированного тестирования.
   - Зарегистрированы биндинги в `AppServiceProvider`.
   - Покрыто тестами в `StagingRepositoryTest`.

2. **Application CQRS Queries (`App\Modules\DataIngestion\Application\Queries`):**
   - Реализованы CQRS-запросы и обработчики с multi-tenant изоляцией по `workspace_id`:
     - `GetImportBatchesQuery` / `GetImportBatchesHandler` — постраничный список батчей импорта с фильтрацией по статусу;
     - `GetImportBatchByIdQuery` / `GetImportBatchByIdHandler` — детальная информация о батче;
     - `GetImportFailuresQuery` / `GetImportFailuresHandler` — постраничный список ошибок валидации конкретного батча.
   - Покрыто тестами в `ImportQueriesTest`.

3. **Application CQRS Commands, Queue Pipeline & Star Schema Projection:**
   - Спроектирован интерфейс проекции `StarSchemaProjectorInterface` и реализован `StarSchemaProjector` для переноса валидированных записей из staging в аналитические таблицы (`fact_sales`, `dim_products`, `dim_customers`, `dim_channels`, `inventory_snapshots`).
   - Реализован `InMemoryStarSchemaProjector` для тестов.
   - Разработана асинхронная очередь Laravel:
     - `ImportJobDispatcherInterface` и `QueueImportJobDispatcher`;
     - `ProcessImportJob` (`ShouldQueue`) с автоматическим делегированием обработки команде `ProcessImportBatchCommand`.
   - Реализована команда `UploadImportBatchCommand` / `UploadImportBatchHandler`:
     - создание агрегата `ImportBatch` в статусе `pending`;
     - сохранение исходного файла в хранилище `storage/app/imports`;
     - диспатч задачи в очередь обработки.
   - Реализована команда `ProcessImportBatchCommand` / `ProcessImportBatchHandler`:
     - потоковый разбор CSV-файлов;
     - построчная валидация доменными спецификаторами датасетов (`sales`, `inventory`);
     - сохранение невалидных строк в `import_failures`;
     - сохранение валидных строк в `staging_*_records` со статусом `pending`;
     - проекция валидных строк в таблицы Star Schema со сменой статуса на `projected`;
     - фиксация прогресса и переход в статус `completed`, `completed_with_errors` (если часть строк не прошла валидацию) или `failed` (если все строки ошибочны);
     - гарантия сохранения консистентности: ошибочные строки не повреждают валидные данные.
   - Реализована команда безопасного повтора `RetryImportBatchCommand` / `RetryImportBatchHandler`:
     - очистка предыдущих ошибок `import_failures` и staging-записей батча;
     - сброс статуса батча в `pending` и обнуление счётчиков;
     - повторный запуск задачи в очереди.
   - Покрыто тестами в `ImportCommandsTest`.

4. **Presentation REST API (`App\Modules\DataIngestion\Presentation`):**
   - Разработан `UploadImportRequest` с валидацией файла (CSV, до 50 МБ) и типа датасета (`sales`, `inventory`), с возвратом структурированных ошибок 422 JSON.
   - Реализован контроллер `ImportBatchController`:
     - `GET /api/v1/imports` — список батчей с фильтрацией и пагинацией;
     - `POST /api/v1/imports` — загрузка нового файла и запуск импорта (201 Created);
     - `GET /api/v1/imports/{id}` — статус и прогресс импорта;
     - `GET /api/v1/imports/{id}/failures` — список ошибок валидации батча;
     - `POST /api/v1/imports/{id}/retry` — повторная обработка батча (202 Accepted).
   - Зарегистрированы маршруты в `backend/routes/api.php` внутри группы `AuthenticateUserIdMiddleware`.
   - Покрыто тестами в `ImportBatchApiTest`.

5. **End-to-End Pipeline & Интеграционное тестирование:**
   - Реализован сквозной тест `ImportPipelineExecutionTest`:
     - проверка успешной загрузки и полного переноса данных в Star Schema;
     - проверка частичного импорта (completed_with_errors) с изоляцией ошибочных строк;
     - проверка safe retry с очисткой предыдущих ошибок и повторной проекцией;
     - подтверждение идемпотентности и защиты от порчи данных.
   - В `backend/phpunit.xml` добавлена конфигурация `QUEUE_CONNECTION=sync` для детерминированного тестирования очередей.
   - Все 194 теста бэкенда (28 396 assertions) проходят успешно.
   - Статический анализ PHPStan (Level Max) и форматирование Laravel Pint пройдены с 0 ошибок.

6. **Frontend Data Ingestion UI (`frontend/src/features/data-ingestion`):**
   - Разработан типизированный REST-шлюз `importGateway` поверх OpenAPI 3.0.3 (`getBatches`, `uploadBatch`, `getBatch`, `getFailures`, `retryBatch`).
   - Реализован Drag & Drop компонент `ImportUploadDropzone` с выбором набора данных (`sales`, `inventory`), валидацией расширений (.csv, .json, .jsonl) и лимита 50 МБ.
   - Реализованы бейджи статусов `ImportStatusBadge` и список батчей `ImportBatchList` с динамическими индикаторами прогресса и кнопкой повтора `Retry`.
   - Реализована слайд-овер панель `ImportBatchDetailSheet` с KPI-карточками, информацией об ошибке пакета, пагинированной таблицей ошибок валидации строк и кнопкой Retry.
   - Реализовано клиентское представление `DataIngestionView` с тихим автополлингом (каждые 3 сек для активных процессов) и предотвращением мерцания пустого состояния через скелетон загрузки.
   - Реализован серверный маршрут App Router `frontend/app/(dashboard)/imports/page.tsx` с аутентификацией сессии и резолвингом воркспейса.
   - Добавлен пункт навигации «Импорт данных» с иконкой `UploadCloud` в `Sidebar`.
   - Полное покрытие unit-, компонентными и сквозными flow-тестами (все 162 теста фронтенда успешно проходят).

### Что осталось

1. **Интеграционный checkpoint фазы 10:**
   - Сквозной интеграционный сценарий (загрузка CSV через API -> асинхронная очередь -> staging -> star schema -> проверка обновления витрин продаж/склада).
   - Проверка чекпоинта по `ROADMAP.md#integration-checkpoints`.

### Следующий шаг

- Провести интеграционный checkpoint фазы 10 и закрыть Phase 10.


