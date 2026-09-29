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

## Integration Checkpoint

Перед завершением этапа пройти [интеграционную проверку](ROADMAP.md#integration-checkpoints).

## Прогресс

- Реализованы миграции схемы Star Schema (`backend/database/migrations`):
  - Измерения: `dim_dates` (календарные признаки: год, квартал, месяц, неделя, день недели, сезон, признак выходного), `dim_categories`, `dim_brands`, `dim_regions`, `dim_warehouses`, `dim_sales_channels`, `dim_suppliers`, `dim_products`.
  - Факты: `fact_orders`, `fact_order_items` (включая денормализованные ключи измерений, выручку, себестоимость и маржинальную прибыль), `fact_inventory_daily` (снимки остатков, резервов, доступного количества, неснижаемого остатка и точки заказа), `fact_supplier_deliveries` (заказы поставщикам, плановые и фактические сроки, задержки, объёмы).
- Все таблицы измерений и фактов (кроме универсального календаря `dim_dates`) строго привязаны к `workspace_id` со внешним ключом `workspaces(id)` и `cascadeOnDelete()`, что исключает утечку данных между арендаторами.
- Создан каталог автотоваров `DemoDataCatalog` с 8 категориями, 10 брендами, 24 товарами с реалистичными ценами, себестоимостью, ABC-классами и сезонными типами.
- Разработан детерминированный генератор `DemoDatasetGenerator` с фиксированным seed:
  - Годовой тренд роста (+15% YoY);
  - Выраженная сезонность (зимние шины и антифриз пикуют в Q4 в 3-4.5 раза, летние шины и салонные фильтры — в Q2);
  - Региональная специфика (ранний старт зимнего сезона в Сибири и на Урале);
  - Ситуации дефицита (stockout, `quantity_available <= 0`) и затоваривания (overstock, `days_of_stock > 120`);
  - Поставки с задержками и неполными отгрузками для будущей аналитики поставщиков.
- Реализован сидер `DemoDataSeeder`, вызываемый из `DatabaseSeeder`, а также консольная команда `php artisan demo:seed [--seed=42]`.
- Блокеров и незавершённых критериев Phase 3 нет.

## Проверка завершения

Дата: 2026-09-22.

Exit criteria полностью подтверждены: demo data генерируется на чистом окружении через `php artisan migrate --force --seed` и `demo:seed`, analytics функционирует без внешнего import, ownership каждого факта и измерения ясен (`workspace_id`), схема полноценно поддерживает Sales и Inventory аналитику, фикстуры воспроизводимы. Соответствие `docs/architecture/06-data-and-analytics.md` подтверждено.

- `make check` — Redocly OpenAPI validation, TypeScript generation, ESLint, Prettier, TypeScript typecheck, 9 тестов Vitest, сборка Next.js production, Composer strict validation, Pint, PHPStan и 26 тестов PHPUnit (27 023 assertions) успешно пройдены.
- `docker compose ... build backend` — независимый образ бэкенда успешно пересобран.
- `scripts/verify-integration.sh` подтвердил в работающем PostgreSQL:
  1. Корректную генерацию 4 453 заказов для `ws-1` и 4 455 заказов для `ws-2`;
  2. Наличие 9 дней дефицита (stockout) в снимках остатков;
  3. Сохранение полной изоляции рабочих пространств;
  4. Работоспособность frontend и backend health-эндпоинтов.
- `git diff --check` — форматирование и diff корректны.
