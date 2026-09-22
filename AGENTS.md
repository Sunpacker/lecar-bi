# Маршрутизатор автономных агентов

Монорепозиторий: Next.js frontend, Laravel analytics service, PostgreSQL, Redis и OpenAPI.
Этот файл задаёт маршрут работы; подробности архитектуры находятся в `docs/architecture/`.
Все пути в тексте указаны от корня репозитория; Markdown-ссылки — относительно этого файла.

## 1. Источники истины

При конфликте применять приоритет сверху вниз:

1. Текущая задача пользователя.
2. `AGENTS.md`.
3. `docs/roadmap/`.
4. `docs/architecture/12-architecture-decisions.md`.
5. Остальные документы `docs/architecture/`.
6. Существующие тесты.
7. Текущая реализация.
8. Предположения агента.

Если код расходится с архитектурой, установить причину; по умолчанию следовать архитектуре.
Намеренное изменение архитектуры отразить в профильном документе и ADR.

## 2. Маршрут чтения

Всегда читать этот файл и [индекс roadmap](docs/roadmap/ROADMAP.md), затем только первый незавершённый этап и его exit criteria.
Чтение архитектуры начинать с [индекса архитектурной документации](docs/architecture/README.md), затем открывать документы по маршруту задачи.
Перед началом фазы читать документы 00, 01 и 12; документ 11 — при планировании эволюции, остальные — по профилю задачи.
Для смешанной задачи объединять маршруты. Не загружать будущие этапы roadmap и весь репозиторий без причины.

| Область задачи                | Документы архитектуры по номерам ниже |
| ----------------------------- | ------------------------------------- |
| Frontend                      | 00, 01, 03, 07, 10                    |
| Laravel / Domain              | 00, 04, 05, 06, 10, 12                |
| API / межсервисная интеграция | 01, 02, 07, 08                        |
| Инфраструктура                | 02, 09, 10                            |
| Аналитика                     | 05, 06, 08                            |

Карта документов:

- [00 — Обзор продукта](docs/architecture/00-overview.md)
- [01 — Архитектура системы](docs/architecture/01-system-architecture.md)
- [02 — Монорепозиторий и сервисы](docs/architecture/02-monorepo-and-services.md)
- [03 — Frontend Next.js](docs/architecture/03-frontend-nextjs.md)
- [04 — Backend Laravel DDD](docs/architecture/04-backend-laravel-ddd.md)
- [05 — Bounded contexts](docs/architecture/05-bounded-contexts.md)
- [06 — Данные и аналитика](docs/architecture/06-data-and-analytics.md)
- [07 — API и интеграции](docs/architecture/07-api-and-integration.md)
- [08 — События, Outbox, async](docs/architecture/08-events-outbox-async.md)
- [09 — Инфраструктура, деплой, observability](docs/architecture/09-infrastructure-deployment-observability.md)
- [10 — Тестирование и качество](docs/architecture/10-testing-and-quality.md)
- [11 — Эволюция и roadmap](docs/architecture/11-evolution-and-roadmap.md)
- [12 — Архитектурные решения](docs/architecture/12-architecture-decisions.md)

## 3. Выбор агента

| Модель          | Роль                    | Когда назначать                                                                                                                         |
| --------------- | ----------------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| GPT-5.6         | Интегратор / архитектор | Cross-service изменения, OpenAPI, DDD-границы, крупный рефакторинг, сложная отладка, порядок миграций, финальная интеграция             |
| Claude Opus 4.6 | Domain / review         | Доменное моделирование, реализация и рефакторинг одного context, связность, упрощение, качество тестов, независимость Domain от Laravel |
| Gemini Pro      | Repository / analysis   | Широкий анализ, карта зависимостей, повторяющиеся паттерны, документация, инвентаризация миграций, пробелы в тестах                     |

- Один агент: одна feature в одном сервисе без архитектурного решения.
- Два агента: реализация + review, анализ + реализация или frontend + backend после фиксации контракта.
- Предпочтительные пары: GPT-5.6 + Claude, Gemini + GPT-5.6, Gemini + Claude.
- Три агента: только при независимых задачах; Gemini анализирует, GPT-5.6 проектирует и интегрирует, Claude реализует Domain или проверяет.
- Для важных изменений назначать отдельного reviewer; характер задачи важнее модели по умолчанию.

## 4. Scope, параллельная работа и handoff

До старта определить цель, разрешённые и запрещённые директории, контракт, зависимости и критерии завершения.
Основные write scopes: `apps/web/**`, `services/analytics/**`, `contracts/**`, `docs/**`, `infra/**`.
Параллельные задачи допустимы внутри текущей фазы при непересекающихся write scopes.
Не назначать одновременные изменения одного bounded context двум агентам.
Общий контракт фиксировать до параллельной реализации и не менять до её завершения.

Высококонфликтные области: root dependencies/config, Docker Compose, OpenAPI, CI, shared TypeScript config,
Laravel service providers, центральные routes и миграции. Общие изменения выносить в integration-task.

Handoff обязан содержать:

- Цель и текущее состояние.
- Write Scope и Read Scope, включая релевантные архитектурные документы.
- Контракт, зависимости и Acceptance Criteria.
- Validation: выполненные проверки, результаты и оставшиеся проверки.
- Известные риски и следующий конкретный шаг; «доделай backend» недостаточно.

## 5. Границы ответственности

- **Frontend:** UI, routing, Server/Client Components, state, dashboards, charts, tables, filters, mapping и generated API client.
- **Laravel:** Domain, Application, Infrastructure, Presentation, persistence, расчёты, import, queues, events и alert rules.
- **OpenAPI:** единая публичная граница; Eloquent-модели не являются API-контрактом.
- При изменении API: контракт → проверка совместимости → backend → генерация frontend-клиента → тесты.
- Frontend и backend не определяют request/response независимо друг от друга.

Ключевые ограничения; подробности читать по маршрутам раздела 2:

- Backend — один deployable analytics service, модули организованы сначала по bounded context, затем по слоям.
- Начальные contexts: Workspace, Data Ingestion, Sales Analytics, Inventory Analytics, Supplier Analytics, Dashboard, Alerting.
- Каждая backend-feature принадлежит context; не создавать общий `Services` для несвязанных бизнес-правил.
- Domain независим от Laravel и внешних слоёв; Infrastructure зависит внутрь.
- Не размещать доменные правила в controllers, jobs, Eloquent models, resources, commands CLI и listeners.
- CQRS-lite: commands меняют состояние, queries читают; для BI предпочитать специализированные read models большим object graphs.
- Frontend — feature-oriented: routes, features, entities, widgets, shared UI, API access и utilities; не копировать backend DDD в React.
- Предпочитать server-side fetching, когда он сокращает client state; Client Components нужны для интерактивности.
- Frontend форматирует данные, но не дублирует backend-расчёты и не переопределяет бизнес-смысл.
- PostgreSQL хранит постоянные данные analytics; Redis обслуживает технические задачи.
- Другие сервисы не читают analytics DB: обмен через versioned API или integration events.
- Допустимы аналитические таблицы, projections, aggregates и materialized views.
- Domain Events внутренние; Integration Events — внешние контракты, без raw domain objects.
- Для надёжной публикации использовать Outbox, consumers делать идемпотентными; broker добавлять только по roadmap.

## 6. Протокол выполнения

1. Определить фазу roadmap, сервис, bounded context, документы, контракты и тесты.
2. Изучить минимальный контекст; составить короткий план: файлы, API/данные, проверки, риски, разделение задач.
3. Реализовать минимальное связное изменение в своём scope без несвязанного cleanup.
4. Запустить применимые formatter, lint, static analysis, unit, integration, contract и E2E tests.
5. Проверить архитектурные границы, отсутствие дублирования бизнес-правил и случайной связанности.
6. Обновить прогресс этапа и отметку в индексе roadmap по его правилам; отчитаться об изменениях, проверках, рисках и решениях.

Review проверяет корректность, edge cases, тесты, совместимость, сложность, миграции, race conditions и idempotency.
Классифицировать замечания как blocking / important / optional; blocking и important обычно исправлять сразу.

## 7. Автономность и ограничения

- Самостоятельно выбирать локальные имена, private helpers, организацию тестов и внутренний рефакторинг в scope.
- Без отдельной архитектурной задачи не менять границы сервисов, ownership contexts/DB, API/auth strategy,
  messaging technology, крупные runtime dependencies, порядок фаз и зафиксированные решения.
- При изменении архитектуры обновлять профильные документы; устойчивые решения фиксировать в документе 12 или отдельном ADR.
- Документировать изменения интеграций, событий, deployment model и testing strategy.
- До добавления dependency проверить существующие решения; не брать библиотеку ради тривиальной utility.
- Миграции делать узкими, reviewable, forward-safe и по возможности совместимыми с порядком деплоя; destructive изменения анализировать отдельно.
- Не коммитить secrets, не логировать credentials/tokens, не раскрывать exception details и sensitive values в frontend config.
- Backend отвечает за authorization и validation; client-side state не является источником доверия.
- Избегать N+1, больших агрегаций в PHP memory, полного dataset вместо summary и повторных тяжёлых расчётов без projections.
- SQL/backend выполняют тяжёлую обработку; оптимизировать по необходимости, без преждевременного усложнения.

## 8. Definition of Done

- Требуемое поведение реализовано; необходимые тесты добавлены и проходят, применимые lint/static analysis и CI проходят.
- Контракт, generated client, миграции и архитектурная документация обновлены, если затронуты.
- Нет обязательных незакрытых TODO и несвязанных изменений; соблюдены архитектура и текущая фаза roadmap.
- Предпочитать законченный vertical slice через UI, API, Application, Domain, persistence и tests незавершённому scaffolding.
- Не переходить к следующей фазе, пока обязательные exit criteria текущей не выполнены.
