# Phase 13 — Domain Events and Transactional Outbox

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Подготовить систему к надёжному межсервисному обмену.

## Функциональность

Domain event conventions, integration mapping, event versioning, outbox storage/publisher, retry policy, published-state tracking, idempotency conventions.

Message broker пока не обязателен.

## Exit Criteria

Business transaction и outbox registration атомарны, retry работает, events versioned, duplicate strategy определена, transport не проникает в Domain. Сверить с `docs/architecture/08-events-outbox-async.md`.

## Integration Checkpoint

Перед завершением этапа пройти [интеграционную проверку](README.md#integration-checkpoints).
