# AutoBI Architecture Documentation

This directory contains the project's architecture documentation.

The documentation records the foundational decisions on system structure, service boundaries, the DDD approach in Laravel, the analytics layer, integrations, infrastructure, and future development.

## Documentation Contents

- `00-overview.md` — product purpose and key architectural principles.
- `01-system-architecture.md` — overall system architecture and component responsibilities.
- `02-monorepo-and-services.md` — monorepo structure and service isolation rules.
- `03-frontend-nextjs.md` — architectural guidelines for the Next.js application.
- `04-backend-laravel-ddd.md` — DDD architecture of the Laravel microservice.
- `05-bounded-contexts.md` — initial bounded contexts and rules for their evolution.
- `06-data-and-analytics.md` — data storage, analytical models, and the read-heavy approach.
- `07-api-and-integration.md` — API contracts and inter-service communication.
- `08-events-outbox-async.md` — domain events, integration events, and asynchronous processes.
- `09-infrastructure-deployment-observability.md` — infrastructure, deployment, and observability.
- `10-testing-and-quality.md` — testing strategy and architectural constraints.
- `11-evolution-and-roadmap.md` — principles of system evolution and development stages.
- `12-architecture-decisions.md` — recorded architectural decisions.
- `rag-support-chat.md` — specification of the planned RAG support chat: MVP scope, access control, retrieval, API/SSE, operations, and quality criteria (ADR-019).

## Status

The documentation describes the target architecture for version 1 and serves as a baseline for future changes.

All significant architectural changes must be accompanied by updates to the relevant document and, where necessary, a separate ADR.
