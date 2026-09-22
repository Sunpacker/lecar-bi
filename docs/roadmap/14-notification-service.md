# Phase 14 — Notification Service Extraction Exercise

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Доказать возможность реального расширения системы отдельным микросервисом.

Создать небольшой notification service после стабилизации Alerting/Outbox. Он должен читать versioned integration events, не ходить напрямую в analytics database, иметь независимый deployable unit и безопасно обрабатывать duplicate events.

Технологию менять только при архитектурной причине.

## Exit Criteria

Нет shared database, integration explicit, analytics service работает при недоступности notification service. Сверить с `docs/architecture/02-monorepo-and-services.md`, `07-api-and-integration.md`, `08-events-outbox-async.md`.

## Integration Checkpoint

Перед завершением этапа пройти [интеграционную проверку](README.md#integration-checkpoints).
