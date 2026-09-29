# Phase 12 — Alerting

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Добавить actionable alerts: систему мониторинга инцидентов и оповещения о рисках складских запасов с детерминированной оценкой правил, гарантией отсутствия дубликатов и бесшовным переходом в аналитический контекст.

## Функциональность

- Управление правилами мониторинга: создание, редактирование, удаление, включение/выключение (toggle).
- Поддерживаемые складские метрики: обеспеченность в днях продаж (`days_of_stock`), физический доступный остаток (`quantity_available`), общая стоимость остатка (`inventory_value`).
- Поддерживаемые типы правил: критический дефицит (`critical_stock`), нулевой остаток (`out_of_stock`), залежалый товар (`overstock`), точка перезаказа (`reorder_point`).
- Уровни критичности: `critical`, `warning`, `info`.
- Полный жизненный цикл алертов: `OPEN` (новый) $\rightarrow$ `ACKNOWLEDGED` (взят в работу) $\rightarrow$ `RESOLVED` (решён с указанием заметки/причины).
- Детерминированный расчет скорости расхода и запаса на основе срезов `fact_inventory_daily` и 30-дневной истории продаж.
- Навигация в аналитический контекст: алерты содержат прямую ссылку в `/inventory?warehouse_id=...&search=...` для мгновенного углубленного анализа.
- Многоарендная изоляция (multi-tenant boundary): строгая изоляция правил и алертов на уровне workspace.

## Exit Criteria

- [x] **Rules детерминированы**: правила строго типизированы через Value Objects (`RuleCondition`, `RuleScope`) и Enums (`RuleMetric`, `RuleComparator`, `RuleType`), оценка выполняется однозначно.
- [x] **Execution идемпотентно**: повторный запуск пересчитывает актуальные значения метрики активных алертов без создания дублирующих записей.
- [x] **Гарантия отсутствия дубликатов**: гарантирована на уровне домена, хэндлера `EvaluateAlertRulesHandler` через вычисление `DedupFingerprint` и на уровне PostgreSQL через частичный уникальный индекс `uq_alerts_active_dedup` по `(workspace_id, dedup_fingerprint)` WHERE `status IN ('open', 'acknowledged')`.
- [x] **Lifecycle покрыт тестами**: полный цикл переходов статусов покрыт Unit, Feature и E2E тестами.
- [x] **Контракт OpenAPI 3.0.3**: зафиксированы эндпоинты `/alert-rules*` и `/alerts*`, Redocly валидация 0 ошибок, типы сгенерированы.
- [x] **Сквозная интеграция**: проверена в Docker через `scripts/verify-integration.sh`.

## Прогресс

- **Сделано:**
  - Контракт OpenAPI 3.0.3 обновлен (`contracts/openapi/analytics-v1.yaml`) и валидирован с 0 ошибок.
  - Миграции PostgreSQL (`2026_09_23_000040_create_alert_rules_table.php`, `2026_09_23_000041_create_alerts_table.php`) созданы и применены.
  - Чистая доменная модель (Pure DDD) `App\Modules\Alerting\Domain` полностью отвязана от фреймворка Laravel (подтверждено `ArchitectureTest`).
  - Репозитории (Eloquent и InMemory) и адаптер `PostgresInventoryAlertSource` реализованы.
  - CQRS Commands и Queries реализованы.
  - REST контроллеры `AlertRuleController` и `AlertController` подключены в `routes/api.php` с middleware аутентификации и tenant-изоляцией.
  - Сидер `AlertingSeeder` с демо-правилами и алертами подключен в `DatabaseSeeder` и выполнен.
  - Фронтенд typed gateway `alertsGateway` с тестами на vitest реализован.
  - UI компоненты раздела алертов (`AlertSummaryCards`, `AlertTable`, `AlertRuleList`, `AlertRuleDialog`, `AlertResolveDialog`, `AlertsView`) и страница `app/(dashboard)/alerts/page.tsx` разработаны.
  - Пункт навигации «Алерты» активирован в сайдбаре.
  - Сквозные интеграционные шаги добавлены в `scripts/verify-integration.sh`.
- **Осталось:** ничего, все exit criteria выполнены.
- **Блокеры:** отсутствуют.
- **Следующий шаг:** Phase 13 — Domain Events and Transactional Outbox.

## Проверка завершения

- **Дата:** 2026-09-23
- **Подтверждение Exit Criteria:**
  - Детерминированные правила, строгая идемпотентность, отсутствие дубликатов, полный жизненный цикл алертов и навигация в `/inventory` подтверждены тестами.
- **Команды проверок и результаты:**
  - `npm --prefix frontend run contracts:validate` — 0 ошибок (Redocly).
  - `npm --prefix frontend run api:generate` — TypeScript типы обновлены.
  - `npm --prefix frontend run lint` — ESLint пройден без замечаний.
  - `npm --prefix frontend run format:check` — Prettier форматирование соблюдено.
  - `npm --prefix frontend run typecheck` — TypeScript компиляция без ошибок.
  - `npm --prefix frontend test` — 45 тестовых файлов, 193 теста vitest PASS.
  - `npm --prefix frontend run build` — Next.js 16 production build PASS (`/alerts` dynamic route).
  - `composer --working-dir=backend validate --strict` — composer.json валиден.
  - `composer --working-dir=backend lint` — Pint и PHPStan (level 8) PASS, 0 ошибок.
  - `composer --working-dir=backend test` — 253 теста PHPUnit, 29272 assertions PASS.
  - `make check` — Полный комплекс статических проверок и тестов PASS.
  - `./scripts/verify-integration.sh` — Все 43 сквозных интеграционных шага с живыми PostgreSQL/Redis/Backend/Frontend контейнерами PASS.
