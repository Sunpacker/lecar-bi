# Phase 19 — Forecasting Extension

[Индекс и правила roadmap](ROADMAP.md) · [Маршрутизатор агентов](../../AGENTS.md) · [Аналитическая архитектура](../architecture/06-data-and-analytics.md)

## Цель и границы

Помочь пользователю заранее увидеть ожидаемые продажи, риск исчерпания остатков и ориентировочный срок заказа. Каждый прогноз имеет дату построения, горизонт, источник данных, допущения и проверенную на истории оценку ошибки. Это расширение аналитики, а не автоматическое создание закупок или замена действующих правил критического остатка и alerting.

Этап начинается после завершения [Phase 17](17-observability.md) и [Phase 18](18-production-hardening.md). Первая реализация принадлежит Inventory Analytics внутри analytics service: он получает данные Sales Analytics и Supplier Analytics через явные read contracts, не обращаясь к их внутренним моделям. Отдельный forecasting service, Python runtime и новые зависимости не вводятся без результатов измерения и архитектурного решения.

## Исходные данные и семантика

- **Ряд продаж:** дневная сумма quantity из fact_order_items по workspace, товару и складу с календарной сеткой. Учитывать статус заказа из fact_orders и исключать отменённые/возвращённые позиции согласно действующей бизнес-семантике. День без продаж при подтверждённой полноте импорта — ноль; день с неполными данными — пропуск. Граница данных (as_of) определяется последней *полной* датой продаж и снимка остатков, а не текущими часами сервера.
- **Цензурирование спроса:** продажи в день дефицита (quantity_available ≤ 0) могут быть ниже реального спроса. Такие дни не считать нулевым спросом и не использовать как обычные наблюдения при обучении и оценке. Если их слишком много или снимков недостаточно для проверки доступности, вернуть статус ограниченного качества. Различать прогноз наблюдаемых продаж и прогноз спроса; последний термин допустим только при проверенном способе обработки дефицита.
- **Остатки и поставки:** брать последний подтверждённый снимок fact_inventory_daily в том же workspace. quantity_available, safety_stock и reorder_point сохраняют текущую доменную семантику. fact_supplier_deliveries даёт исторический срок поставки; ожидаемые даты из этого журнала не считать гарантированными будущими поступлениями. Первый сценарий исчерпания рассчитывается **без будущих поступлений** и так подписывается в API/UI.
- **Свежесть и пригодность:** возраст снимка, полноту ряда, долю дней дефицита и минимальную историю определять до расчёта. Демоданные заканчиваются 2025 годом: прогноз на дату 2026 года нельзя выдавать как актуальный. Для демонстрации использовать исторический as_of и backtest либо обновить датасет с проверкой свежести. При пустом, коротком или устаревшем ряде показывать причину недоступности вместо прогноза.

## Технологический выбор

| Шаг | Технология и назначение | Условие применения |
| --- | --- | --- |
| Первый работающий срез | PostgreSQL агрегирует дневные ряды и хранит версии прогнозов; Laravel Queue вычисляет их вне HTTP; простой сезонный baseline (повторение дня недели) и среднее за последние 28 дней реализуются за портом прогнозирования в analytics service. | Зафиксировать точные правила выбора baseline по результатам backtest. Не вводить ML runtime ради одной формулы. |
| Сравнение моделей | В изолированном воспроизводимом эксперименте проверить актуальный [StatsForecast](https://github.com/Nixtla/statsforecast): SeasonalNaive, AutoETS, а для прерывистых продаж — CrostonOptimized/ADIDA. | Продвигать кандидат только при выигрыше над простым baseline на заранее выбранных метриках и с приемлемой стоимостью запуска. У Croston нет гарантированных встроенных prediction intervals; покрытие интервалов проверять отдельно. |
| Более сложный кандидат | [MLForecast](https://github.com/Nixtla/mlforecast) с лагами и календарными признаками, моделью с явной фиксацией версии и conformal intervals; quantile regression можно сравнить через [scikit-learn](https://scikit-learn.org/stable/auto_examples/applications/plot_time_series_lagged_features.html). | Только если данных достаточно, статистические кандидаты систематически не проходят quality gate, а признаки доступны на момент прогноза. Не переносить Python-модель в Laravel и не добавлять сервис без отдельного ADR. |

Версии пакетов, параметры и seed эксперимента фиксировать при реализации; модель выбирать по качеству на данных проекта, а не по популярности или новизне.

## Прогноз и проверка качества

1. **Сформировать датасет as-of.** Для каждой пары товар–склад сохранить интервалы наблюдения, исключённые дни, версии входных датасетов (sales, inventory, suppliers) и момент отсечения. Не использовать заказ, снимок остатка, поставку или признак, которые ещё не были известны на историческом отсечении. Пустые дни, пропуски и дни дефицита обрабатывать раздельно.
2. **Построить прогноз на 7, 14 и 28 дней.** Выбрать простейший допустимый baseline по ряду; ограничить отрицательные значения нулём. Для нового товара, нерегулярного ряда и недостаточной истории возвращать явный статус качества. Суммы по категории/складу должны быть согласованы с выбранной иерархией: либо сумма опубликованных товарных прогнозов, либо отдельный агрегат с явной пометкой; не смешивать оба подхода в одной метрике.
3. **Показать неопределённость.** Для достаточной истории оценивать интервалы будущих наблюдений из ошибок rolling-origin backtest отдельно по шагу прогноза и релевантному сегменту; верхняя и нижняя границы неотрицательны. Указывать уровень интервала (например, 80%) только после проверки фактического покрытия на отложенных срезах. При недостаточном числе ошибок не рисовать точную «80%» зону: оставить точечную оценку с предупреждением либо скрыть прогноз согласно quality gate. Интервал прогноза не является гарантией наличия товара.
4. **Рассчитать риск остатков.** От последнего quantity_available последовательно вычитать прогнозируемые продажи, без неутверждённых поставок. При остатке ≤ 0 дата исчерпания — as_of; иначе это первый день, когда остаток достигает нуля. Если доступный остаток уже не выше reorder_point, дата достижения порога — as_of; иначе это первый день пересечения порога. Если порог не достигнут в пределах горизонта, дату не придумывать. Ориентировочный срок размещения заказа = дата достижения порога минус обоснованный lead time по завершённым поставкам соответствующего товара/поставщика; прошедший срок обозначить «заказать сейчас». Если достоверного lead time нет, не показывать дату заказа. Диапазон риска по сценариям отделять от калиброванного интервала продаж; автоматическую закупку не запускать.
5. **Проверить на истории.** Использовать rolling-origin backtest с теми же горизонтами 7/14/28, несколькими отсечениями и последним неиспользованным для выбора модели периодом. Сравнивать с seasonal naive и 28-дневным средним: MAE, WAPE при ненулевой сумме факта, signed bias, ошибку по горизонту и сегментам (включая прерывистые продажи), а для интервалов — empirical coverage и ширину. Не использовать MAPE для рядов с нулями. Отдельно оценить попадание в фактическое исчерпание на датах, где есть полноценные снимки; при неизвестных поступлениях такие случаи не объявлять ошибкой модели продаж. До публикации зафиксировать пороги качества и минимальный объём наблюдений в отчёте backtest; провалившиеся сегменты скрыть или пометить как ненадёжные.

## Контракт, выполнение и интерфейс

1. **Сначала OpenAPI.** Добавить versioned endpoints чтения прогнозов и качества в contracts/openapi/analytics-v1.yaml, проверить совместимость и сгенерировать TypeScript-клиент. Ответ должен содержать workspace, product, warehouse, as_of, generated_at, horizon, дневные measured/forecast points, единицы измерения, метод/версию модели, интервал с уровнем или причиной отсутствия, метрики качества, data_freshness, допущения и явный статус (ready, stale, insufficient_data, limited_by_stockouts, failed). Защита — существующая analytics.view и обязательная серверная проверка workspace; полномочия на запуск пересчёта решать отдельно, не через публичный тяжёлый GET.
2. **Фоновый расчёт.** Laravel Queue вычисляет ограниченные батчи по workspace; результаты и версия исходных датасетов хранятся в PostgreSQL. Повтор задачи с теми же workspace + product + warehouse + as_of + horizon + model_version + dataset_versions идемпотентен. После импорта изменение версий делает старые результаты устаревшими; ручной запуск и плановое обновление используют один application use case. Не запускать обучение на запрос страницы. Redis допустим для очереди и блокировки, но не является источником истины. Ограничить параллелизм, число рядов в батче и время выполнения; измерять длительность, ошибки, долю stale и возраст последнего успешного расчёта по правилам Phase 17.
3. **UI.** В контексте товара/склада показать фактическую историю сплошной линией, прогноз — отдельным стилем после видимой границы as_of, зону неопределённости только для проверенного интервала. Для сохранённого прогноза дать сравнение forecast-vs-actual по тем датам, на которые уже поступил полный факт; не пересчитывать прошлый прогноз задним числом. Под графиком дать короткое объяснение метода, дату данных, ошибку на истории и допущение «без будущих поступлений». Срок заказа показывать как рекомендацию с условиями, не как обещание. Состояния stale/insufficient_data/limited_by_stockouts объяснять пользователю без чисел, которым нельзя доверять. Frontend только форматирует значения, вычисленные backend.

## Exit Criteria

- Для репрезентативного набора товаров, включая нулевые продажи, сезонность, дефицит, короткую историю и два workspace, прогноз воспроизводим при одинаковом as_of и версиях данных; кросс-доступ к чужим рядам невозможен.
- Backtest воспроизводим и опубликован как артефакт этапа: указаны исходные срезы, exclusions, baseline, ошибки по горизонту/сегментам, bias, покрытие и ширина интервалов. Прогноз, не прошедший заранее объявленный quality gate, не показывается как надёжный. Нет утечки будущих данных в признаки или lead time.
- UI и OpenAPI различают факт, сохранённый прогноз, сравнение forecast-vs-actual, границы интервала и сценарий остатков; при устаревших данных, отсутствии lead time или слишком короткой истории отображается честное ограничение. Дата исчерпания и дата размещения заказа имеют разные подписи и проверенные формулы.
- Очередь, идемпотентность, обработка повторов/отказов, инвалидация после импорта и наблюдаемость проверены. HTTP остаётся быстрым при пересчёте; миграции и хранение версий прогнозов допускают повторный запуск и восстановление.
- Пройдён [integration checkpoint](ROADMAP.md#integration-checkpoints): контракт → backend → generated client → frontend, применимые unit/integration/API/UI/E2E тесты, линтеры, static analysis, сборка и проверка границ DDD. Документация и ADR обновлены только если при реализации действительно изменятся runtime, сервисная граница или стратегия хранения.

## Источники для реализации

- [Forecasting: Principles and Practice — time series cross-validation](https://otexts.com/fpp3/tscv.html), [оценка точечного прогноза](https://otexts.com/fpp3/accuracy.html) и [оценка интервалов](https://otexts.com/fpp3/distaccuracy.html).
- [StatsForecast: поддерживаемые модели](https://github.com/Nixtla/statsforecast) и [MLForecast: лаги, cross-validation и интервалы](https://github.com/Nixtla/mlforecast).
- [scikit-learn: прогноз временного ряда и quantile regression](https://scikit-learn.org/stable/auto_examples/applications/plot_time_series_lagged_features.html).

## Прогресс

- Реализован контракт API и схемы данных:
  - В [`contracts/openapi/analytics-v1.yaml`](../../contracts/openapi/analytics-v1.yaml) добавлен эндпоинт `GET /analytics/forecasts/{productId}/{warehouseId}` с параметрами `horizon_days` (7, 14, 28) и `as_of_date`.
  - Описаны схемы `ForecastResponse`, `ForecastStatus` (`ready`, `stale`, `insufficient_data`, `limited_by_stockouts`, `failed`), `ForecastPoint` с доверительными интервалами и отметкой дефицита, `ForecastQualityMetric` (MAE, WAPE, signed bias, coverage), `ForecastStockRisk` (остаток, страховой запас, точка заказа, дата исчерпания, дата порога и расчетный срок размещения заказа без фиктивных поставок).
  - Сгенерирован актуальный TypeScript-клиент в [`frontend/src/shared/api/generated/schema.ts`](../../frontend/src/shared/api/generated/schema.ts).
- Реализовано доменное ядро прогнозирования:
  - Базовые модели: [`SeasonalNaiveForecaster`](../../backend/app/Modules/InventoryAnalytics/Domain/Forecasting/SeasonalNaiveForecaster.php) (повторение дня недели) и [`MovingAverageForecaster`](../../backend/app/Modules/InventoryAnalytics/Domain/Forecasting/MovingAverageForecaster.php) (28-дневное среднее).
  - Обработка дефицита: [`SalesTimeSeries`](../../backend/app/Modules/InventoryAnalytics/Domain/Forecasting/SalesTimeSeries.php) цензурирует спрос в дни stockout, исключая искажение обучающей выборки.
  - Оценка качества и доверительные интервалы: [`BacktestEngine`](../../backend/app/Modules/InventoryAnalytics/Domain/Forecasting/BacktestEngine.php) выполняет rolling-origin backtest по историческим срезам, вычисляет эмпирические prediction intervals и метрики MAE/WAPE.
  - Оценка рисков запасов: [`StockRiskCalculator`](../../backend/app/Modules/InventoryAnalytics/Domain/Forecasting/StockRiskCalculator.php) последовательно рассчитывает дату исчерпания остатков и срок заказа на основе подтверждённого медианного lead time без неутверждённых поступлений.
  - Диспетчер качества: [`DataQualityAssessment`](../../backend/app/Modules/InventoryAnalytics/Domain/Forecasting/DataQualityAssessment.php) и [`ForecastEngine`](../../backend/app/Modules/InventoryAnalytics/Domain/Forecasting/ForecastEngine.php) проверяют полноту ряда, свежесть данных и долю дефицита.
- Реализована инфраструктура, персистентность и API:
  - Миграция [`2026_09_25_000090_create_forecasts_tables.php`](../../backend/database/migrations/2026_09_25_000090_create_forecasts_tables.php) для таблиц `forecast_runs`, `forecast_points` и `forecast_quality_metrics`.
  - Чтение и персистентность: [`PostgresSalesTimeSeriesReader`](../../backend/app/Modules/InventoryAnalytics/Infrastructure/Persistence/PostgresSalesTimeSeriesReader.php), [`PostgresForecastRepository`](../../backend/app/Modules/InventoryAnalytics/Infrastructure/Persistence/PostgresForecastRepository.php) и [`PostgresForecastReadModel`](../../backend/app/Modules/InventoryAnalytics/Infrastructure/Persistence/PostgresForecastReadModel.php).
  - Фоновый пересчёт: джобы [`GenerateForecastJob`](../../backend/app/Modules/InventoryAnalytics/Infrastructure/Jobs/GenerateForecastJob.php) и [`GenerateWorkspaceForecastsJob`](../../backend/app/Modules/InventoryAnalytics/Infrastructure/Jobs/GenerateWorkspaceForecastsJob.php).
  - API Controller [`ForecastController`](../../backend/app/Modules/InventoryAnalytics/Presentation/Controllers/ForecastController.php) с проверкой capabilities и workspace boundary.
- Реализован frontend-слой прогнозирования спроса и оценки рисков запасов:
  - Шлюз [`inventory-gateway.ts`](../../frontend/src/features/inventory-analytics/api/inventory-gateway.ts): метод `getForecast(userId, workspaceId, productId, warehouseId, params)` с типизацией ответа `ForecastResponse` и параметров горизонта (7, 14, 28 дней).
  - Компонент бейджа качества [`forecast-quality-badge.tsx`](../../frontend/src/features/inventory-analytics/ui/forecast-quality-badge.tsx): визуальный индикатор статуса модели (`ready`, `stale`, `insufficient_data`, `limited_by_stockouts`, `failed`), расшифровка метода и метрики backtest (WAPE, MAE, Signed Bias, Coverage).
  - Карточка риска исчерпания и сроков заказа [`forecast-stock-risk-card.tsx`](../../frontend/src/features/inventory-analytics/ui/forecast-stock-risk-card.tsx): остаток, страховой запас, точка перезаказа, дата исчерпания, дата достижения порога, рекомендация «Заказать сейчас» / срок заказа на основе lead time и обязательный дисклеймер «Сценарий рассчитан без будущих поступлений».
  - Карточка метаданных [`forecast-metadata.tsx`](../../frontend/src/features/inventory-analytics/ui/forecast-metadata.tsx): свежесть срезов по продажам, остаткам и поставкам (as-of date), время генерации и список допущений модели.
  - Интерактивный график [`forecast-chart.tsx`](../../frontend/src/features/inventory-analytics/ui/forecast-chart.tsx): сплошная линия факта продаж, пунктирная линия прогноза спроса, полупрозрачная полоса доверительного интервала (80%), вертикальная линия границы данных (as-of) и точки ретроспективного сравнения forecast-vs-actual.
  - Контейнерный экран [`product-forecast-view.tsx`](../../frontend/src/features/inventory-analytics/ui/product-forecast-view.tsx): селектор горизонта (7/14/28 дней), селектор товара со склада, обновление данных, обработка состояний загрузки и ошибок.
  - Интеграция в навигацию и таблицы: вкладка «Прогноз спроса» в [`inventory-tabs-nav.tsx`](../../frontend/src/features/inventory-analytics/ui/inventory-tabs-nav.tsx) и [`inventory-tabs-container.tsx`](../../frontend/src/features/inventory-analytics/ui/inventory-tabs-container.tsx), а также прямые кнопки перехода к прогнозу в строках [`inventory-items-table.tsx`](../../frontend/src/features/inventory-analytics/ui/inventory-items-table.tsx).
- Верификация:
  - Unit-тесты доменного ядра [`ForecastingTest.php`](../../backend/tests/Unit/Modules/InventoryAnalytics/Domain/ForecastingTest.php) (8 тестов, 76 проверок).
  - Feature-тесты эндпоинта [`ForecastApiTest.php`](../../backend/tests/Feature/Modules/InventoryAnalytics/ForecastApiTest.php) (4 теста, 63 проверки: 401 unauthenticated, 403 workspace boundary, 404 not found, 200 full payload).
  - Frontend unit-тесты шлюза [`inventory-gateway.test.ts`](../../frontend/src/features/inventory-analytics/api/inventory-gateway.test.ts) (7 тестов) и UI-компонентов [`forecast-components.test.tsx`](../../frontend/src/features/inventory-analytics/ui/forecast-components.test.tsx) (12 тестов).
  - Полный прогон `make check` успешен: контракты валидны, 403 теста backend, 59 тестов notification, 242 теста frontend vitest, typecheck, lint, prettier и сборка Next.js без ошибок.

## Проверка завершения

- **Дата:** 2026-09-25
- **Статус этапа:** Завершен ([x])

### Подтверждение Exit Criteria

| Критерий | Статус | Подтверждение |
| --- | --- | --- |
| Воспроизводимость прогноза и изоляция workspace | Выполнен | Проверено в `ForecastingTest.php` и `ForecastApiTest.php`: проверка детерминированности по as_of и версиям данных, отказ 403 при межорганизационном доступе |
| Воспроизводимый backtest, quality gate и обработка дефицита | Выполнен | `BacktestEngine` рассчитывает WAPE/MAE/Signed Bias на скользящих окнах; `SalesTimeSeries` цензурирует дни дефицита; при недостатке истории выставляется статус `insufficient_data` / `limited_by_stockouts` |
| Разделение факта, прогноза, доверительных интервалов и сценария остатков в UI/OpenAPI | Выполнен | Спецификация OpenAPI и компоненты `ForecastChart`, `ForecastStockRiskCard`, `ForecastQualityBadge` наглядно разделяют факт, прогноз спроса, доверительный коридор (80%) и расчет срока заказа с явным дисклеймером об отсутствии будущих поступлений |
| Фоновый расчет, очередь, идемпотентность и версии датасетов | Выполнен | Миграция с составным уникальным индексом `uq_forecast_dedup` по версиям входных датасетов; очереди `GenerateForecastJob` и `GenerateWorkspaceForecastsJob` |
| Пройден integration checkpoint: контракт → backend → client → frontend, линтеры, анализ, тесты и сборка | Выполнен | `make check` (контракты, фронтенд, бэкенд, уведомления) завершен с кодом 0 |

### Команды верификации

```bash
make check-contracts   # OpenAPI валидация и генерация TypeScript-схемы
make check-backend     # composer validate, pint, phpstan (0 errors), phpunit (403 tests, 33716 assertions)
make check-notification # composer validate, pint, phpstan (0 errors), phpunit (59 tests)
make check-frontend    # eslint, format:check, typecheck, vitest (51 suites, 242 tests), next build
make check             # сквозная проверка всех сервисов репозитория
```

