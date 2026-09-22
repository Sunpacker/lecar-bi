# Phase 3 — Demo Data Model

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Создать воспроизводимый automotive e-commerce dataset для разработки аналитики без внешнего ingestion.

## Области данных

Products, categories, brands, warehouses, regions, sales channels, suppliers, orders, order items, inventory snapshots и необходимые delivery facts.

Создать базовые dimensions/facts для time, product, region, warehouse, sales и inventory.

Dataset должен демонстрировать trends, seasonality, stockouts, overstock и региональные/категорийные различия.

## Exit Criteria

Demo data генерируется на чистом окружении, analytics работает без import, ownership ясен, schema поддерживает Sales/Inventory, fixtures воспроизводимы. Сверить с `docs/architecture/06-data-and-analytics.md`.
