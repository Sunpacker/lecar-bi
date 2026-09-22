# AutoBI — roadmap автономной разработки

## Как читать

1. Прочитать [AGENTS.md](../../AGENTS.md) и этот индекс.
2. Открыть только первый незавершённый этап из списка ниже — это текущая фаза.
3. Прочитать [индекс архитектуры](../architecture/README.md), затем необходимые архитектурные документы и файлы текущей задачи.
4. Прошлые этапы открывать только для проверки зависимости; будущие — только по явной задаче на планирование.

Не загружать всю папку roadmap. Следующая фаза не активна, пока не выполнены exit criteria текущей.
Порядок этапов задаёт зависимости; список содержит только названия и отметки завершения.

## Выполнение

`[ ]` — этап не завершён; `[x]` — все exit criteria и обязательные проверки выполнены.
Единственный источник статуса этапов — этот список; отдельный указатель текущей фазы не нужен.
На момент разделения roadmap в репозитории есть документация, но bootstrap ещё не завершён.

- [x] [Phase 0 — Bootstrap репозитория](00-bootstrap.md)
- [x] [Phase 1 — Development Foundation](01-development-foundation.md)
- [x] [Phase 2 — Identity, Workspace and Access Boundary](02-identity-workspace-access.md)
- [x] [Phase 3 — Demo Data Model](03-demo-data-model.md)
- [x] [Phase 4 — Sales Analytics Vertical Slice](04-sales-analytics.md)
- [x] [Phase 5 — Sales Drill-Down and BI Interaction Model](05-sales-drill-down.md)
- [ ] [Phase 6 — Inventory Intelligence](06-inventory-intelligence.md)
- [ ] [Phase 7 — ABC/XYZ Analysis](07-abc-xyz-analysis.md)
- [ ] [Phase 8 — Dashboard Builder](08-dashboard-builder.md)
- [ ] [Phase 9 — Shared Filters and Saved Views](09-shared-filters-saved-views.md)
- [ ] [Phase 10 — Data Ingestion](10-data-ingestion.md)
- [ ] [Phase 11 — Supplier Analytics](11-supplier-analytics.md)
- [ ] [Phase 12 — Alerting](12-alerting.md)
- [ ] [Phase 13 — Domain Events and Transactional Outbox](13-events-outbox.md)
- [ ] [Phase 14 — Notification Service Extraction Exercise](14-notification-service.md)
- [ ] [Phase 15 — RBAC](15-rbac.md)
- [ ] [Phase 16 — Performance and Caching](16-performance-caching.md)
- [ ] [Phase 17 — Observability](17-observability.md)
- [ ] [Phase 18 — Production Hardening](18-production-hardening.md)
- [ ] [Phase 19 — Forecasting Extension](19-forecasting.md)

## Как обновлять прогресс

- После работы фиксировать в файле текущего этапа раздел «Прогресс»: что сделано, что осталось, блокеры и следующий шаг.
- При закрытии этапа добавить раздел «Проверка завершения»: дата, подтверждение exit criteria, команды проверок и результаты.
- Только после этого заменить `[ ]` на `[x]` в индексе в том же изменении; созданная документация сама по себе не завершает этап.
- Если критерии перестали выполняться, снять отметку и описать причину в файле этапа.
- При параллельной работе индекс обновляет интегратор; изменения общего файла согласовывать последовательно.

## Архитектурный контекст

Перед началом фазы читать:

- [00 — Обзор](../architecture/00-overview.md).
- [01 — Архитектура системы](../architecture/01-system-architecture.md).
- [12 — Архитектурные решения](../architecture/12-architecture-decisions.md).

Профильные документы выбирать по маршрутам в `AGENTS.md` и ссылкам текущего этапа.
[11 — Эволюция системы](../architecture/11-evolution-and-roadmap.md) нужен при планировании или изменении архитектуры, а не для каждой реализации.
Все пути в обратных кавычках в файлах этапов указаны от корня репозитория; короткие имена архитектурных файлов относятся к `docs/architecture/`.
При противоречиях применять приоритет источников из `AGENTS.md` и сначала согласовать архитектурное решение.

## Выбор следующей задачи

1. Разблокировать текущую фазу.
2. Исправить её failing tests.
3. Закрыть недостающие exit criteria.
4. Завершить интеграцию уже реализованных частей.
5. Добавить тесты текущего поведения.
6. Обновить необходимую документацию.
7. Переходить дальше только после выполнения exit criteria.

Не перескакивать к сложной инфраструктуре только потому, что она интереснее.
Предпочитать один законченный vertical slice: понятный outcome, один bounded context, ограниченный write scope, тесты и acceptance criteria.
Разделять несвязанные contexts, frontend/backend до фиксации контракта, архитектурные решения и рутинную реализацию, инфраструктурные миграции и продуктовые features.

## Параллельная работа

Работать параллельно только внутри текущей фазы при независимых задачах и непересекающемся write scope.
Обычно безопасны frontend/backend против frozen API, docs и tests вне активно изменяемых файлов, analysis-only inspection.
Не менять одновременно OpenAPI, миграции, один bounded context, Docker Compose, root dependencies или одни файлы в refactor/feature.
Роли моделей и правила handoff заданы в `AGENTS.md`; характер задачи важнее модели по умолчанию.

## Integration Checkpoints

Обязательный checkpoint указан в файле соответствующего этапа и входит в условие его завершения.
Проверить build, совместимость frontend/backend контрактов, архитектуру, миграции, test suite, документацию, случайную связанность и exit criteria.
Предпочтительный integration-agent — GPT-5.6.

## Демонстрация продукта

[Portfolio Completion Target](portfolio.md) читать только при подготовке демонстрации или оценке готовности продукта.
