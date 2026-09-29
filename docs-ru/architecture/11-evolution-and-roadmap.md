# 11. Evolution and Roadmap

Ниже — укрупнённые архитектурные стадии, их номера не совпадают с этапами реализации.
Порядок задач, exit criteria и отметки выполнения находятся в [индексе roadmap](../roadmap/ROADMAP.md).

## Принцип развития

AutoBI должен развиваться постепенно.

Архитектура не должна усложняться заранее ради гипотетического будущего.

Новая инфраструктура добавляется тогда, когда она решает уже возникшую проблему.

## Phase 1 — Foundation

Цели:

- монорепозиторий;
- Next.js;
- Laravel;
- Docker;
- PostgreSQL;
- Redis;
- OpenAPI;
- CI;
- базовые DDD-модули;
- базовая архитектурная документация.

## Phase 2 — Core and Demo Data

Цели:

- workspace;
- пользователи;
- demo dataset;
- базовая модель automotive e-commerce;
- подготовка аналитического слоя;
- seed/import тестовых данных.

## Phase 3 — Sales Analytics

Цели:

- основные KPI;
- выручка;
- заказы;
- средний чек;
- тренды;
- категории;
- регионы;
- фильтры;
- drill-down.

## Phase 4 — Inventory Intelligence

Цели:

- остатки;
- days of stock;
- критический остаток;
- overstock;
- оборачиваемость;
- ABC/XYZ;
- специализированные read models.

## Phase 5 — Dashboard Builder

Цели:

- пользовательские dashboard;
- widgets;
- drag-and-drop layout;
- глобальные фильтры;
- сохранение конфигураций;
- восстановление состояния.

## Phase 6 — Data Ingestion

Цели:

- импорт файлов;
- staging;
- validation;
- очереди;
- import status;
- projections;
- обработка ошибок.

## Phase 7 — Alerts and Suppliers

Цели:

- supplier analytics;
- правила alerting;
- создание alert;
- domain events;
- integration events;
- outbox;
- фоновые workers.

## Phase 8 — Production Architecture

Цели:

- RBAC;
- caching;
- observability;
- correlation identifiers;
- contract tests;
- end-to-end tests;
- performance optimization;
- демонстрация подключения внешнего микросервиса.

## Возможное дальнейшее выделение сервисов

В будущем могут появиться:

- ingestion service;
- notification service;
- forecasting service;
- identity service;
- другие доменные сервисы.

Выделение должно происходить только после появления устойчивой границы и эксплуатационной причины.
