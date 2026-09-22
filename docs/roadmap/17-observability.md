# Phase 17 — Observability

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Сделать multi-service behavior диагностируемым.

## Функциональность

Structured logs, request/correlation IDs, health endpoints, application/queue/import metrics, error tracking, tracing preparation.

## Exit Criteria

Request прослеживается между frontend/backend logs, jobs имеют correlation context где нужно, health checks различают process/dependency state. Сверить с `docs/architecture/09-infrastructure-deployment-observability.md`.
