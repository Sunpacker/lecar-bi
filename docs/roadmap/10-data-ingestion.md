# Phase 10 — Data Ingestion

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Перейти к реальному import pipeline.

## Функциональность

Upload, validation, import record, staging, async processing, progress/status, validation failures, normalization, analytics projection update, safe retry.

Использовать Laravel queues. Невалидный import не должен повреждать валидные данные.

## Распределение

Gemini Pro анализирует formats/validation. Claude реализует или review bounded context. GPT-5.6 отвечает за pipeline integration и projection strategy.

## Exit Criteria

Import работает, progress виден, invalid rows понятны, retries безопасны, duplicate processing не портит данные, projection rebuild протестирован. Сверить с `docs/architecture/06-data-and-analytics.md` и `08-events-outbox-async.md`.

## Integration Checkpoint

Перед завершением этапа пройти [интеграционную проверку](README.md#integration-checkpoints).
