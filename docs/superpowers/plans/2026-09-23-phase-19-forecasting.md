# Phase 19 — Forecasting Extension

## Кратко

Добавить в analytics service отдельный bounded context `Forecasting`, который строит и сохраняет воспроизводимые прогнозы спроса по паре товар–склад, оценивает их на истории без утечки будущих данных и на их основе рассчитывает ожидаемое исчерпание остатка и дату повторного заказа.

Первая версия остаётся в Laravel и использует прозрачные статистические модели. Отдельный Python/ML-сервис не создаётся: текущая задача не требует другого runtime, независимого масштабирования или отдельного deployment lifecycle. Решение о выделении допускается только после измеренного ограничения PHP-реализации или появления моделей, для которых действительно нужен Python ecosystem.

Цепочка данных:

`fact_order_items + fact_orders → observed daily demand → walk-forward evaluation → selected model → immutable forecast run → forecast API → Next.js`

Для stock planning:

`latest fact_inventory_daily + demand forecast + historical supplier lead time → depletion range + reorder timing`

Forecast всегда маркируется как оценка, а не как измеренный факт. API и UI обязаны показывать дату среза, модель, качество данных, assumptions, интервал неопределённости и результат historical evaluation.

## Цель, scope и критерии готовности

### Результат

- В `backend/app/Modules/Forecasting/**` существует изолированный bounded context со своими Domain, Application, Infrastructure и Presentation слоями.
- Для каждой доступной пары товар–склад строится прогноз дневного спроса на 60 дней; API может вернуть срезы 14, 30 или 60 дней без повторного расчёта.
- Каждая серия проходит rolling-origin backtest, сравнение с baseline и получает сохранённые метрики качества.
- Прогноз содержит центральную оценку и 80% prediction interval, построенный по ошибкам backtest; нижняя граница не бывает отрицательной.
- Stock depletion и reorder timing строятся только при достаточном качестве входных данных; невозможный расчёт возвращает явную причину, а не выдуманное число.
- Forecast runs неизменяемы, versioned и воспроизводимы по `workspace_id`, `as_of_date`, `model_version` и входному cutoff.
- Frontend имеет самостоятельный раздел `/forecasting`, где measured, backtest и future forecast визуально и текстово различаются.
- Forecast-vs-actual показывает как walk-forward backtest, так и фактические значения, появившиеся после ранее сохранённого forecast run.
- Полный integration checkpoint подтверждает отсутствие data leakage, workspace isolation, interval semantics, идемпотентную генерацию и корректные no-data/insufficient-data состояния.

### Разрешённый write scope будущей реализации

- `backend/app/Modules/Forecasting/**`
- `backend/database/migrations/**` — только таблицы forecasting и необходимые индексы существующих fact tables
- `backend/tests/**/Modules/Forecasting/**`
- `backend/routes/api.php`
- `backend/app/Providers/AppServiceProvider.php`
- `backend/config/modules.php`
- `backend/config/forecasting.php`
- `backend/routes/console.php`
- `backend/database/seeders/**` — только расширение детерминированного forecast demo/evaluation scenario
- `contracts/openapi/analytics-v1.yaml`
- `frontend/app/(dashboard)/forecasting/**`
- `frontend/src/features/forecasting/**`
- `frontend/src/shared/api/generated/schema.ts` — только через генератор
- `frontend/src/shared/ui/layout/sidebar.tsx`
- `scripts/verify-integration.sh`
- `docs/architecture/{03-frontend-nextjs,05-bounded-contexts,06-data-and-analytics,07-api-and-integration,09-infrastructure-deployment-observability,10-testing-and-quality,12-architecture-decisions}.md`
- `docs/architecture/forecasting-methodology.md`
- `docs/roadmap/{19-forecasting,ROADMAP}.md` — только на финальном checkpoint
- `README.md` и `infra/.env.example` — только если добавляется пользовательская конфигурация forecasting runtime

### Запрещённый scope

- отдельный forecasting microservice без подтверждённой эксплуатационной причины;
- Python runtime, Jupyter, внешняя ML-платформа, LLM или AI-provider;
- нейросети, автоматический hyperparameter search и black-box AutoML;
- закупочные заказы, автоматическая отправка заказов поставщикам и изменение запасов;
- прогноз выручки, цены или supplier reliability — Phase 19 прогнозирует только спрос в единицах товара;
- изменение формул существующих Sales, Inventory, ABC/XYZ и Supplier Analytics;
- использование frontend как источника forecast/reorder бизнес-правил;
- синхронный тяжёлый forecast calculation внутри HTTP request;
- подмена отсутствующих или некачественных данных «уверенным» прогнозом;
- breaking changes существующих OpenAPI endpoints.

## Обязательный preflight-gate

Phase 19 не начинается, пока не выполнены все условия:

- Phase 0–18 отмечены `[x]` в `docs/roadmap/ROADMAP.md`, а Phase 18 содержит пройденный production-hardening checkpoint.
- Существующие Sales, Inventory, Supplier и Data Ingestion integration tests проходят.
- Phase 16 зафиксировала performance budgets и индексы для аналитических запросов; Phase 17 предоставляет метрики job duration/failure и API latency; Phase 18 предоставляет production-safe scheduler/worker lifecycle.
- В PostgreSQL доступны:
  - `fact_order_items.quantity`, `order_date`, `product_id`, `warehouse_id`, `workspace_id`;
  - `fact_orders.status` для исключения cancelled/non-completed demand;
  - `fact_inventory_daily.quantity_available`, `reorder_point`, `snapshot_date`;
  - `fact_supplier_deliveries.product_id`, `warehouse_id`, `lead_time_days`, `actual_delivery_date`;
  - workspace-scoped product, warehouse и category dimensions.
- Demo dataset содержит минимум 365 дней продаж, не менее 90 дней inventory snapshots и детерминированную сезонность. Текущий baseline этим условиям соответствует: продажи за 2025 год и inventory snapshots за Q4 2025.
- Forecast generation может выполняться queue worker и scheduler без добавления нового broker.
- OpenAPI validation, generated TypeScript client и contract drift checks находятся в зелёном состоянии.

Если любой gate не выполнен, сначала закрывается соответствующий exit criterion предыдущей фазы. Phase 19 не исправляет production foundation попутно.

## Архитектурные решения

### Граница bounded context

- Создать `Forecasting` как отдельный bounded context внутри существующего Laravel analytics service.
- `Forecasting` не импортирует внутренние DTO, repositories или Domain classes `SalesAnalytics`, `InventoryAnalytics`, `SupplierAnalytics` и `DataIngestion`.
- Historical demand, inventory snapshot и supplier lead-time samples поступают через Forecasting-owned Application ports, реализованные SQL adapters в Infrastructure.
- Domain содержит алгоритмы прогнозирования, evaluation, uncertainty и stock projection и не зависит от Laravel, Eloquent, Carbon, queue или HTTP.
- Forecasting использует те же analytics PostgreSQL и deployable unit, что и остальной backend; отдельная БД не создаётся, пока модуль не выделен в самостоятельный сервис.
- Решение «оставить Forecasting внутри analytics service» и измеримые условия будущего выделения фиксируются следующим ADR. Сам факт возможного будущего выделения не считается причиной создавать сервис сейчас.

### Семантика спроса и качества данных

- Target variable — сумма `fact_order_items.quantity` по календарному дню для конкретных `workspace_id + product_id + warehouse_id`.
- Учитываются только orders со статусом, который опубликован Sales contract как завершённая продажа. Cancelled, draft и иные незавершённые статусы исключаются явно.
- `as_of_date` — последний календарный день, для которого одновременно доступны допустимые sales observations и inventory snapshot. Forecast не использует строки после cutoff.
- День считается наблюдаемым, если workspace-level sales dataset подтверждает покрытие этого дня. Ноль по конкретному SKU допустим только внутри наблюдаемого дня; неизвестный день не превращается в нулевой спрос.
- До появления отдельного source coverage contract наблюдаемость определяется workspace-level activity и документируется как ограничение v1. Серия с покрытием менее 90% training window получает `insufficient_coverage`.
- Минимальная история для прогноза — 84 наблюдаемых дня, evaluation window — последние 28 наблюдаемых дней. Значения задаются `config/forecasting.php`, попадают в `model_version` и не меняются скрыто.
- Серия без достаточной истории возвращает `insufficient_history`; API не подменяет её workspace/category average.

### Модели и выбор модели

Первая версия использует небольшой прозрачный portfolio без новых dependencies:

1. `seasonal_naive_7` — прогноз равен наблюдению того же дня недели предыдущей недели.
2. `weekday_mean_8` — среднее последних восьми наблюдений соответствующего дня недели.
3. `croston_sba` — baseline для intermittent demand с большим количеством нулевых дней.

Для каждой серии выполняется expanding-window rolling-origin evaluation на одинаковых origins. На каждом origin модель обучается только на данных до origin и прогнозирует следующий день.

Выбор модели:

- primary metric — MAE;
- WAPE рассчитывается только при ненулевой сумме actual demand;
- дополнительно сохраняются bias и empirical interval coverage;
- при статистически равном MAE выбирается более простая модель в порядке `seasonal_naive_7 → weekday_mean_8 → croston_sba`;
- selected model никогда не хуже опубликованного `seasonal_naive_7` baseline по primary metric: иначе используется baseline;
- zero-only history не превращается в ложную точность: серия помечается `no_observed_demand`, central forecast равен нулю, а reorder recommendation недоступна.

Алгоритмы и параметры входят в строковый `model_version`, например `demand-v1-84d-28o-p80`. Изменение формулы, окна, interval policy или selection rule требует новой версии; старые runs не пересчитываются задним числом.

### Uncertainty representation

- API хранит `lower`, `estimate`, `upper` для каждой forecast point.
- В v1 это empirical 80% prediction interval по rolling-origin residuals выбранной модели.
- Границы нормализуются правилом `0 <= lower <= estimate <= upper`; округление выполняется только на API boundary.
- Если residual sample недостаточен, interval не фабрикуется: серия получает `uncertainty_unavailable` и nullable bounds.
- API публикует `interval_level = 0.8`, фактический `interval_coverage` backtest и количество evaluation points.
- Daily bounds являются marginal empirical interval. Depletion dates ниже — сценарии по upper/central/lower demand paths, а не обещание 80% вероятности конкретной даты исчерпания.
- UI использует пунктир/штриховку для forecast, полупрозрачную область для interval и сплошную линию для measured actual; один цвет без legend не считается достаточным различием.
- Копирайтинг использует формулировки «прогноз», «диапазон», «ожидается», но не «будет» или «гарантировано».

### Stock depletion и reorder timing

- Starting stock — `quantity_available` последнего inventory snapshot на `as_of_date` для той же пары товар–склад.
- Projected stock рассчитывается вычитанием cumulative forecast demand из starting stock; отрицательные значения отображаются как ноль.
- Incoming purchase orders и future receipts в v1 отсутствуют в модели и явно перечисляются в assumptions.
- Depletion range содержит:
  - `earliest_date` по upper-demand path;
  - `expected_date` по central estimate;
  - `latest_date` по lower-demand path;
  - nullable значение, если соответствующий path не исчерпывает stock в пределах 60 дней.
- Reorder threshold — существующий `fact_inventory_daily.reorder_point`, а не новая frontend-константа.
- Effective lead time — p80 фактической разницы `actual_delivery_date - order_date` завершённых deliveries той же пары товар–склад за последние 365 дней при минимум трёх observations. Справочное `lead_time_days` не выдаётся за фактический срок.
- Если lead-time history недостаточна, demand/depletion forecast остаётся доступным, но reorder timing возвращает `insufficient_lead_time_history`.
- `recommended_order_date` равна дате пересечения reorder threshold по upper-demand path минус effective lead time. Дата на cutoff или в прошлом маркируется `overdue`, но не запускает закупку.
- Forecasting не рассчитывает order quantity и не выбирает supplier: в текущей схеме нет явного product–supplier ownership contract.

### Persistence и lifecycle forecast run

Добавить три таблицы:

1. `forecast_runs`:
   - UUID `id`, `workspace_id`, `as_of_date`, `horizon_days`, `model_version`;
   - `status = pending|running|completed|failed`;
   - `input_sales_through`, `inventory_snapshot_date`, timestamps;
   - `assumptions` JSONB и sanitized failure code/message;
   - unique key `workspace_id + as_of_date + model_version`.
2. `forecast_series`:
   - UUID `id`, `run_id`, `product_id`, `warehouse_id`;
   - selected model, history/evaluation bounds, observation counts и data-quality status;
   - MAE, nullable WAPE, bias, interval coverage, baseline MAE;
   - starting stock, reorder point, effective lead time;
   - depletion dates, reorder date/status и unavailability reasons;
   - unique key `run_id + product_id + warehouse_id`.
3. `forecast_points`:
   - `series_id`, `point_date`, `point_kind = backtest|forecast`;
   - immutable `estimate`, nullable `lower`/`upper`;
   - `actual_quantity` сохраняется для backtest point; для future forecast actual подмешивается query adapter после появления факта;
   - unique key `series_id + point_kind + point_date`.

Дополнительные правила:

- API читает только `completed` runs; partial/failed run никогда не становится текущим.
- Generation job идемпотентна по unique key. Повторный запуск completed run является no-op, failed run можно перезапустить явной командой.
- Один workspace обрабатывается одной unique job/lock; разные workspaces могут считаться параллельно.
- Серии обрабатываются bounded chunks, а не загружаются всем dataset в PHP memory.
- Heavy aggregation выполняется в PostgreSQL. Domain получает одну нормализованную серию ограниченной длины.
- `forecasts:generate --workspace=... --as-of=...` поддерживает детерминированный backfill/test run; production scheduler использует последний допустимый cutoff.
- Ежедневный scheduler только dispatches jobs. HTTP endpoints никогда не запускают расчёт.
- Старые runs очищаются отдельной retention command по `FORECAST_RETENTION_DAYS=180`; running/failed diagnostics и последний completed run каждого workspace не удаляются ошибочно.
- Structured logs/metrics содержат run ID, workspace ID, model version, series counts, duration, outcome и failure code, но не raw business series.

## Публичный API-контракт

Все endpoints находятся под существующей `/api/v1` boundary, используют текущую аутентификацию и workspace middleware и описываются в `contracts/openapi/analytics-v1.yaml` до backend/frontend реализации.

### `GET /analytics/forecasting/summary`

Query:

- `horizon_days`: `14|30|60`, default `30`;
- `warehouse_id`, `category_id` — optional;
- `as_of_date` — optional exact completed run cutoff, default latest completed.

Response содержит:

- run metadata: `run_id`, `as_of_date`, `generated_at`, `model_version`, `input_sales_through`, `inventory_snapshot_date`;
- forecast metadata: `horizon_days`, `interval_level`, assumptions;
- coverage: total/ready/insufficient series и data-quality reasons;
- risk counts: expected depletion, possible depletion by upper path, overdue reorder, unavailable reorder;
- evaluation summary: weighted MAE, nullable WAPE, bias, interval coverage и baseline comparison.

### `GET /analytics/forecasting/items`

Query:

- те же horizon/filter параметры;
- `risk = all|depletion|reorder_due|insufficient_data`;
- `search`, `page`, `per_page`;
- `sort_by = expected_depletion_date|recommended_order_date|forecast_demand|mae|product_name`;
- `sort_direction = asc|desc`.

Каждый item содержит identity товара/склада, data-quality status, cumulative demand interval, starting/projected stock, depletion range, reorder timing, selected model и краткие evaluation metrics.

### `GET /analytics/forecasting/items/{productId}`

Обязательный query `warehouse_id`; optional `horizon_days` и `as_of_date`.

Response содержит:

- metadata и assumptions выбранной серии;
- measured history;
- backtest points с actual/estimate/interval;
- future forecast points;
- realized forecast-vs-actual points из последнего более раннего completed run, чей horizon уже пересекается с доступным actual period;
- metrics, baseline comparison, data-quality diagnostics;
- stock projection, depletion range и reorder explanation.

### `GET /analytics/forecasting/filters`

Возвращает доступные warehouses, categories, completed run cutoffs, supported horizons и latest completed run metadata. Product search остаётся серверным параметром items endpoint и не требует выгрузки всего справочника.

### Ошибки и совместимость

- `404 forecast_not_available` — completed run для workspace/cutoff отсутствует;
- `404 forecast_series_not_found` — product/warehouse не входят в выбранный run;
- `409 forecast_stale` не используется: stale age возвращается metadata/warning, чтобы read endpoint оставался доступным;
- `422` — неизвестный horizon, filter, sort или некорректная дата;
- существующие endpoints не изменяются;
- generated TypeScript types не дополняются вручную.

## План реализации

### Task 1. Зафиксировать methodology, boundary и ADR

**Files:**

- Create: `docs/architecture/forecasting-methodology.md`
- Modify: `docs/architecture/03-frontend-nextjs.md`
- Modify: `docs/architecture/05-bounded-contexts.md`
- Modify: `docs/architecture/06-data-and-analytics.md`
- Modify: `docs/architecture/07-api-and-integration.md`
- Modify: `docs/architecture/09-infrastructure-deployment-observability.md`
- Modify: `docs/architecture/10-testing-and-quality.md`
- Modify: `docs/architecture/12-architecture-decisions.md`

- [ ] Описать target, completed-order semantics, cutoff, observed/missing day policy и minimum data requirements.
- [ ] Формально описать три candidate models, rolling-origin procedure, tie-break, baseline rule и метрики.
- [ ] Описать empirical interval, depletion/reorder formulas и все допущения v1.
- [ ] Добавить `Forecasting` в список bounded contexts и зафиксировать запрет прямых импортов соседних модулей.
- [ ] Добавить ADR о сохранении Forecasting внутри analytics service и условиях будущего extraction.
- [ ] Зафиксировать UI semantics measured/backtest/forecast и accessibility requirement не полагаться только на цвет.

Acceptance criteria:

- Один и тот же набор входных данных однозначно приводит к одной модели, точкам и метрикам.
- Документация не обещает probabilistic certainty, которой алгоритм не вычисляет.
- Условия выделения сервиса измеримы: runtime/library need, independent scaling или deployment lifecycle.

### Task 2. Сначала опубликовать OpenAPI contract

**Files:**

- Modify: `contracts/openapi/analytics-v1.yaml`
- Generate: `frontend/src/shared/api/generated/schema.ts`
- Modify: `contracts/README.md`

- [ ] Добавить четыре forecasting endpoints и все request/response schemas.
- [ ] Использовать отдельные schemas для measured, backtest и future forecast points.
- [ ] Сделать uncertainty bounds nullable только вместе с явным `uncertainty_status`.
- [ ] Описать `data_quality_status`, reason codes, reorder availability и nullable depletion dates.
- [ ] Зафиксировать units (`quantity/day`, calendar date, days, interval level) в schema descriptions.
- [ ] Перегенерировать TypeScript client только штатной командой.
- [ ] Проверить backward compatibility существующего API.

Acceptance criteria:

- Redocly lint и contract generation проходят.
- Contract позволяет UI честно показать no-data, stale, uncertainty-unavailable и insufficient-lead-time состояния без догадок.
- Backend и frontend implementation начинается только после freeze этого contract.

### Task 3. Создать Forecasting module и чистые Domain algorithms

**Files:**

- Create: `backend/app/Modules/Forecasting/Domain/DemandSeries.php`
- Create: `backend/app/Modules/Forecasting/Domain/ForecastPoint.php`
- Create: `backend/app/Modules/Forecasting/Domain/ForecastResult.php`
- Create: `backend/app/Modules/Forecasting/Domain/ForecastModel.php`
- Create: `backend/app/Modules/Forecasting/Domain/Models/SeasonalNaiveForecast.php`
- Create: `backend/app/Modules/Forecasting/Domain/Models/WeekdayMeanForecast.php`
- Create: `backend/app/Modules/Forecasting/Domain/Models/CrostonSbaForecast.php`
- Create: `backend/app/Modules/Forecasting/Domain/RollingOriginEvaluator.php`
- Create: `backend/app/Modules/Forecasting/Domain/ForecastModelSelector.php`
- Create: `backend/app/Modules/Forecasting/Domain/EmpiricalPredictionInterval.php`
- Create: `backend/app/Modules/Forecasting/Domain/StockProjection.php`
- Create: `backend/app/Modules/Forecasting/Domain/ReorderTiming.php`
- Create: `backend/tests/Unit/Modules/Forecasting/Domain/**`
- Modify: `backend/config/modules.php`
- Modify: `backend/tests/Unit/ArchitectureTest.php`

- [ ] Реализовать immutable value objects с явными dates/units и без framework helpers.
- [ ] Реализовать candidate models независимо друг от друга через малый `ForecastModel` interface.
- [ ] Исключить look-ahead bias: evaluator передаёт модели только prefix до текущего origin.
- [ ] Реализовать MAE, nullable WAPE, bias, interval coverage и deterministic tie-break.
- [ ] Реализовать intervals из residual sample и guard `lower <= estimate <= upper`.
- [ ] Реализовать depletion paths и reorder timing отдельно от demand forecasting.
- [ ] Покрыть constant, weekly-seasonal, trending, intermittent, all-zero, short, missing и invalid series.
- [ ] Расширить architecture test запретом Laravel/Infrastructure dependencies в Forecasting Domain и внутренних импортов соседних contexts.

Acceptance criteria:

- Domain tests не загружают Laravel и не требуют PostgreSQL/Redis.
- Fixtures с фиксированной серией дают идентичный результат независимо от системного времени.
- Отдельный leakage test падает, если evaluator видит observation после origin.

### Task 4. Добавить source ports и PostgreSQL adapters

**Files:**

- Create: `backend/app/Modules/Forecasting/Application/Contracts/ForecastInputSource.php`
- Create: `backend/app/Modules/Forecasting/Application/Contracts/ForecastRunRepository.php`
- Create: `backend/app/Modules/Forecasting/Application/Contracts/ForecastReadModel.php`
- Create: `backend/app/Modules/Forecasting/Application/Dtos/**`
- Create: `backend/app/Modules/Forecasting/Infrastructure/Persistence/PostgresForecastInputSource.php`
- Create: `backend/app/Modules/Forecasting/Infrastructure/Persistence/EloquentForecastRunRepository.php`
- Create: `backend/app/Modules/Forecasting/Infrastructure/Persistence/PostgresForecastReadModel.php`
- Create: `backend/app/Modules/Forecasting/Infrastructure/Models/ForecastRunModel.php`
- Create: `backend/app/Modules/Forecasting/Infrastructure/Models/ForecastSeriesModel.php`
- Create: `backend/app/Modules/Forecasting/Infrastructure/Models/ForecastPointModel.php`
- Create: `backend/database/migrations/*_create_forecast_runs_table.php`
- Create: `backend/database/migrations/*_create_forecast_series_table.php`
- Create: `backend/database/migrations/*_create_forecast_points_table.php`
- Modify when justified by `EXPLAIN`: `backend/database/migrations/*_add_forecasting_source_indexes.php`
- Modify: `backend/app/Providers/AppServiceProvider.php`
- Test: `backend/tests/Feature/Modules/Forecasting/ForecastPersistenceTest.php`
- Test: `backend/tests/Feature/Modules/Forecasting/ForecastInputSourceTest.php`

- [ ] Создать forward-safe migrations с workspace/run foreign keys и описанными unique/index constraints.
- [ ] Агрегировать completed demand в SQL и заполнять series calendar с различием observed/missing.
- [ ] Выбирать inventory snapshot не позже cutoff.
- [ ] Считать p80 lead time по `actual_delivery_date - order_date` только для завершённых deliveries в допустимом history window.
- [ ] Реализовать workspace-scoped queries во всех adapters.
- [ ] Сохранять run/series/points chunks и публиковать `completed` только после успешного завершения всего run.
- [ ] Доказать query plan на объёме, определённом Phase 16; индекс добавлять только по фактическому плану.

Acceptance criteria:

- Adapter не читает данные другого workspace даже при совпадающих product IDs.
- Missing workspace day не превращается в zero observation.
- Failed/partial run не возвращается read model.
- Миграции применяются на чистую и существующую БД без изменения фактических данных Sales/Inventory/Supplier contexts.

### Task 5. Реализовать generation use case, queue и lifecycle

**Files:**

- Create: `backend/app/Modules/Forecasting/Application/Commands/GenerateForecastRun.php`
- Create: `backend/app/Modules/Forecasting/Application/Commands/GenerateForecastRunHandler.php`
- Create: `backend/app/Modules/Forecasting/Application/Commands/PruneForecastRuns.php`
- Create: `backend/app/Modules/Forecasting/Infrastructure/Jobs/GenerateWorkspaceForecastJob.php`
- Create: `backend/app/Modules/Forecasting/Infrastructure/Commands/GenerateForecastsCommand.php`
- Create: `backend/app/Modules/Forecasting/Infrastructure/Commands/PruneForecastsCommand.php`
- Create: `backend/config/forecasting.php`
- Modify: `backend/routes/console.php`
- Modify: `backend/.env.example`
- Test: `backend/tests/Unit/Modules/Forecasting/Application/GenerateForecastRunTest.php`
- Test: `backend/tests/Feature/Modules/Forecasting/GenerateForecastsCommandTest.php`
- Test: `backend/tests/Feature/Modules/Forecasting/GenerateWorkspaceForecastJobTest.php`

- [ ] Оркестрировать input loading, model evaluation/selection, interval, stock projection и persistence без доменных формул в handler/job.
- [ ] Добавить unique lock на workspace + cutoff + model version.
- [ ] Поддержать latest cutoff, явный `--as-of`, `--workspace`, sync test mode и безопасный retry failed run.
- [ ] Dispatch ежедневных jobs выполнять scheduler-ом после ingestion window; не связывать HTTP с generation.
- [ ] Ограничить chunk size/config и проверить memory ceiling на demo и Phase 16 volume fixture.
- [ ] Добавить retention command и schedule без удаления текущего completed run.
- [ ] Инструментировать duration, series outcomes, model distribution, failures и stale age через observability conventions Phase 17.
- [ ] Sanitize exceptions: raw series, SQL bindings и business payload не попадают в logs/API.

Acceptance criteria:

- Два одинаковых запуска создают один immutable run.
- Падение посередине оставляет run невидимым для API и допускает контролируемый retry.
- Разные workspaces можно обрабатывать параллельно без shared mutable state.
- HTTP response time не включает forecast calculation.

### Task 6. Реализовать backend queries и Presentation

**Files:**

- Create: `backend/app/Modules/Forecasting/Application/Queries/GetForecastSummaryQuery.php`
- Create: `backend/app/Modules/Forecasting/Application/Queries/GetForecastSummaryHandler.php`
- Create: `backend/app/Modules/Forecasting/Application/Queries/GetForecastItemsQuery.php`
- Create: `backend/app/Modules/Forecasting/Application/Queries/GetForecastItemsHandler.php`
- Create: `backend/app/Modules/Forecasting/Application/Queries/GetForecastDetailQuery.php`
- Create: `backend/app/Modules/Forecasting/Application/Queries/GetForecastDetailHandler.php`
- Create: `backend/app/Modules/Forecasting/Application/Queries/GetForecastFiltersQuery.php`
- Create: `backend/app/Modules/Forecasting/Application/Queries/GetForecastFiltersHandler.php`
- Create: `backend/app/Modules/Forecasting/Presentation/Controllers/ForecastingController.php`
- Create: `backend/app/Modules/Forecasting/Presentation/Requests/**`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/Modules/Forecasting/ForecastingApiTest.php`
- Test: `backend/tests/Contract/ForecastingOpenApiContractTest.php`

- [ ] Реализовать thin controller и отдельные request validators.
- [ ] Применять workspace authorization до чтения run/series.
- [ ] Truncate points до requested horizon без пересчёта модели.
- [ ] Реализовать пагинацию, server-side filters/search/sort и stable tie-break sort.
- [ ] Для detail query получить measured history, persisted backtest/future points и actual overlap для предыдущего run.
- [ ] Вернуть explicit freshness/data-quality/uncertainty/reorder statuses из contract.
- [ ] Не вычислять forecast или stock policy в controller/resource.

Acceptance criteria:

- Feature tests покрывают 200/401/403/404/422, cross-workspace access и пустой completed-run state.
- JSON соответствует OpenAPI для ready и каждого insufficient/unavailable сценария.
- Existing analytics endpoints остаются совместимыми.

### Task 7. Построить Forecasting UI

**Files:**

- Create: `frontend/app/(dashboard)/forecasting/page.tsx`
- Create: `frontend/app/(dashboard)/forecasting/[productId]/page.tsx`
- Create: `frontend/src/features/forecasting/api/forecasting-gateway.ts`
- Create: `frontend/src/features/forecasting/api/forecasting-gateway.test.ts`
- Create: `frontend/src/features/forecasting/ui/forecasting-overview.tsx`
- Create: `frontend/src/features/forecasting/ui/forecast-summary-cards.tsx`
- Create: `frontend/src/features/forecasting/ui/forecast-filters.tsx`
- Create: `frontend/src/features/forecasting/ui/forecast-items-table.tsx`
- Create: `frontend/src/features/forecasting/ui/demand-forecast-chart.tsx`
- Create: `frontend/src/features/forecasting/ui/forecast-evaluation-panel.tsx`
- Create: `frontend/src/features/forecasting/ui/stock-projection-card.tsx`
- Create: `frontend/src/features/forecasting/ui/forecast-assumptions.tsx`
- Create: `frontend/src/features/forecasting/ui/*.test.tsx`
- Modify: `frontend/src/shared/ui/layout/sidebar.tsx`

- [ ] Использовать только generated API types; gateway выполняет transport mapping, но не бизнес-расчёты.
- [ ] Загружать initial summary/items server-side, а интерактивные filters/pagination держать в узком Client Component.
- [ ] Синхронизировать shareable filters и выбранный run/horizon с URL.
- [ ] На overview показать freshness, coverage, quality metrics и risk table до детальной визуализации.
- [ ] На detail page показать measured/backtest/forecast series, interval band, cutoff divider и отдельный forecast-vs-actual view.
- [ ] Показать модель, history window, MAE/WAPE/bias/coverage простым языком и с tooltips.
- [ ] Показать assumptions рядом с depletion/reorder result, включая отсутствие incoming receipts.
- [ ] Для insufficient data, missing uncertainty, stale run и missing lead time использовать отдельные informative states без fake zeros.
- [ ] Обеспечить keyboard navigation, accessible legend/patterns, table semantics и responsive layout.
- [ ] Не добавлять вложенные dashboards, drawers и вторую систему вкладок: overview и отдельная detail route достаточны.

Acceptance criteria:

- Пользователь с первого экрана видит, на какую дату и с какой точностью построен forecast.
- Measured и forecast data различимы без опоры только на цвет.
- Ни один UI component не пересчитывает depletion/reorder/evaluation metrics.
- Component tests покрывают ready, loading, error, empty, insufficient и stale states.

### Task 8. Подготовить воспроизводимый demo и end-to-end evaluation

**Files:**

- Modify: `backend/database/seeders/Demo/DemoDatasetGenerator.php`
- Modify: `backend/database/seeders/DemoDataSeeder.php`
- Modify: `scripts/verify-integration.sh`
- Create when supported by current E2E stack: `frontend/e2e/forecasting.spec.ts`

- [ ] Сохранить seed `42` и текущие product identities; расширять данные только детерминированно.
- [ ] Зафиксировать минимум четыре сценария: weekly seasonal, trend, intermittent demand и insufficient history/coverage.
- [ ] Создать historical run с cutoff `2025-11-30`, не читая December observations при generation.
- [ ] После generation сопоставить December actual с сохранённым November forecast и проверить forecast-vs-actual response.
- [ ] Создать latest run с cutoff `2025-12-31` и проверить 14/30/60-day API truncation.
- [ ] Проверить upper-demand depletion не позже central/lower path и reorder date с p80 lead time.
- [ ] Проверить, что series без lead-time samples показывает depletion, но не выдумывает reorder date.
- [ ] Проверить идемпотентный rerun, failed-run invisibility, retry и cross-workspace isolation.
- [ ] Проверить UI labels/legend/assumptions и отсутствие console errors.
- [ ] Измерить job duration, peak memory, SQL count и endpoint latency относительно budgets Phase 16/17.

Acceptance criteria:

- Тест доказывает отсутствие December data в November training/evaluation input.
- Forecast-vs-actual использует сохранённый prediction, а не ретроспективный пересчёт после появления actual.
- Полный user flow работает на clean seeded stack.

### Task 9. Integration checkpoint и закрытие Phase 19

**Files:**

- Modify: `docs/roadmap/19-forecasting.md`
- Modify: `docs/roadmap/ROADMAP.md`
- Modify if behavior changed: `README.md`

- [ ] Выполнить применимые проверки:

```bash
make check-contracts
npm run api:generate --prefix frontend
npm run format:check --prefix frontend
npm run lint --prefix frontend
npm run typecheck --prefix frontend
npm run test --prefix frontend
npm run build --prefix frontend
composer --working-dir=backend lint
composer --working-dir=backend test
make check
make integration
```

- [ ] Выполнить clean-database migration/seed/generation и повторить generation для idempotency.
- [ ] Проверить `EXPLAIN (ANALYZE, BUFFERS)` source/read queries на Phase 16 volume fixture и приложить результат к checkpoint.
- [ ] Проверить scheduler/worker restart, duplicate dispatch, partial failure, retention и stale-run behavior.
- [ ] Проверить OpenAPI drift и regenerated client diff.
- [ ] Провести review на data leakage, workspace leakage, fake certainty, hidden defaults, unbounded memory/query load и accidental cross-context imports.
- [ ] Записать в Phase 19 фактические команды, результаты, model version, quality metrics demo dataset и известные ограничения.
- [ ] Только после подтверждения каждого exit criterion отметить Phase 19 `[x]` в `docs/roadmap/ROADMAP.md`.

## Матрица проверок

| Требование | Проверка |
|---|---|
| Demand forecast | unit fixtures трёх моделей + API future points |
| No look-ahead bias | rolling-origin leakage test + historical November run против December actual |
| Uncertainty | residual interval tests, ordering invariant, interval coverage в API/UI |
| Historical evaluation | MAE/WAPE/bias/baseline tests и backtest points |
| Forecast-vs-actual | immutable earlier run + later actual join |
| Stock depletion | upper/central/lower projection tests и nullable beyond-horizon dates |
| Reorder timing | reorder-point crossing, p80 lead time, overdue и insufficient history cases |
| Data quality | short history, missing coverage, zero demand и stale input states |
| Reproducibility | fixed seed/cutoff/model version дают одинаковый result |
| Idempotency | unique run key, duplicate job/command test |
| Failure safety | partial/failed run не виден API, retry завершается completed run |
| Workspace isolation | source, persistence, API и E2E cross-workspace tests |
| API contract | Redocly + backend contract response + generated client drift |
| UI honesty | measured/forecast visual distinction, assumptions, accessible legend |
| Performance | Phase 16 dataset, SQL plan, bounded chunks, job/API metrics |
| Operations | scheduler, worker restart, metrics/logs, retention and stale age |

## Риски и меры

| Риск | Мера |
|---|---|
| Один год данных недостаточен для надёжной годовой сезонности | Не заявлять annual seasonality; использовать weekly/intermittent baselines и показывать uncertainty |
| Пропуск импорта ошибочно принят за нулевой спрос | Workspace observation calendar, coverage threshold и explicit `insufficient_coverage` |
| Backtest случайно видит будущие данные | Expanding prefix API в evaluator и отдельный leakage test |
| MAE поощряет zero forecast для редкого спроса | Croston candidate, WAPE/bias рядом с MAE и visible zero-demand status |
| Prediction interval выглядит как гарантия | Называть empirical 80% interval, показывать measured coverage и assumptions |
| Inventory projection игнорирует будущие поставки | Явно указать assumption; не обещать stock balance после receipts |
| Нет product–supplier relation | Использовать только исторические deliveries product–warehouse; не выбирать поставщика и не считать order quantity |
| Forecast generation перегружает PHP/DB | SQL aggregation, bounded series chunks, queue jobs, Phase 16 budgets и observability |
| Daily immutable runs растут без границ | Retention 180 дней и indexes/partitioning review только по измерениям |
| Будущий Python service дублирует контракты | Application ports и versioned persistence/API; extraction только при измеренной причине |

## Порядок интеграции и handoff

Рекомендуемый порядок исполнения после открытия Phase 19:

1. Integration owner фиксирует methodology, ADR и OpenAPI contract.
2. Backend owner реализует Domain, persistence и generation в `backend/**` после contract freeze.
3. Frontend owner реализует `frontend/**` только по generated client; contract во время параллельной работы не меняется.
4. Integration owner добавляет demo/E2E, выполняет performance/operations checks и закрывает roadmap.

Обязательный handoff каждой части содержит:

- цель и фактическое состояние;
- write/read scope;
- frozen contract/model version;
- выполненные тесты и их результат;
- unresolved risks и следующий конкретный шаг.

## Допущения плана

- До старта Phase 19 предыдущие фазы могут изменить runtime conventions, auth/RBAC, observability и performance tooling. Реализация принимает их итоговые публичные interfaces и не откатывает их к состоянию на дату этого плана.
- Текущие незакоммиченные изменения Alerting/Outbox/frontend принадлежат более ранним фазам и не входят в Phase 19.
- Имена конкретных migration timestamps и свободного ADR выбираются в момент реализации после проверки фактического состояния репозитория.
- Phase 19 является последней фазой текущего roadmap, но её закрытие не разрешает автоматически выделять Forecasting в отдельный сервис.
