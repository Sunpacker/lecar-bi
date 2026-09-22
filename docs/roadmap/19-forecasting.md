# Phase 19 — Forecasting Extension

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Добавить forecasting после зрелости основной аналитики.

## Возможности

Demand forecast, stock depletion forecast, reorder timing, uncertainty representation, forecast-vs-actual.

Forecasting сначала может оставаться в analytics service. Выделять отдельно только при необходимости другого runtime, Python ecosystem, independent scaling или independent deployment lifecycle.

## Exit Criteria

Forecast не выдаётся за детерминированный факт, assumptions документированы, есть historical evaluation, UI различает measured и forecast data.
