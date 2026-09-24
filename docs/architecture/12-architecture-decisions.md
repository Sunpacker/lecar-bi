# 12. Architecture Decisions

Статус отдельных планируемых расширений указан в соответствующем ADR; такой ADR не означает,
что реализация уже существует или что порядок roadmap изменён.

## ADR-001 — Monorepo

Решение: хранить frontend, backend, contracts, infrastructure и docs в одном Git-репозитории.

Причина: согласованная разработка, единая история изменений и удобное управление контрактами.

## ADR-002 — Independent Deployable Services

Решение: Next.js и Laravel являются отдельными deployable-сервисами.

Причина: независимый жизненный цикл и возможность масштабировать их отдельно.

## ADR-003 — Laravel as Analytics Microservice

Решение: Laravel выступает как самостоятельный analytics-service.

Причина: backend должен иметь собственную ответственность и не быть просто техническим приложением для frontend.

## ADR-004 — DDD inside Laravel

Решение: использовать DDD с разделением на bounded contexts.

Причина: проект имеет несколько независимых предметных областей и должен оставаться расширяемым.

## ADR-005 — Module First

Решение: организовывать backend сначала по bounded context, затем по архитектурным слоям.

Причина: облегчает понимание границ и последующее выделение модуля в отдельный сервис.

## ADR-006 — CQRS-lite

Решение: логически разделять commands и queries без обязательного физического разделения хранилищ.

Причина: BI является read-heavy системой и требует специализированных моделей чтения.

## ADR-007 — OpenAPI Contract

Решение: HTTP API описывается через OpenAPI.

Причина: единый формальный контракт и возможность генерации frontend-клиента.

## ADR-008 — Database per Service

Решение: каждый микросервис владеет собственной базой данных.

Причина: снижение связанности и обеспечение независимости сервисов.

## ADR-009 — Domain Events and Integration Events

Решение: разделять внутренние domain events и внешние integration events.

Причина: доменная модель не должна зависеть от способа межсервисной доставки сообщений.

## ADR-010 — Transactional Outbox

Решение: предусмотреть Outbox Pattern для публикации интеграционных событий.

Причина: повышение надёжности межсервисного обмена.

## ADR-011 — Redis

Решение: использовать Redis для технических задач, связанных с cache, queues, locks и rate limiting.

Причина: Laravel имеет зрелую интеграцию с Redis, а перечисленные сценарии хорошо соответствуют его назначению.

## ADR-012 — No Premature Microservices

Решение: не выделять отдельные микросервисы без необходимости.

Причина: избежать роста операционной сложности до появления реальных требований.

## ADR-013 — Frontend Has No Domain Business Rules

Решение: доменные вычисления и бизнес-правила выполняются на backend.

Причина: единая точка истины и возможность использования backend несколькими потребителями.

## ADR-014 — Analytics-specific Read Models

Решение: разрешить специализированные read models, materialized views и агрегированные структуры.

Причина: DDD не должен ухудшать производительность аналитических запросов.

## ADR-015 — Independent Infrastructure Evolution

Решение: message broker, distributed tracing и другие инфраструктурные компоненты добавляются постепенно.

Причина: архитектура должна быть готова к их подключению, но не обязана включать их в первый релиз.

## ADR-016 — shadcn/ui and Tailwind CSS

Решение: frontend обязан использовать shadcn/ui в связке с Tailwind CSS: shadcn/ui для переиспользуемых UI-компонентов, Tailwind CSS для стилизации и адаптивной вёрстки.

Причина: единый UI-стек обеспечивает согласованность интерфейса и упрощает поддержку общих компонентов.

Правила использования описаны в [архитектуре frontend](03-frontend-nextjs.md#обязательный-ui-стек).

## ADR-017 — Redis Stream as Replaceable Integration Event Transport

Решение: Redis Stream `autobi.integration-events` является начальным транспортом для integration events, реализованным как заменяемый адаптер через `IntegrationEventTransportInterface`. Domain и Application layers не зависят от Redis.

Причина: на текущем этапе полноценный message broker (RabbitMQ, Kafka) не нужен. Redis уже является обязательной инфраструктурной зависимостью. Транспорт инкапсулирован за портом, поэтому его замена не затронет Domain или Application.

Ограничения:

- Семантика доставки: at-least-once. Consumers обязаны дедуплицировать по `event_id`.
- Глобальный порядок событий не гарантируется.
- PostgreSQL outbox является источником истины; Redis Stream — только канал доставки.
- Retention Stream не управляется в Phase 13 — будет добавлен после появления consumer и observability.
- Consumer group будет создана в Phase 14.

## ADR-018 — Notification Service Extraction and Autonomous Bounded Context

Решение: выделить `notification/` как третий независимый deployable-сервис (Laravel 13, PHP 8.3) с собственной базой данных PostgreSQL, читающий `alert.triggered.v1` из Redis Stream `autobi.integration-events` через consumer group `notification-service-v1`.

Причина: подтвердить возможность масштабирования и модульного расширения системы отдельным сервисом без создания общей базы данных (ADR-008) и без преждевременного усложнения инфраструктуры (ADR-012, ADR-015).

Правила и ограничения:

- **Database per Service:** Notification Service владеет собственной базой данных PostgreSQL (`notification-postgres`). Никаких foreign keys, shared tables или доступа к analytics PostgreSQL.
- **Event-Driven Integration:** Сервисы обмениваются данными исключительно через асинхронные события. Нет прямых HTTP-вызовов из analytics в notification и обратно для обогащения данных.
- **Self-contained Contract:** Каноническое событие `alert.triggered.v1` содержит все данные (rule, severity, threshold, current value, analytical context), достаточные для формирования заголовка, текста и контекста уведомления.
- **At-Least-Once Delivery и Дедупликация:** Сервис гарантирует корректность при повторной доставке через таблицу `consumed_events` с уникальным первичным ключом `event_id`. Вставка проекции `notifications` и `consumed_events` выполняется в единой локальной транзакции.
- **Подтверждение (XACK) и Poison Messages:** `XACK` выполняется строго после коммита в БД или обнаружения дубликата. Невалидные сообщения и неподдерживаемые версии событий отправляются в dead-letter stream `autobi.integration-events.dead-letter` с последующим XACK.
- **Независимость жизненного цикла:** Временная недоступность или падение Notification Service не влияет на работу сервиса аналитики, HTTP API и публикацию Outbox. Накопленные события обрабатываются после восстановления работы consumer group.

## ADR-019 — RAG Support Chat

Статус: реализация в Phase 20, 2026-09-24. Контракт, модули и runtime-конфигурация подготовлены;
production rollout заблокирован до PostgreSQL/proxy checkpoint и внешней provider evaluation.

Решение: реализовать поддержку по документации в contexts `Support` и `KnowledgeBase` внутри
analytics-service. Использовать PostgreSQL/pgvector + FTS, Laravel Queue и существующую
Workspace/auth boundary. Next.js отображает чат и передаёт API/SSE, RAG orchestration принадлежит Laravel.

Причина: переиспользовать инфраструктуру и авторизацию платформы, сохранить DDD-границы
и измерять качество на небольшом корпусе до усложнения retrieval или выделения сервиса.

Основные ограничения:

- MVP работает только с явно опубликованной общей документацией/FAQ, без доступа к фактическим
  BI-данным и без tool calling. Приватные знания workspace — последующее расширение.
- Диалоги приватны по паре workspace/user; capability `support.use` не заменяет ownership.
- `KnowledgeBase` предоставляет публичный retrieval contract; SQL обеих поисковых веток ограничивает
  доступные документы до `LIMIT`. Начальный baseline — exact vector search и объединение рангов с FTS.
- Версии индекса публикуются атомарно; embedding profiles не смешиваются. Удалённые/отозванные
  материалы исключаются независимо от переиндексации.
- Создание generation выполняется идемпотентно через HTTP JSON; worker сохраняет состояние в PostgreSQL.
  SSE наблюдает за generation и восстанавливается полным snapshot без повторного запуска модели.
- Ошибки, бюджеты, citations, retention и измеримые evaluation gates входят в MVP.

Последствия: нужны pgvector в dev/test/deploy, отдельные очереди/worker capacity, диспетчер сохранённых
generation-задач и проверка streaming через proxy/BFF. Новые сервисы, broker и vector DB не требуются.
Расширение capabilities и API выполняется contract-first при реализации, вместе с generated client и тестами.

Подробное поведение, критерии приёмки и порядок работ: [RAG Support Chat](rag-support-chat.md).
