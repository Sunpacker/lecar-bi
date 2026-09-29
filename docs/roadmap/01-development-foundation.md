# Phase 1 — Development Foundation

[Roadmap index and rules](ROADMAP.md) · [Agent router](../../AGENTS.md)

## Goal

Make both services runnable, testable, and independently buildable.

## Backend

- Laravel analytics service;
- PostgreSQL;
- Redis;
- DDD module conventions;
- bounded-context registration;
- testing structure;
- static analysis;
- formatting.

## Frontend

- Next.js App Router;
- feature-oriented structure;
- TypeScript quality rules;
- linting/formatting;
- tests.

## Contracts and Infrastructure

- OpenAPI as the source of truth;
- TypeScript client generation;
- API versioning convention;
- Docker environment;
- independent container builds;
- CI for frontend/backend/contracts/tests/build.

## Parallel Work

Frontend and Laravel foundations can be built in parallel. OpenAPI tooling must be ready before parallel feature development.

## Exit Criteria

The entire local environment starts, the frontend can reach the backend health endpoint, services build independently, CI is green, OpenAPI validation and client generation work, and architecture tests protect Domain. Compliance with `docs/architecture/02-monorepo-and-services.md`, `03-frontend-nextjs.md`, and `04-backend-laravel-ddd.md` has been verified.

## Integration Checkpoint

Complete the [integration check](ROADMAP.md#integration-checkpoints) before finishing the phase.

## Progress

- The frontend was upgraded to Next.js 15 and React 19; ESLint, Prettier, TypeScript, Vitest, and the production build are configured.
- The health vertical slice uses server-side fetching and a typed boundary based on the generated OpenAPI schema.
- The Laravel foundation includes registration of seven bounded contexts, contract tests, PHPStan, Pint, and architecture tests for modules and Shared Domain.
- OpenAPI is validated by Redocly and generates a reproducible TypeScript schema; CI checks for drift.
- Docker dev and production targets run the frontend, backend, PostgreSQL, and Redis; services have healthchecks and run as unprivileged users.
- CI independently checks contracts/frontend, backend, and container builds, then runs the integration checkpoint.
- Phase 1 has no outstanding criteria or blockers.

## Completion Verification

Date: 2026-09-22.

Exit criteria confirmed: the local environment starts, the frontend receives backend health, frontend and backend build independently, CI commands pass locally, OpenAPI validation and client generation are reproducible, and Domain is protected by architecture tests. The implementation was checked against `02-monorepo-and-services.md`, `03-frontend-nextjs.md`, and `04-backend-laravel-ddd.md`.

- `make check` — OpenAPI validation/generation, frontend lint/format/typecheck, 5 frontend tests, Next.js production build, Composer validation, Pint, PHPStan, and 5 backend tests (23 assertions) passed.
- `npm audit --audit-level=high` — 0 vulnerabilities found.
- `docker compose ... build frontend` and `docker compose ... build backend` — independent production images built.
- `docker compose ... config --quiet` — configuration is valid.
- `scripts/verify-integration.sh` on a clean production-like stack — frontend and analytics health are available; `web → analytics` connectivity confirmed.
- The backend container runs as `uid=1001(app)` and has the required permissions for Laravel runtime directories.
- `git diff --check` — no diff formatting errors.
