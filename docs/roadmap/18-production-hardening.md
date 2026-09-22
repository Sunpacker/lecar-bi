# Phase 18 — Production Hardening

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Довести проект до portfolio-quality production demonstration.

## Работа

Security review, API compatibility, migration review, backup/restore docs, failure-mode review, rate limiting, timeouts, retries, queue failure handling, dependency audit, secrets review, deployment docs.

## Exit Criteria

Нет blocking security issues, clean environment deployable по документации, migrations работают с empty DB, failure behavior описан, CI green, critical E2E проходят. Сверить с `docs/architecture/09-infrastructure-deployment-observability.md` и `10-testing-and-quality.md`.

## Integration Checkpoint

Перед завершением этапа пройти [интеграционную проверку](README.md#integration-checkpoints).
