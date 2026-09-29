# Phase 8 — Dashboard Builder

[Roadmap index and rules](ROADMAP.md) · [Agent router](../../AGENTS.md)

## Goal

Allow users to build their own dashboards.

## Functionality

Create/rename/delete dashboard, add/remove/move/resize widget, metric/dimension/options, save/reload, workspace ownership, drag-and-drop grid, edit/view modes.

The backend contract must use semantic widget concepts, not React implementation details.

## Exit Criteria

Dashboards can be built and restored, ownership is enforced, frontend-specific state does not leak into the public contract without justification, and key builder flows are covered by E2E tests. Check against `docs/architecture/05-bounded-contexts.md` and `07-api-and-integration.md`.

## Integration Checkpoint

Complete the [integration check](ROADMAP.md#integration-checkpoints) before finishing the phase.

---

## Progress

### Completed

1. **Preliminary Fix:**
   - Fixed a regression in `frontend/src/shared/ui/layout/header.tsx`: added `ThemeToggle` with `aria-label="Переключить цветовую тему"` ("Toggle color theme"); all 60 frontend tests pass.

2. **OpenAPI Contract (`contracts/openapi/analytics-v1.yaml`):**
   - Added `/dashboards` (GET, POST) and `/dashboards/{id}` (GET, PUT, DELETE) endpoints.
   - Defined semantic schemas: `DashboardSummary`, `DashboardListResponse`, `DashboardDetail`, `DashboardDetailResponse`, `WidgetDetail`, `WidgetInput`, `WidgetGridPosition` (12-column `x, y, w, h` grid), `WidgetQueryConfig` (dataset `sales|inventory`, semantic metrics, dimensions, and date ranges), `CreateDashboardRequest`, `UpdateDashboardRequest`.
   - The contract is fully valid (Redocly CLI), TypeScript types were generated in `frontend/src/shared/api/generated/schema.ts`, and a test was added to `ApiContractTest`.

3. **Domain Layer (`App\Modules\Dashboard\Domain`):**
   - Developed pure Value Objects: `DashboardId` (with RFC 4122 UUID generation), `WidgetId`, `WidgetGridPosition` (with 12-column grid invariants), `WidgetQueryConfig`.
   - Added domain Enums: `WidgetType`, `DatasetType`, `MetricType`, `DimensionType`.
   - Developed the `Widget` entity and `Dashboard` Aggregate Root (widget management, renaming, invariants).
   - The `DashboardRepositoryInterface` repository interface and `DashboardNotFoundException`, `InvalidGridPositionException` domain exceptions.
   - Full compliance with architecture boundaries (`ArchitectureTest` confirms 0 forbidden dependencies), covered by `DashboardDomainTest` unit tests.

4. **Application Layer (`App\Modules\Dashboard\Application`):**
   - Created DTOs: `WidgetGridPositionDto`, `WidgetQueryConfigDto`, `WidgetDto`, `DashboardSummaryDto`, `DashboardDetailDto`.
   - Implemented CQRS Commands & Handlers: `CreateDashboardHandler`, `UpdateDashboardHandler`, `DeleteDashboardHandler`.
   - Implemented CQRS Queries & Handlers: `GetDashboardsHandler`, `GetDashboardByIdHandler`.
   - Covered by isolated `DashboardApplicationTest` tests.

5. **Infrastructure Layer (`App\Modules\Dashboard\Infrastructure`):**
   - Created PostgreSQL migrations: `2026_09_22_000020_create_dashboards_table.php` and `2026_09_22_000021_create_dashboard_widgets_table.php`, with cascading deletes and indexes.
   - Implemented Eloquent models `DashboardModel` and `DashboardWidgetModel`.
   - Implemented `EloquentDashboardRepository` with transactional widget synchronization and `InMemoryDashboardRepository` for fast isolated testing.
   - Registered the `DashboardRepositoryInterface` binding in `AppServiceProvider`.
   - Covered by `DashboardRepositoryTest`.

6. **Presentation Layer (`App\Modules\Dashboard\Presentation`):**
   - Developed Form Requests with validation: `CreateDashboardRequest` and `UpdateDashboardRequest`.
   - Implemented `DashboardController` (`index`, `store`, `show`, `update`, `destroy`) with strict `workspace_id` isolation through `GetCurrentWorkspaceHandler` and authentication.
   - Registered routes in `backend/routes/api.php`.
   - Created the `DashboardApiTest` Feature API test suite (tenant isolation, CRUD, 422 validation, 401/403/404 errors).

7. **Demo Data and Integration:**
   - Created `DashboardDatabaseSeeder` with starter dashboards for `ws-1` and `ws-2`, containing semantic revenue, order, sales trend, and inventory widgets. Registered it in `DatabaseSeeder`.
   - Extended `scripts/verify-integration.sh` with dashboard and workspace isolation checks.

8. **Frontend Dashboard Management & Viewer (View Mode):**
   - Implemented the typed `dashboardGateway` API gateway (`list`, `getById`, `create`, `update`, `delete`) with strict `workspace_id` isolation and error handling.
   - Developed the `widgetDataLoader` widget data adapter (`loadWidgetData`, `formatMetricValue`), connecting semantic configuration (`dataset`, `metric`, `dimension`, `date_range`) to sales and inventory analytics endpoints.
   - Developed a family of semantic widget components: `WidgetKpiCard`, `WidgetLineChart`, `WidgetBarChart`, `WidgetDonutChart`, `WidgetTable`, and the `WidgetRenderer` dispatcher, supporting loading skeletons and error states.
   - Implemented the responsive 12-column `DashboardGrid`, with positioning by `x, y, w, h` coordinates.
   - Created the `/dashboards` dashboard list page (`DashboardListView`) with cards, a dashboard creation modal panel (Sheet), and deletion.
   - Created the `/dashboards/[id]` detail page (`DashboardViewer`) with a refresh button, back action, and navigation.
   - Added `Dashboards` to the side navigation (`Sidebar`).
   - Covered by isolated unit and component tests (all 23 test suites and 82 frontend tests pass; typecheck and lint are clean).

9. **Frontend Dashboard Builder UI & Grid Editor (Edit Mode):**
   - Developed the `useDashboardBuilder` grid state and logic hook (`frontend/src/features/dashboard/model/use-dashboard-builder.ts`):
     - Mode management (`view` / `edit`), title and description editing;
     - Strict 12-column grid invariants (`0 <= x <= 11`, `1 <= w <= 12`, `x + w <= 12`, `y >= 0`, `1 <= h <= 24`);
     - Adding, editing, deleting, moving (`moveWidget`), and resizing (`resizeWidget`) widgets;
     - Change tracking (`isDirty`), saving through `dashboardGateway.update`, and discarding changes (`discardChanges`).
   - Developed the `WidgetConfigSheet` widget configuration slide-over panel (`frontend/src/features/dashboard/ui/widget-config-sheet.tsx`):
     - Semantic configuration: title, visualization type (`kpi_card`, `line_chart`, `bar_chart`, `donut_chart`, `table`), dataset (`sales`, `inventory`), metrics, dimensions (`dimension`), date periods (`date_range`), and grid sizes;
     - Dynamic switching of available metrics and dimensions when changing datasets;
     - Idiomatic key-based form state reset (React 19 without unnecessary side-effects).
   - Developed the interactive `DashboardGridEditor` and `WidgetEditorCard` (`frontend/src/features/dashboard/ui/dashboard-grid-editor.tsx`, `widget-editor-card.tsx`):
     - Toolbar with a drag handle, title, type badge, configuration and delete buttons;
     - Bottom bar with positioning buttons (left, right, up, down), disabled at grid boundaries;
     - Buttons for incremental width (`w`) and height (`h`) changes;
     - HTML5 drag-and-drop support for moving and rearranging widgets;
     - Empty grid state with a button to create the first widget.
   - Updated `DashboardViewer` (`frontend/src/features/dashboard/ui/dashboard-viewer.tsx`):
     - Toolbar for switching between "View" / "Edit" modes;
     - In edit mode: editable dashboard title and description, "Unsaved changes" badge, "Add widget", "Save" (with status indication), and "Cancel" buttons;
     - Switching between `DashboardGrid` and `DashboardGridEditor`, mounting `WidgetConfigSheet`.
   - Comprehensive test coverage:
     - Hook unit tests in `use-dashboard-builder.test.ts` (8 tests);
     - Component tests in `widget-config-sheet.test.tsx` (4 tests);
     - Grid editor component tests in `dashboard-grid-editor.test.tsx` (4 tests);
     - Integration tests in `dashboard-viewer.test.tsx` (3 tests);
     - Full frontend test run: 26 files, 100 tests pass without errors; `typecheck`, `lint`, and `format:check` are clean (0 errors);
     - Backend tests: 114 tests (27997 assertions) and Pint/PHPStan without errors.

10. **E2E Testing and Final Integration Checkpoint:**
    - Developed an end-to-end E2E integration test for `DashboardViewer` editor user scenarios (`frontend/src/features/dashboard/ui/dashboard-builder-flow.test.tsx`):
      - Full editing cycle: switching to edit mode, changing metadata (title, description), adding a semantic widget through `WidgetConfigSheet`, moving a widget within the 12-column grid and resizing it, saving through `dashboardGateway.update` while validating the purity of the submitted contract;
      - Canceling and discarding changes without modifying the original dashboard state.
    - Developed an end-to-end integration test for dashboard list management (`frontend/src/features/dashboard/ui/dashboard-list-flow.test.tsx`):
      - Creating a dashboard through the Sheet form, validating the `dashboardGateway.create` call and addition of a card to the DOM;
      - Deleting a dashboard with confirmation in a browser dialog and a `dashboardGateway.delete` call.
    - Developed a test for semantic contract purity and protection against frontend state leakage (`backend/tests/Feature/Modules/Dashboard/DashboardContractSemanticsTest.php`):
      - Checking the `WidgetInput`, `WidgetGridPosition`, and `WidgetQueryConfig` OpenAPI schemas for absence of UI-specific fields (`className`, `style`, `pixelWidth`, `domId`, etc.);
      - Checking that the API strips extra frontend properties when updating and retrieving dashboards;
      - Checking tenant isolation and ownership protection (cross-workspace update / delete returns 403 Forbidden).
    - Extended `scripts/verify-integration.sh` with the full dashboard CRUD lifecycle:
      - Creating a user dashboard through `POST /api/v1/dashboards`;
      - Reading and restoring through `GET /api/v1/dashboards/{id}`;
      - Updating widget configuration and moving widgets through `PUT /api/v1/dashboards/{id}`;
      - Checking access isolation (an access attempt by `user-2` returns 403 Forbidden);
      - Authenticated rendering checks for `/dashboards` and `/dashboards/{id}` frontend pages;
      - Deleting through `DELETE /api/v1/dashboards/{id}` and checking for 404 Not Found on a subsequent read.

### Remaining Work in the Current Phase

All planned Phase 8 tasks have been completed successfully.

### Blockers

- None.

### Next Step

- Phase 8 is complete. Proceed to Phase 9 (Shared Filters and Saved Views) according to the Roadmap.

---

## Completion Verification

- **Completion date:** 2026-09-23
- **Status:** Complete (all exit criteria confirmed).

### Exit Criteria Confirmation

1. **Dashboards can be built and restored:**
   Confirmed by unit, component, integration, and end-to-end tests. Users can create dashboards, add any semantic widget types (`kpi_card`, `line_chart`, `bar_chart`, `donut_chart`, `table`), configure their sources (`sales`, `inventory`), metrics, and time ranges, freely move and resize blocks in the 12-column grid (`x, y, w, h`), save state on the backend, and restore it on loading.
2. **Ownership enforced:**
   Confirmed by `DashboardApiTest`, `DashboardContractSemanticsTest`, and `scripts/verify-integration.sh`. Dashboards are strictly scoped to `workspace_id` and `user_id`. Cross-workspace read, update, and delete requests are immediately blocked with `403 Forbidden`.
3. **Frontend-specific state does not leak into the public contract without justification:**
   Confirmed by the automated `DashboardContractSemanticsTest::test_openapi_contract_does_not_leak_frontend_state` test and validation of `WidgetInput`, `WidgetGridPosition`, and `WidgetQueryConfig` schemas. The contract uses domain concepts exclusively, without React-specific details, CSS styles, or pixel-based layout.
4. **Key builder flows covered by E2E tests:**
   Developed an end-to-end test suite:
   - `dashboard-builder-flow.test.tsx` (full View/Edit cycle, creation, positioning, resizing, saving, reset);
   - `dashboard-list-flow.test.tsx` (creating and deleting dashboards from the catalog);
   - `scripts/verify-integration.sh` (end-to-end creation, reading, modification, isolation, and deletion through HTTP API and Next.js SSR).

### Automated Check Results (`make check`)

- `npm --prefix frontend run contracts:validate`: OK (OpenAPI 3.0.3 valid)
- `npm --prefix frontend run format:check`: OK (Prettier — all files formatted)
- `npm --prefix frontend run lint`: OK (ESLint 0 errors)
- `npm --prefix frontend run typecheck`: OK (TypeScript 0 errors)
- `npm --prefix frontend test`: OK (28 test files, 104 tests passed)
- `npm --prefix frontend run build`: OK (Next.js 16 production build succeeded, all static and dynamic routes compiled)
- `composer --working-dir=backend validate --strict`: OK (composer.json valid)
- `composer --working-dir=backend lint`: OK (Pint + PHPStan level max 0 errors)
- `composer --working-dir=backend test`: OK (117 tests, 28,037 assertions passed)
