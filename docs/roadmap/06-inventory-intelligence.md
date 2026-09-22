# Phase 6 — Inventory Intelligence

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Добавить вторую крупную BI-область.

## Backend

Current inventory, stock quantity, average sales velocity, days of stock, critical stock, overstock, stock health, warehouse breakdown, product drill-down.

Inventory business rules принадлежат backend Domain/Application. При необходимости используются read models.

## Frontend

Inventory dashboard, critical stock, overstock, product details, warehouse/status filters.

## Exit Criteria

Расчёты покрыты тестами, classifications детерминированы, frontend не дублирует rules, queries производительны. Сверить с `docs/architecture/05-bounded-contexts.md` и `06-data-and-analytics.md`.

## Integration Checkpoint

Перед завершением этапа пройти [интеграционную проверку](ROADMAP.md#integration-checkpoints).
