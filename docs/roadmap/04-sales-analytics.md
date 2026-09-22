# Phase 4 — Sales Analytics Vertical Slice

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Первый полный BI-сценарий от PostgreSQL до UI.

## Backend

Sales summary, revenue, order count, average order value, sales trend, category breakdown, regional breakdown, date/category/region filters.

## Frontend

Sales dashboard, KPI cards, time-series, category/region visualizations, filters, loading/empty/error states.

## Правила

До параллельной frontend/backend реализации зафиксировать OpenAPI. Aggregation выполнять на backend/database, не в браузере.

## Exit Criteria

Dashboard работает на backend data, filters синхронны, calculations покрыты tests, API contract-tested, frontend не считает бизнес-метрики. Сверить с `docs/architecture/03-frontend-nextjs.md`, `06-data-and-analytics.md`, `07-api-and-integration.md`.

## Integration Checkpoint

Перед завершением этапа пройти [интеграционную проверку](README.md#integration-checkpoints).
