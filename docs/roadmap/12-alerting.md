# Phase 12 — Alerting

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Добавить actionable alerts.

## Функциональность

Create/enable/disable rule, evaluate conditions, create alert, acknowledge/resolve, active list, navigation к analytical context.

Начать с inventory-oriented rules, не строить универсальный rule engine заранее.

## Exit Criteria

Rules детерминированы, execution идемпотентно, повторная evaluation не создаёт uncontrolled duplicates, lifecycle покрыт тестами.
