# Phase 15 — RBAC

[Индекс и правила roadmap](README.md) · [Маршрутизатор агентов](../../AGENTS.md)

## Цель

Добавить роли после стабилизации resource model.

## Функциональность

Workspace roles, permission checks, backend policies, capability-aware UI, protected dashboard/import/alert actions.

## Exit Criteria

Backend authoritative, frontend restrictions только UX, role changes протестированы, cross-workspace isolation сохраняется.

## Integration Checkpoint

Перед завершением этапа пройти [интеграционную проверку](ROADMAP.md#integration-checkpoints).

## Прогресс

- Зафиксирован OpenAPI контракт (`contracts/openapi/analytics-v1.yaml`): добавлены `WorkspaceRole`, закрытый список из 8 `WorkspaceCapability`, аннотации `x-required-capability` на всех защищённых эндпоинтах, эндпоинты списка участников (`GET /workspaces/{id}/members`) и изменения роли (`PATCH /workspaces/{id}/members/{userId}/role`).
- В Domain `Workspace` реализована чистая ролевая модель без зависимостей от Laravel: `MembershipRole` (`owner`, `member`, `viewer`), `WorkspaceCapability`, инвариант обязательного наличия хотя бы одного активного владельца (`LastWorkspaceOwnerException`).
- Реализована персистентность ролей в PostgreSQL (миграция `constrain_workspace_member_roles`), транзакционный лок строки воркспейса при смене роли (`LaravelWorkspaceTransactionManager`) для предотвращения race condition при конкурентной демоции.
- Реализованы CQRS команды и запросы: `ChangeWorkspaceMemberRoleCommand`, `GetWorkspaceMembersQuery`, `GetCurrentWorkspaceQuery` с передачей эффективных capabilities.
- Централизованная защита ресурсов через `RequireWorkspaceCapabilityMiddleware` и `WorkspaceAccessGuard`: маршруты дашбордов, сохранённых представлений, импорта данных, правил алертов и инцидентов защищены соответствующими capabilities; попытки несанкционированных мутаций возвращают HTTP 403 `INSUFFICIENT_CAPABILITY`.
- Frontend capability foundation: клиентский gateway, модель `hasCapability()`, fail-closed React-контекст `WorkspaceAccessProvider` и хук `useWorkspaceAccess`.
- Реализовано управление доступом в UI: компонент `WorkspaceMemberList`, страница `/settings/access`, защищённый пункт навигации «Доступ» в сайдбаре.
- Все ресурсные представления адаптированы под capabilities: кнопки мутаций дашбордов, пресетов, загрузки импортов и управления алертами скрываются при отсутствии соответствующих прав (`dashboards.manage`, `imports.manage`, `alerts.manage`).
- `WorkspaceDatabaseSeeder` дополнен тестовыми идентичностями `user-1` (owner), `user-3` (member), `user-4` (viewer).
- Скрипт `scripts/verify-integration.sh` дополнен полным сквозным сценарием проверки RBAC (шаги 47–54).
- Обновлена архитектурная документация (`03-frontend-nextjs.md`, `05-bounded-contexts.md`, `07-api-and-integration.md`).

## Проверка завершения

- **Дата:** 2026-09-23
- **Exit Criteria Status:** Все критерии выполнены в полном объеме:
  1. **Backend Authoritative:** Backend является единственным источником истины для авторизации. Любые прямые HTTP-запросы на мутации от роли `viewer` или неавторизованные действия блокируются со статусом HTTP 403 `INSUFFICIENT_CAPABILITY`.
  2. **Frontend Restrictions — Only UX:** Frontend никогда не вычисляет права из строковых ролей и использует capabilities, возвращённые backend в контракте `WorkspaceResponse`. При отсутствии контекста действует принцип fail-closed.
  3. **Role Changes Tested & Safe:** Смена ролей защищена транзакционной блокировкой воркспейса. Инвариант сохранения минимум одного владельца покрыт тестами на уровне Domain, Application, Database и HTTP API (HTTP 409 `LAST_WORKSPACE_OWNER`).
  4. **Cross-Workspace Isolation Preserved:** Изоляция рабочих пространств строго соблюдается для всех ролей (`owner`, `member`, `viewer`) до любых ресурсных операций.
- **Команды проверок и результаты:**
  - `npm --prefix frontend run contracts:validate` — OpenAPI валиден (OK)
  - `npm --prefix frontend run api:generate` — детерминированно сгенерирован TypeScript клиент (OK)
  - `composer --working-dir=backend validate --strict` — валидно (OK)
  - `composer --working-dir=backend lint` — Pint и PHPStan пройдены без ошибок (0 errors)
  - `composer --working-dir=backend test` — 326 тестов, 33272 assertions (OK)
  - `npm --prefix frontend run lint` — ESLint пройден без замечаний (OK)
  - `npm --prefix frontend run format:check` — Prettier форматирование соблюдено (OK)
  - `npm --prefix frontend run typecheck` — TypeScript компиляция без ошибок (OK)
  - `npm --prefix frontend test` — 48 тест-файлов, 217 тестов (OK)
  - `npm --prefix frontend run build` — Next.js production build успешен (OK)
  - `sh -n scripts/verify-integration.sh` — синтаксис скрипта интеграции валиден (OK)

