# 02. Monorepo and Services

## General Principle

AutoBI is stored in a single Git repository.

The monorepo provides:

- a unified change history;
- coordinated version control;
- contract storage;
- shared CI configuration;
- convenient joint frontend and backend development;
- centralized architecture documentation.

## Service Separation

The monorepo contains three independently deployable services:

- `frontend/` — the Next.js web application (BFF, UI, dashboards);
- `backend/` — the Laravel analytics service (DDD, ingestion, sales/inventory/supplier/alerting, outbox);
- `notification/` — the Laravel notification service (Redis Stream consumer, projection repository, inbox deduplication).

Each service has:

- its own dependencies (`package.json` / `composer.json`);
- its own configuration layer (`.env.example`, `config/`);
- its own Dockerfile and build target;
- its own tests (Unit, Feature, Architecture);
- its own entry points (HTTP server, CLI consumer worker);
- its own build process and CI validation;
- its own database (PostgreSQL for analytics and a separate PostgreSQL for notification);
- its own isolated responsibility.

## Shared Directories

The monorepo may contain shared directories for:

- API contracts;
- event contracts;
- architecture documentation;
- shared development tools;
- infrastructure configuration;
- the generated API client.

## Shared Code Constraints

Do not create a shared business logic package used by both the frontend and backend.

Shared code must be limited to technical contracts and artifacts that truly need to be common to multiple services.

## Code Ownership

Each service must have a clear internal boundary.

Changing one service must not require access to another service's internal classes.

Communication takes place through APIs and integration events.
