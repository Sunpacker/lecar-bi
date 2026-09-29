# Phase 2 — Identity, Workspace and Access Boundary

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Добавить минимальные user/workspace concepts для владения BI-ресурсами.

## Функциональность

Identity integration, workspace model, membership, current workspace, authorization boundary, backend enforcement, frontend workspace context.

Полноценный RBAC пока не вводить.

## Exit Criteria

Пользователь видит только разрешённый workspace, backend проверяет ownership, cross-workspace access невозможен, tests покрывают boundaries. Сверить с `docs/architecture/05-bounded-contexts.md`.

## Integration Checkpoint

Перед завершением этапа пройти [интеграционную проверку](ROADMAP.md#integration-checkpoints).

## Прогресс

- OpenAPI-контракт расширен эндпоинтами `/me`, `/workspaces`, `/workspaces/{id}`, `/workspaces/current` со схемами `UserResponse`, `WorkspaceResponse`, `WorkspaceListResponse`, `CurrentWorkspaceResponse`, `ErrorResponse` и схемой безопасности `UserIdAuth` (`X-User-Id`).
- Сгенерирован строго типизированный TypeScript-клиент для frontend без drift.
- В bounded context `Workspace` реализован чистый Domain слой (`Workspace`, `User`, `Membership`, `WorkspaceId`, `UserId`, `MembershipRole`, исключения и интерфейсы репозиториев) с нулевой зависимостью от Laravel и сторонней инфраструктуры (подтверждено `ArchitectureTest`).
- Application Layer реализует `WorkspaceAccessGuard`, DTO и обработчики запросов для изоляции прав доступа.
- Infrastructure Layer предоставляет миграции для таблиц `users`, `workspaces`, `workspace_members`, Eloquent-модели и репозитории для PostgreSQL, изолированные InMemory-репозитории для тестов, а также seeder с демонстрационными учётными записями.
- Presentation Layer включает `AuthenticateUserIdMiddleware`, возвращающий 401 для неаутентифицированных запросов, и контроллеры, пресекающие попытки доступа к чужому рабочему пространству кодом 403 Forbidden.
- Frontend содержит фичу `workspace`: типизированный шлюз `workspaceGateway`, клиентский переключатель `WorkspaceSwitcher` и верхнюю панель `WorkspaceContextBar`.
- Блокеров и незавершённых критериев Phase 2 нет.

## Проверка завершения

Дата: 2026-09-22.

Exit criteria полностью подтверждены: пользователь получает доступ только к разрешённому workspace, бэкенд авторизует владение данными, cross-workspace доступ блокируется с 403 Forbidden, boundary покрыта юнит-, интеграционными и фича-тестами. Соответствие `docs/architecture/05-bounded-contexts.md` подтверждено.

- `make check` — OpenAPI валидация, TypeScript schema generation, eslint, prettier, tsc typecheck, 9 тестов Vitest, сборка Next.js production, Composer strict validation, Pint, PHPStan и 21 тест PHPUnit (107 assertions) успешно пройдены.
- `docker compose ... build frontend` и `docker compose ... build backend` — оба независимых контейнера собраны без ошибок.
- `scripts/verify-integration.sh` на чистом docker compose стеке подтвердил:
  1. Доступность backend health (`/api/v1/health` -> ok);
  2. Доступность frontend health (`/api/health` -> ok);
  3. 401 Unauthorized при запросе без `X-User-Id`;
  4. Корректную фильтрацию доступных воркспейсов для `user-1` (`ws-1`);
  5. 403 Forbidden при попытке `user-1` обратиться к `ws-2` (строгая изоляция между воркспейсами);
  6. Успешный рендеринг главной страницы с контекстом воркспейса.
- `git diff --check` — форматирование и diff корректны.
