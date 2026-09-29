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

Перед завершением этапа пройти [интеграционную проверку](ROADMAP.md#integration-checkpoints).

## Прогресс

- Frontend обновлён до Next.js 15 и React 19; настроены ESLint, Prettier, TypeScript, Vitest и production build.
- Health vertical slice использует server-side fetching и типизированную границу generated OpenAPI schema.
- Laravel foundation содержит регистрацию семи bounded contexts, contract tests, PHPStan, Pint и архитектурные тесты для module и Shared Domain.
- OpenAPI валидируется Redocly и генерирует воспроизводимую TypeScript schema; CI проверяет отсутствие drift.
- Docker dev и production targets запускают frontend, backend, PostgreSQL и Redis; сервисы имеют healthchecks и работают от непривилегированных пользователей.
- CI независимо проверяет contracts/frontend, backend и container builds, затем выполняет интеграционный checkpoint.
- Незавершённых критериев и блокеров Phase 1 нет.

## Проверка завершения

Дата: 2026-09-22.

Exit criteria подтверждены: локальная среда запускается, frontend получает backend health, frontend и backend собираются независимо, команды CI проходят локально, OpenAPI validation и client generation воспроизводимы, Domain защищён архитектурными тестами. Реализация сверена с `02-monorepo-and-services.md`, `03-frontend-nextjs.md` и `04-backend-laravel-ddd.md`.

- `make check` — OpenAPI validation/generation, frontend lint/format/typecheck, 5 frontend tests, Next.js production build, Composer validation, Pint, PHPStan и 5 backend tests (23 assertions) прошли.
- `npm audit --audit-level=high` — найдено 0 уязвимостей.
- `docker compose ... build frontend` и `docker compose ... build backend` — независимые production-образы собраны.
- `docker compose ... config --quiet` — конфигурация валидна.
- `scripts/verify-integration.sh` на чистом production-like stack — frontend и analytics health доступны, связь `web → analytics` подтверждена.
- Backend container запускается как `uid=1001(app)` и имеет необходимые права на runtime-каталоги Laravel.
- `git diff --check` — ошибок форматирования diff нет.
