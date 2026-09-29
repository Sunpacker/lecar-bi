# 10. Testing and Quality

## General Strategy

Testing must align with the architectural layers and provide fast feedback when code changes.

## Domain Tests

The Domain Layer is tested primarily with unit tests.

Main goals:

- verify business rules;
- verify invariants;
- verify value objects;
- verify domain services;
- verify domain events.

Domain tests must not require starting Laravel or a database unless necessary.

## Application Tests

The Application Layer tests use cases.

These tests may use:

- fakes;
- mocks;
- in-memory repository implementations;
- test doubles for external interfaces.

The goal is to verify orchestration and correct interaction with Domain.

## Infrastructure Tests

The Infrastructure Layer must have integration tests.

They verify:

- PostgreSQL;
- Eloquent mappings;
- repository implementations;
- Redis;
- queues;
- integrations;
- migrations.

## Presentation Tests

The HTTP API must be covered by feature and API tests.

These verify:

- validation;
- status codes;
- serialization;
- authorization;
- API contract compliance.

## Frontend Tests

The frontend must include:

- unit tests for utilities;
- component tests;
- integration tests for complex UI flows;
- end-to-end tests for key user journeys.

## Contract Tests

The Laravel API must be checked for compliance with the published OpenAPI contract.

API changes must be detected before production.

## Architecture Tests

Key constraints should be checked automatically:

- Domain does not depend on Infrastructure;
- bounded contexts do not bypass public boundaries;
- forbidden framework dependencies do not enter Domain;
- the frontend does not import backend internals.

## Performance and Query Invariant Testing

Phase 16 introduced strict performance testing rules:

- **No wall-clock assertions in CI:** Assertions such as `$this->assertLessThan(200, $durationMs)` are strictly forbidden in standard CI tests to avoid false failures (flaky tests) across heterogeneous runners.
- **Invariants checked by CI:**
  1. _Functional parity:_ 100% payload equality across `cache=disabled`, `cache=cold`, and `cache=warm` states.
  2. _SQL query count (Query Count):_ A warm cache hit (`warm cache`) executes exactly 0 analytical SQL queries against facts. Paginated lists do not produce N+1 queries.
  3. _Query plan invariants (Plan Invariants):_ Check `EXPLAIN (ANALYZE, BUFFERS)` plans for unintended `Seq Scan` operations and sort spills to disk (`Temp Written Blocks = 0`, use of `Index Only Scan`).
  4. _Multi-tenancy isolation:_ Verify that cache data cannot leak between different `workspace_id` values.
- **Reproducible benchmarking:** p50/p95 percentile measurements (5 warm-up + 30 measured runs) are taken in an isolated reference Docker environment using `make performance-benchmark`, with artifacts saved in `docs/performance/`.

## Definition of Done

A feature is not complete without:

- tests at the appropriate level;
- an updated API contract when the interface changes;
- updated documentation when the architecture changes;
- migrations when the data schema changes;
- CI checks.

## Storybook Browser Checks

The frontend keeps its jsdom unit suite and has a separate Storybook Vitest browser configuration with Playwright Chromium. Vitest 4 is used because Storybook's Vitest addon does not yet support Vitest 5; the existing unit suite continues to run with the same command. Story play functions cover form validation, selection, Sheet focus, sorting, pagination, filter reset and retry. The accessibility addon enforces axe checks on stories. Frontend CI installs Chromium, runs `test:storybook`, and builds the static Storybook catalog after unit checks. Storybook is not published by this workflow.
