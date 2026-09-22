# Phase 8 — Dashboard Builder

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Позволить пользователю собирать собственные dashboard.

## Функциональность

Create/rename/delete dashboard, add/remove/move/resize widget, metric/dimension/options, save/reload, workspace ownership, drag-and-drop grid, edit/view modes.

Backend contract должен опираться на семантические widget concepts, а не React implementation details.

## Exit Criteria

Dashboard собирается и восстанавливается, ownership enforced, frontend-specific state не протекает в public contract без причины, основные builder flows покрыты E2E. Сверить с `docs/architecture/05-bounded-contexts.md` и `07-api-and-integration.md`.

## Integration Checkpoint

Перед завершением этапа пройти [интеграционную проверку](ROADMAP.md#integration-checkpoints).
