# Phase 2 — Identity, Workspace and Access Boundary

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Добавить минимальные user/workspace concepts для владения BI-ресурсами.

## Функциональность

Identity integration, workspace model, membership, current workspace, authorization boundary, backend enforcement, frontend workspace context.

Полноценный RBAC пока не вводить.

## Exit Criteria

Пользователь видит только разрешённый workspace, backend проверяет ownership, cross-workspace access невозможен, tests покрывают boundaries. Сверить с `docs/architecture/05-bounded-contexts.md`.
