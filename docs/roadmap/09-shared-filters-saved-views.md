# Phase 9 — Shared Filters and Saved Views

[Roadmap index and rules](ROADMAP.md) · [Agent router](../../AGENTS.md)

## Goal

Make dashboards reusable.

## Functionality

Dashboard-level filters, widget-level filters where needed, saved presets, reusable date ranges, consistent serialization, restoring saved state.

## Exit Criteria

Filtered views can be saved/restored, filter semantics are consistent, ownership is respected, and incompatible combinations are handled explicitly.

## Integration Checkpoint

Complete the [integration check](ROADMAP.md#integration-checkpoints) before finishing the phase.

---

## Progress

### Completed

1. **OpenAPI 3.0.3 Contract (`contracts/openapi/analytics-v1.yaml`):**
   - Added routes for managing saved dashboard views:
     - `GET /dashboards/{dashboardId}/views` — list saved views and filter presets;
     - `POST /dashboards/{dashboardId}/views` — create a saved view;
     - `GET /dashboards/{dashboardId}/views/{viewId}` — retrieve a saved view;
     - `PUT /dashboards/{dashboardId}/views/{viewId}` — update a view (name, filters, `is_default` flag);
     - `DELETE /dashboards/{dashboardId}/views/{viewId}` — delete a view (204 No Content).
   - Designed semantic schemas: `DashboardFilterValues` (`date_range`, `date_from`, `date_to`, `category_id`, `region_id`, `warehouse_id`, `stock_health`), `DashboardSavedView`, `DashboardSavedViewListResponse`, `DashboardSavedViewResponse`, `CreateDashboardSavedViewRequest`, `UpdateDashboardSavedViewRequest`.
   - Added the `filters` (`DashboardFilterValues`) property to `WidgetQueryConfig` to support local filter overrides at the widget level.
   - Contract validation passed (`npm run contracts:validate`); TypeScript types generated (`npm run api:generate`).
   - Added assertions to `ApiContractTest`.

2. **Domain Layer (`App\Modules\Dashboard\Domain`):**
   - Created the pure `SavedViewId` Value Object with UUID v4 (RFC 4122) generation and no external framework dependencies.
   - Created the `DashboardFilters` Value Object with date range validation (`date_from <= date_to`, throwing `InvalidFilterException`) and dataset projection methods: `forSalesDataset()` (strips unsupported `warehouse_id`, `stock_health`) and `forInventoryDataset()` (strips unsupported `region_id`).
   - Created the `SavedView` Entity with name invariants, filter serialization, `is_default` flag management, and timestamps.
   - Defined `SavedViewRepositoryInterface` (`findById`, `findByDashboardId`, `save`, `delete`, `clearDefault`).
   - Created `SavedViewNotFoundException` and `InvalidFilterException` domain exceptions.
   - Domain layer purity verified by `ArchitectureTest` (0 forbidden dependencies).
   - Covered by `SavedViewDomainTest` unit tests.

3. **Application Layer (`App\Modules\Dashboard\Application`):**
   - Developed DTOs: `DashboardFiltersDto`, `SavedViewDto`.
   - Implemented CQRS commands and handlers: `CreateSavedViewHandler`, `UpdateSavedViewHandler`, `DeleteSavedViewHandler`.
   - Implemented CQRS queries and handlers: `GetSavedViewsByDashboardHandler`, `GetSavedViewByIdHandler`.
   - Implemented strict access checks: access to a dashboard and its views is allowed only within the user's workspace (`workspace_id`); cross-workspace requests are rejected with `DashboardNotFoundException` / `404` or `403 Forbidden`.
   - Implemented default view exclusivity (`clearDefault` clears the flag on other dashboard views when a new default is set).
   - Covered by `SavedViewApplicationTest` unit tests.

4. **Infrastructure Layer (`App\Modules\Dashboard\Infrastructure`):**
   - Created the PostgreSQL migration `2026_09_23_000022_create_dashboard_saved_views_table.php` with a foreign key to `dashboards` (`cascadeOnDelete`), a `jsonb` `filters` field, and a composite `(dashboard_id, created_at)` index.
   - Created the typed `DashboardSavedViewModel` Eloquent model, casting `filters` to an array and `is_default` to boolean.
   - Added the `savedViews(): HasMany` relationship to `DashboardModel`.
   - Implemented `EloquentSavedViewRepository` with transactional default flag reset logic.
   - Implemented `InMemorySavedViewRepository` for isolated unit testing.
   - Registered the `SavedViewRepositoryInterface` binding in `AppServiceProvider`.
   - Covered by `SavedViewRepositoryTest`.

5. **Presentation Layer (`App\Modules\Dashboard\Presentation`):**
   - Developed Form Requests with input validation: `CreateSavedViewRequest` and `UpdateSavedViewRequest` (string fields, allowed period enum values, valid dates, and `date_to >= date_from`).
   - Implemented the `DashboardSavedViewController` REST controller (`index`, `store`, `show`, `update`, `destroy`) with `GetCurrentWorkspaceHandler` integration and standardized response codes (200, 201, 204, 403, 404, 422).
   - Registered routes in `backend/routes/api.php` within the protected `AuthenticateUserIdMiddleware` group.
   - Comprehensively tested in `DashboardSavedViewApiTest` and `DashboardFilterSemanticsTest`.

6. **Testing and Integration:**
   - Added filter semantic contract purity checks to `DashboardContractSemanticsTest`.
   - Extended the `scripts/verify-integration.sh` integration bash script with steps for creating, reading, modifying, isolating, and deleting saved dashboard views.
   - All automated checks (`make check`: OpenAPI lint, Prettier, ESLint, TypeScript, Vitest, Pint, PHPStan, PHPUnit) passed without errors (134 backend tests, 104 frontend tests).

7. **Frontend API Gateway & Filter Serialization (`frontend/src/features/dashboard/api`):**
   - Extended `DashboardGateway` with saved view management methods: `listSavedViews`, `getSavedView`, `createSavedView`, `updateSavedView`, `deleteSavedView`.
   - Covered by `dashboard-gateway.test.ts` unit tests (11 tests).

8. **Frontend Filter Domain Logic and Dataset Projection (`frontend/src/features/dashboard/model`):**
   - Implemented `filter-resolver.ts`:
     - Date preset resolution: `30d`, `90d`, `180d`, `365d`, `all`, `custom`;
     - Merging dashboard filters with widget-local overrides (`mergeFilters`);
     - Semantic filter projection and sanitization by dataset (`sanitizeFiltersForDataset`): stripping `warehouse_id` and `stock_health` for Sales, stripping `region_id` for Inventory;
     - Filter equality comparison (`isFiltersEqual`) and active filter checking (`hasActiveFilters`).
   - Covered by `filter-resolver.test.ts` unit tests (11 tests).

9. **Frontend State & URL Synchronization (`frontend/src/features/dashboard/model/use-dashboard-filters.ts`):**
   - Developed the `useDashboardFilters` hook:
     - Active view support (`activeViewId`), tracking changes relative to the saved preset (`isModifiedFromActiveView`);
     - Bidirectional filter synchronization with URL query parameters (`window.history.replaceState` without unnecessary re-renders);
     - Automatic default view selection (`is_default = true`) on load through pure derived state;
     - Management functions: `setFilters`, `patchFilters`, `resetFilters`, `applyView`, `saveCurrentAsView`, `updateCurrentView`, `removeView`, `toggleDefaultView`.
   - Covered by `use-dashboard-filters.test.ts` unit tests (4 tests).

10. **Frontend Widget Data Loading (`frontend/src/features/dashboard/model/widget-data-loader.ts`):**
    - Updated `loadWidgetData`: dashboard filter support, merging with widget-local overrides, date range resolution, parameter sanitization for the dataset, `stock_health` mapping (`low_stock` -> `critical`, `in_stock` -> `optimal`).
    - Covered by `widget-data-loader.test.ts` (9 tests).

11. **Frontend UI Components (`frontend/src/features/dashboard/ui`):**
    - `DashboardFilterBar`: filter bar with date period buttons, `date_from`/`date_to` selectors, category, region, warehouse, and stock status selection, and an active filter reset button.
    - `DashboardSavedViewsMenu`: saved view selection menu, current filter modification indicator (*), new preset creation (with an option to make it the default), default preset switching (star), and preset deletion.
    - Integration into `DashboardViewer` and filter propagation through `DashboardGrid` to `WidgetRenderer`.
    - Components covered by tests: `dashboard-filter-bar.test.tsx` (3 tests), `dashboard-saved-views-menu.test.tsx` (2 tests), `dashboard-viewer.test.tsx` (4 tests).

12. **End-to-End E2E Testing (`frontend/src/features/dashboard/ui/dashboard-saved-views-flow.test.tsx`):**
    - Wrote a comprehensive integration test: automatic default view loading -> period filter change (30d -> 90d) -> sales overview request with the updated interval -> saving a new preset through the modal menu -> filter reset.
    - Completed the full frontend validation cycle (Prettier format:check, ESLint, TypeScript check, 134 Vitest tests).

### Remaining Work in the Current Phase

All planned Phase 9 tasks have been completed successfully.

### Blockers

- None.

### Next Step

- Phase 9 is complete. Proceed to Phase 10 (Data Ingestion) according to the Roadmap.

---

## Completion Verification

- **Completion date:** 2026-09-23
- **Status:** Complete (all exit criteria confirmed).

### Exit Criteria Confirmation

1. **Filtered views can be saved/restored:**
   - Confirmed by backend tests: `DashboardSavedViewApiTest.php` (saving through POST, listing through GET, restoring by ID, updating through PUT, deleting through DELETE);
   - Confirmed by frontend tests: `dashboard-saved-views-flow.test.tsx` (saving a preset from active filters through the menu, restoring the default preset when mounting the dashboard);
   - Confirmed by the integration script: `scripts/verify-integration.sh` (steps 29–33).

2. **Filter semantics are consistent:**
   - A single `DashboardFilterValues` contract is finalized in OpenAPI 3.0.3 (`date_range`, `date_from`, `date_to`, `category_id`, `region_id`, `warehouse_id`, `stock_health`);
   - Confirmed by backend semantics tests: `DashboardFilterSemanticsTest.php` (strict period validation, `date_from <= date_to` check);
   - Confirmed by frontend tests: `filter-resolver.test.ts` (date calculations for `30d`, `90d`, `180d`, `365d`, `all`, `custom` presets).

3. **Ownership is respected:**
   - Strict `workspace_id` and `user_id` isolation implemented in `DashboardSavedViewController` and `WorkspaceAccessGuard`;
   - Attempts to access another user's views return `403 Forbidden` (`SavedViewApplicationTest.php`, `DashboardSavedViewApiTest.php`, `scripts/verify-integration.sh` step 32).

4. **Incompatible combinations are handled explicitly:**
   - Implemented parameter projection by dataset:
     - Sales Dataset: `warehouse_id` and `stock_health` are stripped;
     - Inventory Dataset: `region_id` is stripped;
   - Confirmed by backend domain tests (`DashboardFilters::forSalesDataset()`, `DashboardFilters::forInventoryDataset()`);
   - Confirmed by the frontend model (`filter-resolver.ts: sanitizeFiltersForDataset`).

### Checks Performed

- `npm run contracts:validate` — OpenAPI is valid (0 errors).
- `npm run lint`, `format:check`, `typecheck` — Frontend static analysis is clean (0 errors).
- `npm test` — 33 test files, 134 Vitest tests passed.
- `npm run build` — Next.js 16 production build completed without errors.
- `composer validate --strict` — `composer.json` is valid.
- `composer lint` — Pint and Larastan (maximum level) without issues.
- `composer test` — 134 PHPUnit tests (28141 assertions) passed.
- `scripts/verify-integration.sh` — All 35 end-to-end integration steps passed.
