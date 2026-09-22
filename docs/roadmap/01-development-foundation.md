# Phase 1 — Development Foundation

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Сделать оба сервиса запускаемыми, тестируемыми и независимо собираемыми.

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
- feature-oriented структура;
- TypeScript quality rules;
- linting/formatting;
- tests.

## Contracts и Infrastructure

- OpenAPI как source of truth;
- генерация TypeScript client;
- API versioning convention;
- Docker environment;
- независимые container builds;
- CI для frontend/backend/contracts/tests/build.

## Параллельная работа

Frontend и Laravel foundation можно делать параллельно. OpenAPI tooling должен быть готов до параллельной feature-разработки.

## Exit Criteria

Вся локальная среда запускается, frontend видит backend health endpoint, сервисы собираются независимо, CI green, OpenAPI validation и client generation работают, архитектурные тесты защищают Domain. Проверено соответствие `docs/architecture/02-monorepo-and-services.md`, `03-frontend-nextjs.md`, `04-backend-laravel-ddd.md`.

## Integration Checkpoint

Перед завершением этапа пройти [интеграционную проверку](README.md#integration-checkpoints).
