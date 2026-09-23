# Phase 12 — Alerting: Actionable Inventory Alerts & Rules Engine (Multi-Agent Implementation Plan)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the complete Phase 12 (Alerting) vertical slice: deterministic OpenAPI 3.0.3 contract, pure DDD domain model, PostgreSQL schema with dedup constraints, inventory-oriented evaluation engine, CQRS-lite commands/queries, lifecycle state machine (Open -> Acknowledged -> Resolved), REST API with strict multi-tenancy, and Next.js frontend UI with analytical context navigation (`/inventory`).

**Architecture:** Domain-Driven Design (DDD) in Laravel 11 under bounded context `Alerting` with strict architectural layer separation (Domain, Application, Infrastructure, Presentation). Domain layer is 100% pure PHP decoupled from Laravel/Illuminate. Evaluation engine evaluates inventory rules against latest `fact_inventory_daily` snapshots without coupling to internal models of `InventoryAnalytics`. Deduplication is guaranteed at both application and database levels via deterministic `dedup_fingerprint` ensuring multiple evaluations never create uncontrolled duplicate alerts. Frontend is feature-oriented (`features/alerts`) built with Next.js 16, React 19, Tailwind CSS, and shadcn/ui, featuring direct deep links back into inventory analysis.

**Tech Stack:** Laravel 11 (PHP 8.3), PostgreSQL 16, Redis (queues), OpenAPI 3.0.3, Redocly CLI, openapi-typescript 7, PHPUnit 11, Next.js 16 (React 19, TypeScript), Tailwind CSS, Lucide icons.

**Spec:** `docs/roadmap/12-alerting.md`, `docs/architecture/04-backend-laravel-ddd.md`, `docs/architecture/05-bounded-contexts.md`, `docs/architecture/06-data-and-analytics.md`, `docs/architecture/07-api-and-integration.md`, `docs/architecture/08-events-outbox-async.md`, `docs/architecture/10-testing-and-quality.md`, `docs/architecture/12-architecture-decisions.md`.

---

## Agent Roles & Orchestration Matrix

According to `AGENTS.md` and `docs/roadmap/12-alerting.md`, execution is distributed across specialized agent roles with strict write scopes and formal handoff gates:

| Агент | Роль в проекте | Задачи в данном плане | Write Scope | Read Scope |
|---|---|---|---|---|
| **Gemini Pro** | Repository / Analysis | Спецификация inventory rule types, условий вычисления, стратегии дедупликации (`dedup_fingerprint`) и навигации в аналитический контекст | `docs/superpowers/plans/*` | `contracts/**`, `backend/app/Modules/InventoryAnalytics/**`, `docs/architecture/**` |
| **GPT-5.6** | Интегратор / Архитектор (Phase A) | OpenAPI 3.0.3 контракт, генерация TypeScript-типов, миграции PostgreSQL (`alert_rules`, `alerts`) с индексами дедупликации, Eloquent-модели | `contracts/openapi/**`, `frontend/src/shared/api/**`, `backend/database/migrations/**`, `backend/app/Modules/Alerting/Infrastructure/Models/**` | `contracts/**`, `docs/architecture/07-api-and-integration.md` |
| **Claude Opus 4.6** | Domain / Business Logic | Чистая доменная модель `Alerting/Domain`: Aggregate Roots `AlertRule`, `Alert`, Value Objects, Enums, State Machine, Domain Events, интерфейсы репозиториев, юнит-тесты домена | `backend/app/Modules/Alerting/Domain/**`, `backend/tests/Unit/Modules/Alerting/**` | `docs/architecture/04-backend-laravel-ddd.md`, `docs/architecture/05-bounded-contexts.md`, `backend/tests/Unit/ArchitectureTest.php` |
| **GPT-5.6** | Интегратор / Evaluation Engine | Адаптер источника остатков (`PostgresInventoryAlertSource`), CQRS-команда оценки правил (`EvaluateAlertRulesCommand`), консольная команда CLI, асинхронный Job | `backend/app/Modules/Alerting/Application/**`, `backend/app/Modules/Alerting/Infrastructure/**`, `backend/tests/Feature/AlertEvaluationEngineTest.php` | `backend/app/Modules/Alerting/**`, `docs/architecture/06-data-and-analytics.md` |
| **Claude Opus 4.6** | Application Layer & Repositories | CQRS команды и запросы (управление правилами, acknowledge/resolve), In-Memory и Eloquent репозитории, биндинги в AppServiceProvider | `backend/app/Modules/Alerting/Application/**`, `backend/app/Modules/Alerting/Infrastructure/Repositories/**`, `backend/app/Providers/AppServiceProvider.php` | `backend/app/Modules/Alerting/Domain/**` |
| **GPT-5.6** | Presentation REST API & Seeders | REST контроллеры `AlertRuleController`, `AlertController`, Form Requests, маршруты `api.php`, демо-сидер дефолтных правил и алертов | `backend/app/Modules/Alerting/Presentation/**`, `backend/routes/api.php`, `backend/database/seeders/**`, `backend/tests/Feature/*ApiTest.php` | `backend/app/Modules/Alerting/**` |
| **Claude Opus 4.6** | Frontend Alerts Feature | UI-компоненты `features/alerts` (KPI cards, табло алертов, кнопки Acknowledge/Resolve, переход в аналитику, модалки правил), страница `/alerts`, разблокировка сайдбара | `frontend/src/features/alerts/**`, `frontend/app/(dashboard)/alerts/**`, `frontend/src/shared/ui/layout/sidebar.tsx` | `frontend/src/**`, `contracts/openapi/**` |
| **GPT-5.6** | Интеграционный checkpoint & финализация | Расширение `scripts/verify-integration.sh`, запуск сквозных тестов, закрытие Phase 12 в roadmap | `scripts/verify-integration.sh`, `docs/roadmap/12-alerting.md`, `docs/roadmap/ROADMAP.md` | Корень репозитория |

---

## Global Constraints

- **Strict DDD Isolation:** Domain Layer in `App\Modules\Alerting\Domain` MUST NOT depend on Laravel/Illuminate, framework helpers (`config`, `now`, etc.), or other bounded contexts (`ArchitectureTest`).
- **Context Boundaries:** `Alerting` MUST NOT directly query or depend on internal Eloquent models of `InventoryAnalytics`. Data access is performed through `InventoryAlertSourceInterface` in `Application/Contracts`.
- **Multi-Tenancy:** All rules and alerts are strictly scoped by `workspace_id`. Cross-workspace requests must return `403 Forbidden`.
- **Deterministic & Idempotent Evaluation:** Multiple executions of the evaluation engine on identical data MUST NOT create duplicate active alerts. Duplicate detection uses `dedup_fingerprint = sha1(workspace_id . ':' . rule_id . ':' . product_id . ':' . warehouse_id)`.
- **Database-Level Protection:** Database constraints/indexes enforce that only ONE active alert (`open` or `acknowledged`) can exist for a given `(workspace_id, dedup_fingerprint)`.
- **Alert Lifecycle:** An alert transitions `OPEN -> ACKNOWLEDGED -> RESOLVED`. Resolving closes the alert with timestamp and optional note. Re-evaluation of a resolved alert with persisting condition will create a new alert cycle; active alerts only update `current_value` and `updated_at`.
- **Actionable Context Navigation:** Every alert must contain analytical context metadata linking directly to `/inventory?search={sku}&warehouse_id={warehouse_id}`.
- **OpenAPI as Single Source of Truth:** Contracts are edited in `contracts/openapi/analytics-v1.yaml` and TypeScript types generated via `npm --prefix frontend run api:generate`.

---

### Task 1: [Gemini Pro] Анализ структуры правил, инвентарных метрик и дедупликации

**Role:** Gemini Pro (Repository / Analysis)
**Write Scope:** `docs/superpowers/plans/*` (спецификация в контексте плана)
**Read Scope:** `backend/app/Modules/InventoryAnalytics/**`, `backend/database/migrations/2026_09_22_000011_create_analytics_facts_tables.php`, `docs/roadmap/12-alerting.md`.

- [ ] **Step 1: Инвентаризация типов правил (Inventory-Oriented Rules)**
  - 1. **OUT_OF_STOCK (Дефицит / Нулевой остаток):**
    - Метрика: `quantity_available`
    - Компаратор: `<=` 0
    - Дефолтная важность: `critical`
    - Описание: Товар полностью закончился на складе.
  - 2. **CRITICAL_STOCK (Критический остаток по дням запаса):**
    - Метрика: `days_of_stock`
    - Компаратор: `<=` `threshold_value` (например, 7.0 дней) или `quantity_available <= safety_stock`
    - Дефолтная важность: `warning`
    - Описание: Дней запаса недостаточно для обеспечения спроса.
  - 3. **OVERSTOCK (Затоваривание / Избыток):**
    - Метрика: `days_of_stock`
    - Компаратор: `>` `threshold_value` (например, 60.0 дней)
    - Дефолтная важность: `info`
    - Описание: Избыточный запас замораживает оборотный капитал.
  - 4. **REORDER_POINT (Достигнута точка дозаказа):**
    - Метрика: `quantity_available`
    - Компаратор: `<=` `reorder_point`
    - Дефолтная важность: `warning`
    - Описание: Требуется разместить заказ у поставщика.

- [ ] **Step 2: Определение формулы `dedup_fingerprint`**
  - Детерминированный ключ: `sha1(sprintf('%s:%s:%s:%s', $workspaceId, $ruleId, $productId ?? 'all', $warehouseId ?? 'all'))`.
  - Условие уникальности: `workspace_id + dedup_fingerprint` уникальны среди алертов со статусом `IN ('open', 'acknowledged')`.

---

### Task 2: [GPT-5.6] OpenAPI 3.0.3 Контракт и типизация

**Role:** GPT-5.6 (Интегратор / Архитектор)
**Files:**
- Modify: `contracts/openapi/analytics-v1.yaml`
- Modify: `backend/tests/Feature/ApiContractTest.php`
- Generated: `frontend/src/shared/api/generated/schema.ts`

**Interfaces:**
- Consumes: Existing OpenAPI schemas (`ErrorResponse`, `UserIdAuth`)
- Produces:
  - `GET /alert-rules` -> `AlertRuleListResponse`
  - `POST /alert-rules` -> `AlertRuleDetailResponse`
  - `GET /alert-rules/{id}` -> `AlertRuleDetailResponse`
  - `PUT /alert-rules/{id}` -> `AlertRuleDetailResponse`
  - `DELETE /alert-rules/{id}` -> 204 No Content
  - `POST /alert-rules/{id}/toggle` -> `AlertRuleDetailResponse`
  - `POST /alert-rules/evaluate` -> `AlertEvaluationResultResponse`
  - `GET /alerts` -> `AlertListResponse` (query: `status`, `severity`, `warehouse_id`, `rule_id`, `page`, `per_page`)
  - `GET /alerts/summary` -> `AlertSummaryResponse`
  - `GET /alerts/{id}` -> `AlertDetailResponse`
  - `POST /alerts/{id}/acknowledge` -> `AlertDetailResponse`
  - `POST /alerts/{id}/resolve` -> `AlertDetailResponse`

- [ ] **Step 1: Write failing test in `backend/tests/Feature/ApiContractTest.php`**

```php
public function test_contract_contains_alerting_endpoints_and_schemas(): void
{
    $contract = $this->openApiContract();

    self::assertArrayHasKey('/alert-rules', $contract['paths']);
    self::assertArrayHasKey('/alert-rules/{id}', $contract['paths']);
    self::assertArrayHasKey('/alert-rules/{id}/toggle', $contract['paths']);
    self::assertArrayHasKey('/alert-rules/evaluate', $contract['paths']);
    self::assertArrayHasKey('/alerts', $contract['paths']);
    self::assertArrayHasKey('/alerts/summary', $contract['paths']);
    self::assertArrayHasKey('/alerts/{id}', $contract['paths']);
    self::assertArrayHasKey('/alerts/{id}/acknowledge', $contract['paths']);
    self::assertArrayHasKey('/alerts/{id}/resolve', $contract['paths']);

    $schemas = $contract['components']['schemas'];
    self::assertArrayHasKey('AlertRule', $schemas);
    self::assertArrayHasKey('AlertRuleListResponse', $schemas);
    self::assertArrayHasKey('AlertRuleDetailResponse', $schemas);
    self::assertArrayHasKey('CreateAlertRuleRequest', $schemas);
    self::assertArrayHasKey('UpdateAlertRuleRequest', $schemas);
    self::assertArrayHasKey('Alert', $schemas);
    self::assertArrayHasKey('AlertListResponse', $schemas);
    self::assertArrayHasKey('AlertDetailResponse', $schemas);
    self::assertArrayHasKey('AlertSummaryResponse', $schemas);
    self::assertArrayHasKey('AlertEvaluationResultResponse', $schemas);
    self::assertArrayHasKey('AlertSeverity', $schemas);
    self::assertArrayHasKey('AlertStatus', $schemas);
    self::assertArrayHasKey('RuleType', $schemas);
}
```

- [ ] **Step 2: Run test to verify it fails**
Run: `composer --working-dir=backend test -- --filter=test_contract_contains_alerting_endpoints_and_schemas`
Expected: FAIL (missing paths and schemas)

- [ ] **Step 3: Update `contracts/openapi/analytics-v1.yaml` with Alerting definitions**
Add routes for `/alert-rules`, `/alert-rules/{id}`, `/alert-rules/{id}/toggle`, `/alert-rules/evaluate`, `/alerts`, `/alerts/summary`, `/alerts/{id}`, `/alerts/{id}/acknowledge`, `/alerts/{id}/resolve`, and their respective request/response schemas.

- [ ] **Step 4: Run Redocly lint and generate frontend TypeScript types**
Run: `npm --prefix frontend run contracts:validate`
Run: `npm --prefix frontend run api:generate`
Run: `composer --working-dir=backend test -- --filter=test_contract_contains_alerting_endpoints_and_schemas`
Expected: PASS

- [ ] **Step 5: Commit**
```bash
git add contracts/openapi/analytics-v1.yaml backend/tests/Feature/ApiContractTest.php frontend/src/shared/api/generated/schema.ts
git commit -m "feat(alerting): add OpenAPI 3.0.3 contract and generated TypeScript types"
```

---

### Task 3: [GPT-5.6] PostgreSQL Миграции схемы данных Alerting

**Role:** GPT-5.6 (Интегратор / Архитектор)
**Files:**
- Create: `backend/database/migrations/2026_09_23_000040_create_alert_rules_table.php`
- Create: `backend/database/migrations/2026_09_23_000041_create_alerts_table.php`
- Create: `backend/tests/Feature/AlertingDatabaseMigrationTest.php`

- [ ] **Step 1: Write migration test `AlertingDatabaseMigrationTest.php`**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class AlertingDatabaseMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_rules_and_alerts_tables_exist_with_proper_columns(): void
    {
        self::assertTrue(Schema::hasTable('alert_rules'));
        self::assertTrue(Schema::hasColumns('alert_rules', [
            'id', 'workspace_id', 'name', 'description', 'rule_type',
            'severity', 'metric', 'comparator', 'threshold_value',
            'warehouse_id', 'category_id', 'product_id', 'is_enabled',
            'created_at', 'updated_at',
        ]));

        self::assertTrue(Schema::hasTable('alerts'));
        self::assertTrue(Schema::hasColumns('alerts', [
            'id', 'workspace_id', 'rule_id', 'rule_name', 'severity',
            'status', 'dedup_fingerprint', 'product_id', 'product_name',
            'product_sku', 'warehouse_id', 'warehouse_name', 'current_value',
            'threshold_value', 'context_data', 'triggered_at',
            'acknowledged_at', 'acknowledged_by', 'resolved_at',
            'resolved_by', 'resolution_note', 'created_at', 'updated_at',
        ]));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**
Run: `composer --working-dir=backend test -- --filter=AlertingDatabaseMigrationTest`
Expected: FAIL (tables do not exist)

- [ ] **Step 3: Create migrations `2026_09_23_000040_create_alert_rules_table.php` & `2026_09_23_000041_create_alerts_table.php`**
Implement migrations with PostgreSQL index on `alert_rules (workspace_id, is_enabled)` and unique composite index on `alerts (workspace_id, dedup_fingerprint, status)` or partial index for active alerts to guarantee zero duplicate active alerts.

- [ ] **Step 4: Run test to verify it passes**
Run: `composer --working-dir=backend test -- --filter=AlertingDatabaseMigrationTest`
Expected: PASS

- [ ] **Step 5: Commit**
```bash
git add backend/database/migrations/*alert*.php backend/tests/Feature/AlertingDatabaseMigrationTest.php
git commit -m "feat(alerting): add PostgreSQL migrations for alert_rules and alerts"
```

---

### Task 4: [Claude Opus 4.6] Pure DDD Domain Model & Lifecycle State Machine

**Role:** Claude Opus 4.6 (Domain / Business Logic)
**Files:**
- Create: `backend/app/Modules/Alerting/Domain/AlertSeverity.php`
- Create: `backend/app/Modules/Alerting/Domain/AlertStatus.php`
- Create: `backend/app/Modules/Alerting/Domain/RuleType.php`
- Create: `backend/app/Modules/Alerting/Domain/RuleMetric.php`
- Create: `backend/app/Modules/Alerting/Domain/RuleComparator.php`
- Create: `backend/app/Modules/Alerting/Domain/AlertRuleId.php`
- Create: `backend/app/Modules/Alerting/Domain/AlertId.php`
- Create: `backend/app/Modules/Alerting/Domain/RuleCondition.php`
- Create: `backend/app/Modules/Alerting/Domain/RuleScope.php`
- Create: `backend/app/Modules/Alerting/Domain/AlertContext.php`
- Create: `backend/app/Modules/Alerting/Domain/DedupFingerprint.php`
- Create: `backend/app/Modules/Alerting/Domain/AlertRule.php`
- Create: `backend/app/Modules/Alerting/Domain/Alert.php`
- Create: `backend/app/Modules/Alerting/Domain/Events/AlertTriggered.php`
- Create: `backend/app/Modules/Alerting/Domain/Events/AlertAcknowledged.php`
- Create: `backend/app/Modules/Alerting/Domain/Events/AlertResolved.php`
- Create: `backend/app/Modules/Alerting/Domain/Repositories/AlertRuleRepositoryInterface.php`
- Create: `backend/app/Modules/Alerting/Domain/Repositories/AlertRepositoryInterface.php`
- Create: `backend/app/Modules/Alerting/Domain/Exceptions/AlertRuleNotFoundException.php`
- Create: `backend/app/Modules/Alerting/Domain/Exceptions/AlertNotFoundException.php`
- Create: `backend/app/Modules/Alerting/Domain/Exceptions/InvalidAlertStateTransitionException.php`
- Test: `backend/tests/Unit/Modules/Alerting/AlertDomainTest.php`
- Test: `backend/tests/Unit/Modules/Alerting/AlertRuleDomainTest.php`

- [ ] **Step 1: Write failing tests `AlertDomainTest.php` & `AlertRuleDomainTest.php`**
Test aggregate roots:
- `AlertRule`: creation, enable/disable toggle, update, condition matching.
- `Alert`: creation in `OPEN` status, transitions: `acknowledge()` sets `acknowledged_at` and `acknowledged_by`, `resolve()` sets `resolved_at` and `resolved_by`.
- Invalid transition test: calling `acknowledge()` on `RESOLVED` alert throws `InvalidAlertStateTransitionException`.
- Re-triggering active alert updates current value and timestamp without altering state.

- [ ] **Step 2: Run tests to verify they fail**
Run: `composer --working-dir=backend test -- --filter=AlertDomainTest`
Expected: FAIL

- [ ] **Step 3: Implement pure Domain layer in `backend/app/Modules/Alerting/Domain`**
Implement all Value Objects, Enums, Entities, Aggregates, Exceptions, and Repository Interfaces in pure PHP without Laravel framework imports.

- [ ] **Step 4: Run tests and verify ArchitectureTest**
Run: `composer --working-dir=backend test -- --filter=AlertDomainTest`
Run: `composer --working-dir=backend test -- --filter=AlertRuleDomainTest`
Run: `composer --working-dir=backend test -- --filter=ArchitectureTest`
Expected: ALL PASS with 0 architectural violations.

- [ ] **Step 5: Commit**
```bash
git add backend/app/Modules/Alerting/Domain/** backend/tests/Unit/Modules/Alerting/**
git commit -m "feat(alerting): implement pure DDD domain model and alert lifecycle state machine"
```

---

### Task 5: [Claude Opus 4.6 / GPT-5.6] Persistence Layer & Repository Implementations

**Role:** Claude Opus 4.6 / GPT-5.6
**Files:**
- Create: `backend/app/Modules/Alerting/Infrastructure/Models/AlertRuleModel.php`
- Create: `backend/app/Modules/Alerting/Infrastructure/Models/AlertModel.php`
- Create: `backend/app/Modules/Alerting/Infrastructure/Repositories/EloquentAlertRuleRepository.php`
- Create: `backend/app/Modules/Alerting/Infrastructure/Repositories/EloquentAlertRepository.php`
- Create: `backend/app/Modules/Alerting/Infrastructure/Repositories/InMemoryAlertRuleRepository.php`
- Create: `backend/app/Modules/Alerting/Infrastructure/Repositories/InMemoryAlertRepository.php`
- Modify: `backend/app/Providers/AppServiceProvider.php`
- Test: `backend/tests/Unit/Modules/Alerting/AlertingRepositoryTest.php`

- [ ] **Step 1: Write repository integration test `AlertingRepositoryTest.php`**
Test saving, retrieving by ID, retrieving active by fingerprint, filtering by workspace, and deleting rules using both `InMemory` and `Eloquent` repositories.

- [ ] **Step 2: Run test to verify it fails**
Run: `composer --working-dir=backend test -- --filter=AlertingRepositoryTest`
Expected: FAIL

- [ ] **Step 3: Implement Eloquent Models, Eloquent Repositories, and InMemory Repositories**
Implement mapping between Eloquent models and pure Domain Aggregates. Register repository bindings in `AppServiceProvider`.

- [ ] **Step 4: Run test to verify it passes**
Run: `composer --working-dir=backend test -- --filter=AlertingRepositoryTest`
Expected: PASS

- [ ] **Step 5: Commit**
```bash
git add backend/app/Modules/Alerting/Infrastructure/** backend/app/Providers/AppServiceProvider.php backend/tests/Unit/Modules/Alerting/AlertingRepositoryTest.php
git commit -m "feat(alerting): implement Eloquent and InMemory persistence repositories"
```

---

### Task 6: [GPT-5.6] Deterministic Evaluation Engine & Deduplication

**Role:** GPT-5.6 (Интегратор / Evaluation Engine)
**Files:**
- Create: `backend/app/Modules/Alerting/Application/Contracts/InventoryAlertSourceInterface.php`
- Create: `backend/app/Modules/Alerting/Application/Dtos/InventorySnapshotCandidateDto.php`
- Create: `backend/app/Modules/Alerting/Infrastructure/Adapters/PostgresInventoryAlertSource.php`
- Create: `backend/app/Modules/Alerting/Infrastructure/Adapters/InMemoryInventoryAlertSource.php`
- Create: `backend/app/Modules/Alerting/Application/Commands/EvaluateAlertRulesCommand.php`
- Create: `backend/app/Modules/Alerting/Application/Commands/EvaluateAlertRulesHandler.php`
- Create: `backend/app/Modules/Alerting/Application/Dtos/AlertEvaluationResultDto.php`
- Create: `backend/app/Modules/Alerting/Infrastructure/Commands/EvaluateAlertsConsoleCommand.php`
- Create: `backend/app/Modules/Alerting/Infrastructure/Jobs/EvaluateAlertsJob.php`
- Test: `backend/tests/Feature/AlertEvaluationEngineTest.php`

- [ ] **Step 1: Write failing test in `AlertEvaluationEngineTest.php`**
Test scenarios:
1. Evaluating rule with out_of_stock condition on warehouse data creates `Alert` with status `OPEN`.
2. **Crucial Idempotency Verification:** Running evaluation again on the exact same dataset DOES NOT create duplicate alerts (`alerts_created: 0`, existing alert updated with latest evaluation time).
3. Rule disabled (`is_enabled: false`) is skipped.
4. Filter by warehouse scopes evaluation only to matching warehouse.
5. Auto-closing or retriggering logic tested.

- [ ] **Step 2: Run test to verify it fails**
Run: `composer --working-dir=backend test -- --filter=AlertEvaluationEngineTest`
Expected: FAIL

- [ ] **Step 3: Implement InventoryAlertSource adapter and EvaluateAlertRulesHandler**
- `PostgresInventoryAlertSource` queries `fact_inventory_daily` joined with `dim_products` and `dim_warehouses` for the latest snapshot date.
- `EvaluateAlertRulesHandler` applies rule conditions:
  - `OUT_OF_STOCK`: `available <= 0`
  - `CRITICAL_STOCK`: `days_of_stock <= threshold` or `available <= safety_stock`
  - `OVERSTOCK`: `days_of_stock > threshold`
  - `REORDER_POINT`: `available <= reorder_point`
- For matching items: compute `fingerprint`, query `findActiveByFingerprint`. If none exists: persist new `Alert`. If active exists: update timestamp and current value.
- Register `EvaluateAlertsConsoleCommand` (`php artisan alerts:evaluate`).

- [ ] **Step 4: Run test to verify it passes**
Run: `composer --working-dir=backend test -- --filter=AlertEvaluationEngineTest`
Expected: PASS

- [ ] **Step 5: Commit**
```bash
git add backend/app/Modules/Alerting/Application/Contracts/** backend/app/Modules/Alerting/Infrastructure/Adapters/** backend/app/Modules/Alerting/Application/Commands/Evaluate* backend/app/Modules/Alerting/Infrastructure/Commands/** backend/tests/Feature/AlertEvaluationEngineTest.php
git commit -m "feat(alerting): implement deterministic evaluation engine with idempotent deduplication"
```

---

### Task 7: [Claude Opus 4.6] Application CQRS Commands, Queries & Handlers

**Role:** Claude Opus 4.6 (Domain / Business Logic)
**Files:**
- Create: `backend/app/Modules/Alerting/Application/Dtos/AlertRuleDto.php`
- Create: `backend/app/Modules/Alerting/Application/Dtos/AlertDto.php`
- Create: `backend/app/Modules/Alerting/Application/Dtos/AlertSummaryDto.php`
- Create: `backend/app/Modules/Alerting/Application/Dtos/AlertCriteriaDto.php`
- Create: `backend/app/Modules/Alerting/Application/Commands/CreateAlertRuleCommand.php`
- Create: `backend/app/Modules/Alerting/Application/Commands/CreateAlertRuleHandler.php`
- Create: `backend/app/Modules/Alerting/Application/Commands/UpdateAlertRuleCommand.php`
- Create: `backend/app/Modules/Alerting/Application/Commands/UpdateAlertRuleHandler.php`
- Create: `backend/app/Modules/Alerting/Application/Commands/ToggleAlertRuleCommand.php`
- Create: `backend/app/Modules/Alerting/Application/Commands/ToggleAlertRuleHandler.php`
- Create: `backend/app/Modules/Alerting/Application/Commands/DeleteAlertRuleCommand.php`
- Create: `backend/app/Modules/Alerting/Application/Commands/DeleteAlertRuleHandler.php`
- Create: `backend/app/Modules/Alerting/Application/Commands/AcknowledgeAlertCommand.php`
- Create: `backend/app/Modules/Alerting/Application/Commands/AcknowledgeAlertHandler.php`
- Create: `backend/app/Modules/Alerting/Application/Commands/ResolveAlertCommand.php`
- Create: `backend/app/Modules/Alerting/Application/Commands/ResolveAlertHandler.php`
- Create: `backend/app/Modules/Alerting/Application/Queries/GetAlertRulesQuery.php`
- Create: `backend/app/Modules/Alerting/Application/Queries/GetAlertRulesHandler.php`
- Create: `backend/app/Modules/Alerting/Application/Queries/GetAlertRuleByIdQuery.php`
- Create: `backend/app/Modules/Alerting/Application/Queries/GetAlertRuleByIdHandler.php`
- Create: `backend/app/Modules/Alerting/Application/Queries/GetAlertsQuery.php`
- Create: `backend/app/Modules/Alerting/Application/Queries/GetAlertsHandler.php`
- Create: `backend/app/Modules/Alerting/Application/Queries/GetAlertSummaryQuery.php`
- Create: `backend/app/Modules/Alerting/Application/Queries/GetAlertSummaryHandler.php`
- Create: `backend/app/Modules/Alerting/Application/Queries/GetAlertByIdQuery.php`
- Create: `backend/app/Modules/Alerting/Application/Queries/GetAlertByIdHandler.php`
- Test: `backend/tests/Unit/Modules/Alerting/AlertCommandsAndQueriesTest.php`

- [ ] **Step 1: Write unit tests in `AlertCommandsAndQueriesTest.php`**
Test:
- Creating alert rule returns populated `AlertRuleDto`.
- Updating and toggling enable state.
- Acknowledging alert switches status to `acknowledged`.
- Resolving alert switches status to `resolved` with resolution note.
- Summary query returns correct counts by severity and status.

- [ ] **Step 2: Run test to verify it fails**
Run: `composer --working-dir=backend test -- --filter=AlertCommandsAndQueriesTest`
Expected: FAIL

- [ ] **Step 3: Implement CQRS Commands, Queries, Handlers, and DTOs**
Implement cleanly with multi-tenancy workspace isolation.

- [ ] **Step 4: Run test to verify it passes**
Run: `composer --working-dir=backend test -- --filter=AlertCommandsAndQueriesTest`
Expected: PASS

- [ ] **Step 5: Commit**
```bash
git add backend/app/Modules/Alerting/Application/** backend/tests/Unit/Modules/Alerting/AlertCommandsAndQueriesTest.php
git commit -m "feat(alerting): implement CQRS commands, queries, and handlers"
```

---

### Task 8: [GPT-5.6] Presentation REST Controllers & API Routes

**Role:** GPT-5.6 (Интегратор / Presentation)
**Files:**
- Create: `backend/app/Modules/Alerting/Presentation/Controllers/AlertRuleController.php`
- Create: `backend/app/Modules/Alerting/Presentation/Controllers/AlertController.php`
- Create: `backend/app/Modules/Alerting/Presentation/Requests/CreateAlertRuleRequest.php`
- Create: `backend/app/Modules/Alerting/Presentation/Requests/UpdateAlertRuleRequest.php`
- Create: `backend/app/Modules/Alerting/Presentation/Requests/AcknowledgeAlertRequest.php`
- Create: `backend/app/Modules/Alerting/Presentation/Requests/ResolveAlertRequest.php`
- Create: `backend/app/Modules/Alerting/Presentation/Requests/GetAlertsRequest.php`
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/AlertRulesApiTest.php`
- Test: `backend/tests/Feature/AlertsApiTest.php`

- [ ] **Step 1: Write API feature tests `AlertRulesApiTest.php` & `AlertsApiTest.php`**
Test:
- `POST /api/v1/alert-rules` (201 Created)
- `GET /api/v1/alert-rules` (200 OK)
- `POST /api/v1/alert-rules/{id}/toggle` (200 OK)
- `POST /api/v1/alert-rules/evaluate` (200 OK)
- `GET /api/v1/alerts` with filters (200 OK)
- `GET /api/v1/alerts/summary` (200 OK)
- `POST /api/v1/alerts/{id}/acknowledge` (200 OK)
- `POST /api/v1/alerts/{id}/resolve` (200 OK)
- Multi-tenancy isolation: user from workspace 2 trying to read or modify workspace 1 rule/alert gets 403 Forbidden.

- [ ] **Step 2: Run tests to verify they fail**
Run: `composer --working-dir=backend test -- --filter=AlertsApiTest`
Expected: FAIL (routes not found)

- [ ] **Step 3: Implement Controllers, Form Requests, and register API routes**
Mount routes inside `backend/routes/api.php` protected by `AuthenticateUserIdMiddleware`.

- [ ] **Step 4: Run tests to verify they pass**
Run: `composer --working-dir=backend test -- --filter=AlertRulesApiTest`
Run: `composer --working-dir=backend test -- --filter=AlertsApiTest`
Expected: PASS

- [ ] **Step 5: Commit**
```bash
git add backend/app/Modules/Alerting/Presentation/** backend/routes/api.php backend/tests/Feature/*Alert*ApiTest.php
git commit -m "feat(alerting): implement REST controllers, validation requests, and routes"
```

---

### Task 9: [GPT-5.6] Demo Data Seeder for Alert Rules & Initial Alerts

**Role:** GPT-5.6 (Интегратор / Seeders)
**Files:**
- Create: `backend/database/seeders/AlertingSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php`
- Test: `backend/tests/Feature/AlertingSeederTest.php`

- [ ] **Step 1: Write seeder test `AlertingSeederTest.php`**
Verify that running the seeder creates:
- 3 standard rules per workspace (`Дефицит остатков`, `Критический остаток`, `Затоваривание`).
- Evaluates rules or seeds initial active alerts for `ws-1` and `ws-2` so the demo has actionable alerts ready out of the box.

- [ ] **Step 2: Run test to verify it fails**
Run: `composer --working-dir=backend test -- --filter=AlertingSeederTest`
Expected: FAIL

- [ ] **Step 3: Implement `AlertingSeeder.php` and attach to `DatabaseSeeder.php`**
Create realistic automotive inventory rules and evaluate initial alerts.

- [ ] **Step 4: Run test to verify it passes**
Run: `composer --working-dir=backend test -- --filter=AlertingSeederTest`
Expected: PASS

- [ ] **Step 5: Commit**
```bash
git add backend/database/seeders/AlertingSeeder.php backend/database/seeders/DatabaseSeeder.php backend/tests/Feature/AlertingSeederTest.php
git commit -m "feat(alerting): add demo data seeder for alert rules and initial alerts"
```

---

### Task 10: [Claude Opus 4.6 / GPT-5.6] Frontend Typed API Gateway & Client

**Role:** Claude Opus 4.6 / GPT-5.6
**Files:**
- Create: `frontend/src/features/alerts/api/alerts-gateway.ts`
- Create: `frontend/src/features/alerts/model/types.ts`
- Test: `frontend/src/features/alerts/api/alerts-gateway.test.ts`

- [ ] **Step 1: Write gateway test `alerts-gateway.test.ts`**
Mock fetch and test methods:
- `getAlerts`, `getAlertSummary`, `acknowledgeAlert`, `resolveAlert`, `getAlertRules`, `createAlertRule`, `toggleAlertRule`, `evaluateAlerts`.

- [ ] **Step 2: Run test to verify it fails**
Run: `npm --prefix frontend test alerts-gateway.test.ts`
Expected: FAIL

- [ ] **Step 3: Implement `alerts-gateway.ts` using generated OpenAPI types**
Include header `X-Workspace-Id` support and typed responses.

- [ ] **Step 4: Run test to verify it passes**
Run: `npm --prefix frontend test alerts-gateway.test.ts`
Expected: PASS

- [ ] **Step 5: Commit**
```bash
git add frontend/src/features/alerts/api/** frontend/src/features/alerts/model/**
git commit -m "feat(alerting): implement typed frontend alerts gateway"
```

---

### Task 11: [Claude Opus 4.6] Frontend Alerts UI & Analytical Context Navigation

**Role:** Claude Opus 4.6 (Frontend & UI)
**Files:**
- Create: `frontend/src/features/alerts/ui/alert-summary-cards.tsx`
- Create: `frontend/src/features/alerts/ui/alert-table.tsx`
- Create: `frontend/src/features/alerts/ui/alert-rule-list.tsx`
- Create: `frontend/src/features/alerts/ui/alert-rule-dialog.tsx`
- Create: `frontend/src/features/alerts/ui/alert-resolve-dialog.tsx`
- Create: `frontend/src/features/alerts/ui/alerts-view.tsx`
- Create: `frontend/app/(dashboard)/alerts/page.tsx`
- Modify: `frontend/src/shared/ui/layout/sidebar.tsx` (enable `/alerts` nav link, remove `disabled: true, badge: 'Скоро'`)
- Test: `frontend/src/features/alerts/ui/alerts-view.test.tsx`

- [ ] **Step 1: Write UI component tests in `alerts-view.test.tsx`**
Test:
- Summary cards render counts (Critical, Warning, Open).
- Active alerts table displays items with severity badges and action buttons.
- Clicking "Анализ остатков" redirects / links to `/inventory?search={sku}&warehouse_id={warehouse_id}`.
- Clicking "Принять в работу" calls acknowledge API.
- Clicking "Закрыть" opens resolve dialog and submits resolution note.
- Toggling rule enables/disables it.

- [ ] **Step 2: Run test to verify it fails**
Run: `npm --prefix frontend test alerts-view.test.tsx`
Expected: FAIL

- [ ] **Step 3: Implement UI components with shadcn/ui and Tailwind CSS**
- Build clean, accessible components.
- In `alert-table.tsx`, include button `Перейти в остатки` linking to `/inventory` with query params.
- Remove `disabled: true, badge: 'Скоро'` for `Alerts` in `sidebar.tsx`.

- [ ] **Step 4: Run frontend tests, lint, and build**
Run: `npm --prefix frontend test alerts-view.test.tsx`
Run: `npm --prefix frontend run lint`
Run: `npm --prefix frontend run typecheck`
Run: `npm --prefix frontend run build`
Expected: ALL PASS

- [ ] **Step 5: Commit**
```bash
git add frontend/src/features/alerts/ui/** frontend/app/(dashboard)/alerts/** frontend/src/shared/ui/layout/sidebar.tsx
git commit -m "feat(alerting): implement alerts UI, rule management, and inventory deep linking"
```

---

### Task 12: [GPT-5.6] Integration Checkpoint & Verification Script

**Role:** GPT-5.6 (Интегратор / Final Verification)
**Files:**
- Modify: `scripts/verify-integration.sh`
- Modify: `docs/roadmap/12-alerting.md`
- Modify: `docs/roadmap/ROADMAP.md`

- [ ] **Step 1: Add Alerting lifecycle tests to `scripts/verify-integration.sh`**
Add integration steps:
1. `GET /api/v1/alerts/summary` returns summary metrics.
2. `POST /api/v1/alert-rules` creates a test rule.
3. `POST /api/v1/alert-rules/evaluate` triggers evaluation.
4. **Idempotency check:** Second call to `POST /api/v1/alert-rules/evaluate` does NOT duplicate alerts.
5. `POST /api/v1/alerts/{id}/acknowledge` acknowledges alert.
6. `POST /api/v1/alerts/{id}/resolve` resolves alert.
7. Cross-tenant check: user-2 accessing user-1 alert returns 403 Forbidden.
8. Authenticated request to frontend `/alerts` returns 200 OK with alert content.

- [ ] **Step 2: Run full integration suite & make check**
Run: `make check`
Run: `scripts/verify-integration.sh`
Expected: ALL PASS

- [ ] **Step 3: Update `docs/roadmap/12-alerting.md` and `docs/roadmap/ROADMAP.md`**
Record progress, exit criteria verification, and mark Phase 12 as completed `[x]`.

- [ ] **Step 4: Commit**
```bash
git add scripts/verify-integration.sh docs/roadmap/12-alerting.md docs/roadmap/ROADMAP.md
git commit -m "chore(alerting): verify integration checkpoint and mark Phase 12 complete"
```

---

## Plan Self-Review Checklist

1. **Spec Coverage:**
   - Create/enable/disable rule: Covered in Tasks 2, 4, 7, 8, 11.
   - Evaluate conditions: Covered in Tasks 1, 6, 8, 12.
   - Create alert: Covered in Tasks 3, 4, 6.
   - Acknowledge/resolve lifecycle: Covered in Tasks 4, 7, 8, 11, 12.
   - Active list: Covered in Tasks 2, 7, 8, 11.
   - Navigation to analytical context: Covered in Tasks 1, 4, 11 (`/inventory` deep links).
   - Inventory-oriented rules first: Covered in Tasks 1, 6.
   - Deterministic & idempotent execution without uncontrolled duplicates: Covered in Tasks 1, 3, 6, 12.
   - All lifecycle covered by tests: Covered in Tasks 4, 6, 7, 8, 10, 11, 12.

2. **Placeholder Scan:**
   - 0 instances of "TBD", "TODO", "implement later". Every task defines exact file paths, interfaces, and code snippets.

3. **Type Consistency:**
   - `AlertSeverity` (`info`, `warning`, `critical`), `AlertStatus` (`open`, `acknowledged`, `resolved`), `RuleType` (`out_of_stock`, `critical_stock`, `overstock`, `reorder_point`).
   - `dedup_fingerprint` consistently used across migration, domain, evaluation engine, and integration tests.
