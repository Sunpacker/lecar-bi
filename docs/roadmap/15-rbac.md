# Phase 15 — RBAC

[Roadmap index and rules](ROADMAP.md) · [Agent router](../../AGENTS.md)

## Goal

Add roles after the resource model stabilizes.

## Functionality

Workspace roles, permission checks, backend policies, capability-aware UI, protected dashboard/import/alert actions.

## Exit Criteria

The backend is authoritative, frontend restrictions are UX only, role changes are tested, and cross-workspace isolation is preserved.

## Integration Checkpoint

Complete the [integration check](ROADMAP.md#integration-checkpoints) before closing the phase.

## Progress

- Defined the OpenAPI contract (`contracts/openapi/analytics-v1.yaml`): added `WorkspaceRole`, a closed list of 8 `WorkspaceCapability` values, `x-required-capability` annotations on all protected endpoints, and endpoints for listing members (`GET /workspaces/{id}/members`) and changing roles (`PATCH /workspaces/{id}/members/{userId}/role`).
- Implemented a pure role model in the `Workspace` Domain without Laravel dependencies: `MembershipRole` (`owner`, `member`, `viewer`), `WorkspaceCapability`, and the invariant requiring at least one active owner (`LastWorkspaceOwnerException`).
- Implemented role persistence in PostgreSQL (`constrain_workspace_member_roles` migration) and a transactional lock on the workspace row during role changes (`LaravelWorkspaceTransactionManager`) to prevent a race condition during concurrent demotion.
- Implemented CQRS commands and queries: `ChangeWorkspaceMemberRoleCommand`, `GetWorkspaceMembersQuery`, and `GetCurrentWorkspaceQuery`, passing effective capabilities.
- Centralized resource protection through `RequireWorkspaceCapabilityMiddleware` and `WorkspaceAccessGuard`: dashboard, saved view, data import, alert rule, and incident routes are protected by their respective capabilities; unauthorized mutation attempts return HTTP 403 `INSUFFICIENT_CAPABILITY`.
- Frontend capability foundation: client gateway, `hasCapability()` model, fail-closed React context `WorkspaceAccessProvider`, and `useWorkspaceAccess` hook.
- Implemented access management in the UI: `WorkspaceMemberList` component, `/settings/access` page, and protected "Access" sidebar navigation item.
- Adapted all resource views to capabilities: mutation buttons for dashboards, presets, import uploads, and alert management are hidden when the corresponding permissions are absent (`dashboards.manage`, `imports.manage`, `alerts.manage`).
- Extended `WorkspaceDatabaseSeeder` with test identities `user-1` (owner), `user-3` (member), and `user-4` (viewer).
- Extended `scripts/verify-integration.sh` with a full end-to-end RBAC verification scenario (steps 47–54).
- Updated architecture documentation (`03-frontend-nextjs.md`, `05-bounded-contexts.md`, `07-api-and-integration.md`).

## Completion Verification

- **Date:** 2026-09-23
- **Exit Criteria Status:** All criteria fully met:
  1. **Backend Authoritative:** The backend is the sole source of truth for authorization. Any direct HTTP mutation requests from the `viewer` role or unauthorized actions are blocked with HTTP 403 `INSUFFICIENT_CAPABILITY`.
  2. **Frontend Restrictions — Only UX:** The frontend never derives permissions from role strings and uses capabilities returned by the backend in the `WorkspaceResponse` contract. Missing context follows the fail-closed principle.
  3. **Role Changes Tested & Safe:** Role changes are protected by a transactional workspace lock. The invariant that at least one owner must remain is covered by Domain, Application, Database, and HTTP API tests (HTTP 409 `LAST_WORKSPACE_OWNER`).
  4. **Cross-Workspace Isolation Preserved:** Workspace isolation is strictly enforced for all roles (`owner`, `member`, `viewer`) before any resource operations.
- **Verification commands and results:**
  - `npm --prefix frontend run contracts:validate` — OpenAPI valid (OK)
  - `npm --prefix frontend run api:generate` — TypeScript client generated deterministically (OK)
  - `composer --working-dir=backend validate --strict` — valid (OK)
  - `composer --working-dir=backend lint` — Pint and PHPStan passed without errors (0 errors)
  - `composer --working-dir=backend test` — 326 tests, 33272 assertions (OK)
  - `npm --prefix frontend run lint` — ESLint passed without issues (OK)
  - `npm --prefix frontend run format:check` — Prettier formatting compliant (OK)
  - `npm --prefix frontend run typecheck` — TypeScript compilation without errors (OK)
  - `npm --prefix frontend test` — 48 test files, 217 tests (OK)
  - `npm --prefix frontend run build` — Next.js production build successful (OK)
  - `sh -n scripts/verify-integration.sh` — integration script syntax valid (OK)
