# Phase 2 — Identity, Workspace and Access Boundary

[Roadmap index and rules](ROADMAP.md) · [Agent router](../../AGENTS.md)

## Goal

Add minimal user/workspace concepts for BI resource ownership.

## Functionality

Identity integration, workspace model, membership, current workspace, authorization boundary, backend enforcement, frontend workspace context.

Do not introduce full RBAC yet.

## Exit Criteria

Users see only authorized workspaces, the backend checks ownership, cross-workspace access is impossible, and tests cover the boundaries. Check against `docs/architecture/05-bounded-contexts.md`.

## Integration Checkpoint

Complete the [integration check](ROADMAP.md#integration-checkpoints) before finishing the phase.

## Progress

- The OpenAPI contract was extended with `/me`, `/workspaces`, `/workspaces/{id}`, and `/workspaces/current` endpoints, the `UserResponse`, `WorkspaceResponse`, `WorkspaceListResponse`, `CurrentWorkspaceResponse`, and `ErrorResponse` schemas, and the `UserIdAuth` (`X-User-Id`) security scheme.
- A strictly typed TypeScript client was generated for the frontend without drift.
- The `Workspace` bounded context implements a pure Domain layer (`Workspace`, `User`, `Membership`, `WorkspaceId`, `UserId`, `MembershipRole`, exceptions, and repository interfaces) with zero dependencies on Laravel or external infrastructure (confirmed by `ArchitectureTest`).
- The Application Layer implements `WorkspaceAccessGuard`, DTOs, and query handlers to isolate access rights.
- The Infrastructure Layer provides migrations for `users`, `workspaces`, and `workspace_members`, Eloquent models and repositories for PostgreSQL, isolated InMemory repositories for tests, and a seeder with demo accounts.
- The Presentation Layer includes `AuthenticateUserIdMiddleware`, returning 401 for unauthenticated requests, and controllers that block access to another workspace with 403 Forbidden.
- The frontend includes the `workspace` feature: the typed `workspaceGateway`, the client-side `WorkspaceSwitcher`, and the `WorkspaceContextBar` top bar.
- Phase 2 has no blockers or outstanding criteria.

## Completion Verification

Date: 2026-09-22.

Exit criteria fully confirmed: users can access only authorized workspaces, the backend authorizes data ownership, cross-workspace access is blocked with 403 Forbidden, and the boundary is covered by unit, integration, and feature tests. Compliance with `docs/architecture/05-bounded-contexts.md` confirmed.

- `make check` — OpenAPI validation, TypeScript schema generation, eslint, prettier, tsc typecheck, 9 Vitest tests, Next.js production build, Composer strict validation, Pint, PHPStan, and 21 PHPUnit tests (107 assertions) passed.
- `docker compose ... build frontend` and `docker compose ... build backend` — both independent containers built without errors.
- `scripts/verify-integration.sh` on a clean docker compose stack confirmed:
  1. Backend health availability (`/api/v1/health` -> ok);
  2. Frontend health availability (`/api/health` -> ok);
  3. 401 Unauthorized for a request without `X-User-Id`;
  4. Correct filtering of available workspaces for `user-1` (`ws-1`);
  5. 403 Forbidden when `user-1` attempts to access `ws-2` (strict workspace isolation);
  6. Successful rendering of the home page with workspace context.
- `git diff --check` — formatting and diff are correct.
