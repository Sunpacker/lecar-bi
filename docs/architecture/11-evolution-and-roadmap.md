# 11. Evolution and Roadmap

The following are high-level architectural stages; their numbers do not match the implementation phases.
Task order, exit criteria, and completion status are recorded in the [roadmap index](../roadmap/ROADMAP.md).

## Evolution Principle

AutoBI must evolve incrementally.

The architecture must not be made more complex in advance for a hypothetical future.

New infrastructure is added when it solves an existing problem.

## Phase 1 — Foundation

Goals:

- monorepo;
- Next.js;
- Laravel;
- Docker;
- PostgreSQL;
- Redis;
- OpenAPI;
- CI;
- basic DDD modules;
- foundational architecture documentation.

## Phase 2 — Core and Demo Data

Goals:

- workspace;
- users;
- demo dataset;
- basic automotive e-commerce model;
- analytics layer preparation;
- test data seeding/import.

## Phase 3 — Sales Analytics

Goals:

- core KPIs;
- revenue;
- orders;
- average order value;
- trends;
- categories;
- regions;
- filters;
- drill-down.

## Phase 4 — Inventory Intelligence

Goals:

- stock levels;
- days of stock;
- critical stock levels;
- overstock;
- inventory turnover;
- ABC/XYZ;
- specialized read models.

## Phase 5 — Dashboard Builder

Goals:

- user dashboards;
- widgets;
- drag-and-drop layout;
- global filters;
- configuration persistence;
- state restoration.

## Phase 6 — Data Ingestion

Goals:

- file import;
- staging;
- validation;
- queues;
- import status;
- projections;
- error handling.

## Phase 7 — Alerts and Suppliers

Goals:

- supplier analytics;
- alerting rules;
- alert creation;
- domain events;
- integration events;
- outbox;
- background workers.

## Phase 8 — Production Architecture

Goals:

- RBAC;
- caching;
- observability;
- correlation identifiers;
- contract tests;
- end-to-end tests;
- performance optimization;
- a demonstration of connecting an external microservice.

## Possible Future Service Extraction

The following may be introduced in the future:

- ingestion service;
- notification service;
- forecasting service;
- identity service;
- other domain services.

Extraction must happen only once a stable boundary and an operational reason emerge.
