# Phase 9: Integration Checkpoint and Phase Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Execute the Phase 9 mandatory integration checkpoint, verify all 4 Phase 9 exit criteria across backend and frontend, document completion in the roadmap, and officially close Phase 9 (Shared Filters and Saved Views) in `docs/roadmap/ROADMAP.md`.

**Architecture:** Phase 9 connects dashboard-level and widget-level shared filters with saved view presets across the full stack. The backend provides REST endpoints for saved views, pure domain value objects (`SavedViewId`, `DashboardFilters`), CQRS commands/queries, multi-tenancy access checks, and jsonb persistence. The frontend provides `DashboardGateway` methods, pure `filter-resolver` projections (isolating sales vs inventory datasets), reactive state via `useDashboardFilters` with URL query sync, and shadcn/ui components (`DashboardFilterBar`, `DashboardSavedViewsMenu`). Closing the phase requires executing full automated test suites, validating live multi-service integration checks, recording exit criteria evidence, and updating roadmap documentation.

**Tech Stack:** Next.js 16 (React 19), Laravel 11 (PHP 8.3), PostgreSQL 16, Redis 7, OpenAPI 3.0.3, Docker Compose, Vitest, PHPUnit, POSIX Shell (curl).

**Spec:** `docs/roadmap/09-shared-filters-saved-views.md`, `docs/roadmap/ROADMAP.md`, `AGENTS.md`, `docs/architecture/05-bounded-contexts.md`, `docs/architecture/07-api-and-integration.md`, `docs/architecture/10-testing-and-quality.md`, `docs/architecture/12-architecture-decisions.md`.

## Global Constraints

- Never close a phase before all exit criteria and mandatory checkpoints are verified (`AGENTS.md` §11, `ROADMAP.md` §Выполнение).
- Zero warnings or errors in the project quality gate: `npm run lint`, `npm run format:check`, `npm run typecheck`, `npm test`, `npm run build`, `composer validate --strict`, `composer lint`, `composer test`.
- All 4 Phase 9 exit criteria must be verified with concrete automated tests:
  1. Filtered view сохраняется/восстанавливается;
  2. Filter semantics едина;
  3. Ownership соблюдается (изоляция рабочих пространств);
  4. Несовместимые комбинации фильтров обрабатываются явно (семантическая проекция на датасеты).
- Live integration verification in `scripts/verify-integration.sh` checks 29-33 must pass against the running backend and web services.

---

### Task 1: Comprehensive Automated Test Suite & Static Analysis Verification

**Files:**
- Test: `contracts/openapi/analytics-v1.yaml`
- Test: `frontend/src/**/*.test.ts`, `frontend/src/**/*.test.tsx`
- Test: `backend/tests/**/*.php`

**Interfaces:**
- Consumes:
  - Redocly CLI for OpenAPI contract validation
  - Vitest & React Testing Library for frontend unit, component, and E2E flows
  - PHPUnit 11 & Larastan for backend domain, application, and API tests
- Produces:
  - Verified green baseline confirming zero regressions and 100% test pass rate across both services.

- [ ] **Step 1: Validate OpenAPI Contract & Generated TypeScript Types**

Run: `npm --prefix frontend run contracts:validate`
Expected: `validating ../contracts/openapi/analytics-v1.yaml... Woohoo! Your API description is valid.`

Run: `npm --prefix frontend run api:generate`
Expected: TypeScript types updated and in sync with `contracts/openapi/analytics-v1.yaml`.

- [ ] **Step 2: Run Full Frontend Static Analysis & Test Suite**

Run: `npm --prefix frontend run lint`
Expected: 0 lint errors.

Run: `npm --prefix frontend run format:check`
Expected: `All matched files use Prettier code style!`

Run: `npm --prefix frontend run typecheck`
Expected: 0 TypeScript compilation errors.

Run: `npm --prefix frontend test`
Expected: 33 test files passed, 134 tests passed.

Run: `npm --prefix frontend run build`
Expected: Production Next.js build succeeds with zero errors.

- [ ] **Step 3: Run Full Backend Static Analysis & Test Suite**

Run: `composer --working-dir=backend validate --strict`
Expected: `./composer.json is valid`.

Run: `composer --working-dir=backend lint`
Expected: `[OK] No errors` (Pint and Larastan check).

Run: `composer --working-dir=backend test`
Expected: 134 tests, 28141 assertions passed without errors.

- [ ] **Step 4: Commit untracked configuration or plan artifacts if present**

```bash
git add docs/superpowers/plans/2026-09-23-shared-filters-and-saved-views-frontend.md
git commit -m "docs(plans): save frontend shared filters and saved views implementation plan"
```

---

### Task 2: Live Integration Checkpoint Verification (`scripts/verify-integration.sh`)

**Files:**
- Verify: `scripts/verify-integration.sh`
- Verify: `infra/docker-compose.yml`

**Interfaces:**
- Consumes:
  - Running Docker Compose services (`autobi-backend-1`, `autobi-frontend-1`, `autobi-postgres-1`, `autobi-redis-1`)
  - Integration bash script with 35 assertion checkpoints
- Produces:
  - Confirmed exit code 0 from `scripts/verify-integration.sh` validating the end-to-end saved views workflow in production-like environment.

- [ ] **Step 1: Check Docker containers health status**

Run: `docker compose --env-file infra/.env.example -f infra/docker-compose.yml ps`
Expected: `autobi-backend-1`, `autobi-frontend-1`, `autobi-postgres-1`, `autobi-redis-1` all status "Up ... (healthy)".

- [ ] **Step 2: Run the full integration verification suite**

Run: `scripts/verify-integration.sh` (outside sandbox or with network access)
Expected:
```text
Integration check passed: web -> analytics health, identity, workspace access boundaries, demo dataset, sales overview, drill-down detail records, inventory intelligence, ABC/XYZ matrix, dashboard builder, and dashboard saved views CRUD lifecycle are verified.
```

- [ ] **Step 3: Verify Specific Phase 9 Integration Assertions**

Verify that checks 29-33 in `scripts/verify-integration.sh` execute successfully:
1. Check 29: `POST /api/v1/dashboards/{id}/views` creates view with `is_default=true`;
2. Check 30: `GET /api/v1/dashboards/{id}/views` lists views containing created preset;
3. Check 31: `PUT /api/v1/dashboards/{id}/views/{viewId}` updates name and filters;
4. Check 32: Cross-tenant isolation returns `403 Forbidden` for user-2 accessing user-1 views;
5. Check 33: `DELETE /api/v1/dashboards/{id}/views/{viewId}` deletes view returning `204 No Content`.

---

### Task 3: Roadmap Progress Documentation, Exit Criteria Matrix, and Phase 9 Closure

**Files:**
- Modify: `docs/roadmap/09-shared-filters-saved-views.md`
- Modify: `docs/roadmap/ROADMAP.md`

**Interfaces:**
- Consumes:
  - Verified test evidence from Tasks 1 and 2
- Produces:
  - Documented completion of Phase 9 with exit criteria matrix, verification dates, test results
  - Phase 9 marked completed (`[x]`) in `docs/roadmap/ROADMAP.md`

- [ ] **Step 1: Update `docs/roadmap/09-shared-filters-saved-views.md` with completion section**

Update the progress section and add `## Проверка завершения`:
```markdown
### Что осталось в текущей фазе

Все запланированные задачи фазы 9 успешно выполнены.

### Блокеры
- Отсутствуют.

### Следующий шаг
- Фаза 9 завершена. Переход к Phase 10 (Data Ingestion) в соответствии с Roadmap.

---

## Проверка завершения

- **Дата завершения:** 2026-09-23
- **Статус:** Выполнено (все exit criteria подтверждены).

### Подтверждение Exit Criteria

1. **Filtered view сохраняется/восстанавливается:**
   - Подтверждено бэкенд-тестами: `DashboardSavedViewApiTest.php` (сохранение через POST, чтение списка через GET, восстановление по ID, обновление через PUT, удаление через DELETE);
   - Подтверждено фронтенд-тестами: `dashboard-saved-views-flow.test.tsx` (сохранение пресета из активных фильтров через меню, восстановление дефолтного пресета при монтировании дашборда);
   - Подтверждено интеграционным скриптом: `scripts/verify-integration.sh` (шаги 29–33).

2. **Filter semantics едина:**
   - Единый контракт `DashboardFilterValues` зафиксирован в OpenAPI 3.0.3 (`date_range`, `date_from`, `date_to`, `category_id`, `region_id`, `warehouse_id`, `stock_health`);
   - Подтверждено бэкенд-тестами семантики: `DashboardFilterSemanticsTest.php` (строгая валидация периодов, проверка `date_from <= date_to`);
   - Подтверждено фронтенд-тестами: `filter-resolver.test.ts` (вычисление дат по пресетам `30d`, `90d`, `180d`, `365d`, `all`, `custom`).

3. **Ownership соблюдается:**
   - Строгая изоляция по `workspace_id` и `user_id` реализована в `DashboardSavedViewController` и `WorkspaceAccessGuard`;
   - Попытки доступа пользователя к чужим представлениям возвращают `403 Forbidden` (`SavedViewApplicationTest.php`, `DashboardSavedViewApiTest.php`, `scripts/verify-integration.sh` шаг 32).

4. **Несовместимые combinations обрабатываются явно:**
   - Реализована проекция параметров по наборам данных:
     - Sales Dataset: отсекаются `warehouse_id` и `stock_health`;
     - Inventory Dataset: отсекается `region_id`;
   - Подтверждено domain-тестами бэкенда (`DashboardFilters::forSalesDataset()`, `DashboardFilters::forInventoryDataset()`);
   - Подтверждено фронтенд-моделью (`filter-resolver.ts: sanitizeFiltersForDataset`).

### Выполненные проверки

- `npm run contracts:validate` — OpenAPI валиден (0 ошибок).
- `npm run lint`, `format:check`, `typecheck` — Frontend статический анализ чист (0 ошибок).
- `npm test` — 33 тестовых файла, 134 теста Vitest успешно пройдены.
- `npm run build` — Production сборка Next.js 16 собрана без ошибок.
- `composer validate --strict` — `composer.json` валиден.
- `composer lint` — Pint и Larastan (максимальный уровень) без замечаний.
- `composer test` — 134 теста PHPUnit (28141 assertions) успешно пройдены.
- `scripts/verify-integration.sh` — Все 35 сквозных шагов интеграции пройдены успешно.
```

- [ ] **Step 2: Update `docs/roadmap/ROADMAP.md` to mark Phase 9 completed**

In `docs/roadmap/ROADMAP.md`:
Replace:
```markdown
- [ ] [Phase 9 — Shared Filters and Saved Views](09-shared-filters-saved-views.md)
```
With:
```markdown
- [x] [Phase 9 — Shared Filters and Saved Views](09-shared-filters-saved-views.md)
```

- [ ] **Step 3: Commit Phase 9 completion to Git**

```bash
git add docs/roadmap/09-shared-filters-saved-views.md docs/roadmap/ROADMAP.md
git commit -m "docs(roadmap): close Phase 9 Shared Filters and Saved Views"
```
