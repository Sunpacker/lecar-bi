# Phase 3 — Demo Data Model Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Создать воспроизводимый, детерминированный automotive e-commerce датасет (Star Schema: dimensions, sales facts, inventory snapshots, supplier delivery facts) с выраженными сезонными кривыми, трендами, ситуациями stockouts/overstock и строгой изоляцией по workspace_id для разработки аналитики без внешнего ingestion.

**Architecture:** На уровне хранения создаются таблицы Star Schema в PostgreSQL (измерения `dim_*` и факты `fact_*`). Генерация данных строится на детерминированном генераторе псевдослучайных последовательностей с фиксированным seed, оперирующем каталогом автотоваров (шины, масла, тормозные системы, фильтры, аккумуляторы). Запуск инкапсулирован в `DemoDataSeeder`, встроен в общий `DatabaseSeeder` и доступен через консольную команду `php artisan demo:seed`.

**Tech Stack:** PHP 8.3, Laravel 13, PostgreSQL 16, Eloquent/Query Builder (batch insert chunks), PHPUnit 11, Docker Compose.

**Spec:** [docs/roadmap/03-demo-data-model.md](../../roadmap/03-demo-data-model.md) и [docs/architecture/06-data-and-analytics.md](../../architecture/06-data-and-analytics.md).

## Global Constraints

- Все tenant-таблицы фактов и измерений обязаны содержать `workspace_id` со внешним ключом `workspaces(id)` и `cascadeOnDelete()`.
- Изоляция данных: данные `ws-1` ("AutoParts Retail") и `ws-2` ("Lecar Wholesale") строго разделены; кросс-воркспейс запросы недопустимы.
- Детерминизм: одинаковый seed (по умолчанию `42`) обязан генерировать идентичные количества строк, суммы и взаимосвязи при повторном запуске на чистой базе.
- Производительность сидинга: вставка выполняется пакетами (chunks по 500-1000 записей), без построчных INSERT.
- Соответствие Domain-правилам: Domain-слой не должен зависеть от инфраструктуры, тесты `ArchitectureTest` должны оставаться зелёными.
- Все существующие проверки `make check` и `scripts/verify-integration.sh` должны выполняться без ошибок.

---

### Task 1: Star Schema Migrations for Dimensions and Facts

**Files:**
- Create: `backend/database/migrations/2026_09_22_000010_create_analytics_dimensions_tables.php`
- Create: `backend/database/migrations/2026_09_22_000011_create_analytics_facts_tables.php`
- Test: `backend/tests/Unit/DemoData/AnalyticsSchemaMigrationStructureTest.php`

**Interfaces:**
- Consumes: существующая таблица `workspaces(id)`.
- Produces таблицы:
  - `dim_dates` (календарные атрибуты: date, year, quarter, month, month_name, week, day, day_of_week, day_name, is_weekend, season);
  - `dim_categories`, `dim_brands`, `dim_regions`, `dim_warehouses`, `dim_sales_channels`, `dim_suppliers`, `dim_products` (с внешними ключами к `workspaces`);
  - `fact_orders`, `fact_order_items`, `fact_inventory_daily`, `fact_supplier_deliveries`.

- [ ] **Step 1: Write the failing unit test for migration structure and column contracts**

```php
<?php

namespace Tests\Unit\DemoData;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AnalyticsSchemaMigrationStructureTest extends TestCase
{
    #[Test]
    public function migration_files_exist_and_contain_required_tables_and_indexes(): void
    {
        $baseDir = dirname(__DIR__, 3).'/database/migrations';
        $dimensionsMigration = $baseDir.'/2026_09_22_000010_create_analytics_dimensions_tables.php';
        $factsMigration = $baseDir.'/2026_09_22_000011_create_analytics_facts_tables.php';

        self::assertFileExists($dimensionsMigration);
        self::assertFileExists($factsMigration);

        $dimensionsContent = (string) file_get_contents($dimensionsMigration);
        $factsContent = (string) file_get_contents($factsMigration);

        // Required dimension tables
        self::assertStringContainsString("'dim_dates'", $dimensionsContent);
        self::assertStringContainsString("'dim_categories'", $dimensionsContent);
        self::assertStringContainsString("'dim_brands'", $dimensionsContent);
        self::assertStringContainsString("'dim_regions'", $dimensionsContent);
        self::assertStringContainsString("'dim_warehouses'", $dimensionsContent);
        self::assertStringContainsString("'dim_sales_channels'", $dimensionsContent);
        self::assertStringContainsString("'dim_suppliers'", $dimensionsContent);
        self::assertStringContainsString("'dim_products'", $dimensionsContent);

        // Required fact tables
        self::assertStringContainsString("'fact_orders'", $factsContent);
        self::assertStringContainsString("'fact_order_items'", $factsContent);
        self::assertStringContainsString("'fact_inventory_daily'", $factsContent);
        self::assertStringContainsString("'fact_supplier_deliveries'", $factsContent);

        // Multi-tenant workspace reference
        self::assertStringContainsString("references('id')->on('workspaces')->cascadeOnDelete()", $dimensionsContent);
        self::assertStringContainsString("references('id')->on('workspaces')->cascadeOnDelete()", $factsContent);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter AnalyticsSchemaMigrationStructureTest`
Expected: FAIL with `Failed asserting that file ".../2026_09_22_000010_create_analytics_dimensions_tables.php" exists.`

- [ ] **Step 3: Write migrations for dimensions and facts**

Create `backend/database/migrations/2026_09_22_000010_create_analytics_dimensions_tables.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dim_dates', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->smallInteger('year');
            $table->tinyInteger('quarter');
            $table->tinyInteger('month');
            $table->string('month_name', 20);
            $table->tinyInteger('week');
            $table->tinyInteger('day');
            $table->tinyInteger('day_of_week');
            $table->string('day_name', 20);
            $table->boolean('is_weekend');
            $table->string('season', 16);
        });

        Schema::create('dim_categories', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->string('name', 128);
            $table->string('slug', 128);
            $table->string('code', 64);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->index(['workspace_id', 'slug']);
        });

        Schema::create('dim_brands', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->string('name', 128);
            $table->string('country', 64);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->index(['workspace_id', 'name']);
        });

        Schema::create('dim_regions', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->string('name', 128);
            $table->string('code', 32);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->index(['workspace_id', 'code']);
        });

        Schema::create('dim_warehouses', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->string('region_id', 64);
            $table->string('name', 128);
            $table->string('code', 32);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('region_id')->references('id')->on('dim_regions')->cascadeOnDelete();
            $table->index(['workspace_id', 'code']);
        });

        Schema::create('dim_sales_channels', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->string('name', 128);
            $table->string('code', 32);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->index(['workspace_id', 'code']);
        });

        Schema::create('dim_suppliers', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->string('name', 128);
            $table->integer('lead_time_days');
            $table->decimal('reliability_score', 3, 2);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->index(['workspace_id', 'name']);
        });

        Schema::create('dim_products', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->string('category_id', 64);
            $table->string('brand_id', 64);
            $table->string('sku', 64);
            $table->string('name', 255);
            $table->decimal('cost_price', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('dim_categories')->cascadeOnDelete();
            $table->foreign('brand_id')->references('id')->on('dim_brands')->cascadeOnDelete();
            $table->index(['workspace_id', 'sku']);
            $table->index(['workspace_id', 'category_id']);
            $table->index(['workspace_id', 'brand_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dim_products');
        Schema::dropIfExists('dim_suppliers');
        Schema::dropIfExists('dim_sales_channels');
        Schema::dropIfExists('dim_warehouses');
        Schema::dropIfExists('dim_regions');
        Schema::dropIfExists('dim_brands');
        Schema::dropIfExists('dim_categories');
        Schema::dropIfExists('dim_dates');
    }
};
```

Create `backend/database/migrations/2026_09_22_000011_create_analytics_facts_tables.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fact_orders', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->string('order_number', 64);
            $table->string('channel_id', 64);
            $table->string('region_id', 64);
            $table->string('status', 32);
            $table->dateTimeTz('ordered_at');
            $table->date('order_date');
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3)->default('RUB');
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('channel_id')->references('id')->on('dim_sales_channels')->cascadeOnDelete();
            $table->foreign('region_id')->references('id')->on('dim_regions')->cascadeOnDelete();
            $table->index(['workspace_id', 'order_date']);
            $table->index(['workspace_id', 'channel_id']);
            $table->index(['workspace_id', 'region_id']);
            $table->index(['workspace_id', 'status']);
        });

        Schema::create('fact_order_items', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->string('order_id', 64);
            $table->string('product_id', 64);
            $table->string('warehouse_id', 64);
            $table->string('category_id', 64);
            $table->string('brand_id', 64);
            $table->string('region_id', 64);
            $table->string('channel_id', 64);
            $table->date('order_date');
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total_price', 12, 2);
            $table->decimal('total_cost', 12, 2);
            $table->decimal('gross_profit', 12, 2);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('fact_orders')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('dim_products')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('dim_warehouses')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('dim_categories')->cascadeOnDelete();
            $table->foreign('brand_id')->references('id')->on('dim_brands')->cascadeOnDelete();
            $table->foreign('region_id')->references('id')->on('dim_regions')->cascadeOnDelete();
            $table->foreign('channel_id')->references('id')->on('dim_sales_channels')->cascadeOnDelete();
            $table->index(['workspace_id', 'order_date']);
            $table->index(['workspace_id', 'product_id']);
            $table->index(['workspace_id', 'category_id']);
            $table->index(['workspace_id', 'warehouse_id']);
        });

        Schema::create('fact_inventory_daily', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->date('snapshot_date');
            $table->string('product_id', 64);
            $table->string('warehouse_id', 64);
            $table->integer('quantity_on_hand');
            $table->integer('quantity_reserved');
            $table->integer('quantity_available');
            $table->integer('safety_stock');
            $table->integer('reorder_point');
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('inventory_value', 14, 2);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('dim_products')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('dim_warehouses')->cascadeOnDelete();
            $table->index(['workspace_id', 'snapshot_date']);
            $table->unique(['workspace_id', 'snapshot_date', 'product_id', 'warehouse_id'], 'uq_inv_ws_date_prod_wh');
        });

        Schema::create('fact_supplier_deliveries', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('workspace_id');
            $table->string('supplier_id', 64);
            $table->string('product_id', 64);
            $table->string('warehouse_id', 64);
            $table->date('order_date');
            $table->date('expected_delivery_date');
            $table->date('actual_delivery_date')->nullable();
            $table->integer('ordered_quantity');
            $table->integer('received_quantity');
            $table->decimal('unit_purchase_cost', 12, 2);
            $table->decimal('total_purchase_cost', 12, 2);
            $table->string('delivery_status', 32);
            $table->integer('lead_time_days');
            $table->integer('delay_days')->default(0);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('dim_suppliers')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('dim_products')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('dim_warehouses')->cascadeOnDelete();
            $table->index(['workspace_id', 'order_date']);
            $table->index(['workspace_id', 'supplier_id']);
            $table->index(['workspace_id', 'delivery_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fact_supplier_deliveries');
        Schema::dropIfExists('fact_inventory_daily');
        Schema::dropIfExists('fact_order_items');
        Schema::dropIfExists('fact_orders');
    }
};
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter AnalyticsSchemaMigrationStructureTest`
Expected: PASS (1 test, 14 assertions).

- [ ] **Step 5: Commit**

```bash
git add backend/database/migrations/2026_09_22_000010_create_analytics_dimensions_tables.php \
        backend/database/migrations/2026_09_22_000011_create_analytics_facts_tables.php \
        backend/tests/Unit/DemoData/AnalyticsSchemaMigrationStructureTest.php
git commit -m "feat(analytics): add star schema migrations for dimensions and facts"
```

---

### Task 2: Automotive E-Commerce Seed Catalog & Domain Fixtures

**Files:**
- Create: `backend/database/seeders/Demo/DemoDataCatalog.php`
- Test: `backend/tests/Unit/DemoData/DemoDataCatalogTest.php`

**Interfaces:**
- Produces static definitions for:
  - `DemoDataCatalog::categories(): list<array{id: string, name: string, slug: string, code: string}>`
  - `DemoDataCatalog::brands(): list<array{id: string, name: string, country: string}>`
  - `DemoDataCatalog::regions(): list<array{id: string, name: string, code: string}>`
  - `DemoDataCatalog::warehouses(): list<array{id: string, region_id: string, name: string, code: string}>`
  - `DemoDataCatalog::salesChannels(): list<array{id: string, name: string, code: string}>`
  - `DemoDataCatalog::suppliers(): list<array{id: string, name: string, lead_time_days: int, reliability_score: float}>`
  - `DemoDataCatalog::products(): list<array{id: string, category_id: string, brand_id: string, sku: string, name: string, cost_price: float, unit_price: float, seasonal_type: string, abc_class: string}>`

- [ ] **Step 1: Write the failing unit test for automotive catalog validity**

```php
<?php

namespace Tests\Unit\DemoData;

use Database\Seeders\Demo\DemoDataCatalog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DemoDataCatalogTest extends TestCase
{
    #[Test]
    public function catalog_contains_valid_automotive_dimensions_and_products(): void
    {
        $categories = DemoDataCatalog::categories();
        $brands = DemoDataCatalog::brands();
        $regions = DemoDataCatalog::regions();
        $warehouses = DemoDataCatalog::warehouses();
        $channels = DemoDataCatalog::salesChannels();
        $suppliers = DemoDataCatalog::suppliers();
        $products = DemoDataCatalog::products();

        self::assertGreaterThanOrEqual(6, count($categories));
        self::assertGreaterThanOrEqual(8, count($brands));
        self::assertGreaterThanOrEqual(4, count($regions));
        self::assertGreaterThanOrEqual(4, count($warehouses));
        self::assertGreaterThanOrEqual(4, count($channels));
        self::assertGreaterThanOrEqual(4, count($suppliers));
        self::assertGreaterThanOrEqual(25, count($products));

        $categoryIds = array_column($categories, 'id');
        $brandIds = array_column($brands, 'id');

        $hasWinterTires = false;
        $hasSummerTires = false;
        $hasFluids = false;

        foreach ($products as $product) {
            self::assertContains($product['category_id'], $categoryIds);
            self::assertContains($product['brand_id'], $brandIds);
            self::assertGreaterThan(0, $product['cost_price']);
            self::assertGreaterThan($product['cost_price'], $product['unit_price'], "Product {$product['sku']} selling price must exceed cost price.");

            if ($product['seasonal_type'] === 'winter_seasonal') {
                $hasWinterTires = true;
            }
            if ($product['seasonal_type'] === 'summer_seasonal') {
                $hasSummerTires = true;
            }
            if ($product['category_id'] === 'cat-oils-fluids') {
                $hasFluids = true;
            }
        }

        self::assertTrue($hasWinterTires, 'Catalog must include winter seasonal items.');
        self::assertTrue($hasSummerTires, 'Catalog must include summer seasonal items.');
        self::assertTrue($hasFluids, 'Catalog must include automotive fluids.');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter DemoDataCatalogTest`
Expected: FAIL with `Class "Database\Seeders\Demo\DemoDataCatalog" not found`.

- [ ] **Step 3: Implement DemoDataCatalog**

Create `backend/database/seeders/Demo/DemoDataCatalog.php`:
```php
<?php

namespace Database\Seeders\Demo;

final class DemoDataCatalog
{
    /** @return list<array{id: string, name: string, slug: string, code: string}> */
    public static function categories(): array
    {
        return [
            ['id' => 'cat-tires-wheels', 'name' => 'Шины и диски', 'slug' => 'tires-and-wheels', 'code' => 'TIRES'],
            ['id' => 'cat-brakes', 'name' => 'Тормозная система', 'slug' => 'braking-systems', 'code' => 'BRAKES'],
            ['id' => 'cat-oils-fluids', 'name' => 'Масла и автохимия', 'slug' => 'oils-and-fluids', 'code' => 'FLUIDS'],
            ['id' => 'cat-filters', 'name' => 'Фильтры', 'slug' => 'filters', 'code' => 'FILTERS'],
            ['id' => 'cat-suspension', 'name' => 'Подвеска и рулевое управление', 'slug' => 'suspension-and-steering', 'code' => 'SUSP'],
            ['id' => 'cat-electrical', 'name' => 'Электрика и освещение', 'slug' => 'electrical-and-lighting', 'code' => 'ELEC'],
            ['id' => 'cat-cooling', 'name' => 'Охлаждение и климат', 'slug' => 'cooling-and-climate', 'code' => 'COOL'],
            ['id' => 'cat-transmission', 'name' => 'Трансмиссия и сцепление', 'slug' => 'transmission-and-clutch', 'code' => 'TRANS'],
        ];
    }

    /** @return list<array{id: string, name: string, country: string}> */
    public static function brands(): array
    {
        return [
            ['id' => 'br-michelin', 'name' => 'Michelin', 'country' => 'Франция'],
            ['id' => 'br-continental', 'name' => 'Continental', 'country' => 'Германия'],
            ['id' => 'br-brembo', 'name' => 'Brembo', 'country' => 'Италия'],
            ['id' => 'br-bosch', 'name' => 'Bosch', 'country' => 'Германия'],
            ['id' => 'br-castrol', 'name' => 'Castrol', 'country' => 'Великобритания'],
            ['id' => 'br-lukoil', 'name' => 'Lukoil', 'country' => 'Россия'],
            ['id' => 'br-mann', 'name' => 'Mann-Filter', 'country' => 'Германия'],
            ['id' => 'br-febi', 'name' => 'Febi Bilstein', 'country' => 'Германия'],
            ['id' => 'br-valeo', 'name' => 'Valeo', 'country' => 'Франция'],
            ['id' => 'br-osram', 'name' => 'Osram', 'country' => 'Германия'],
        ];
    }

    /** @return list<array{id: string, name: string, code: string}> */
    public static function regions(): array
    {
        return [
            ['id' => 'reg-cbr', 'name' => 'Центральный регион (Москва и МО)', 'code' => 'MSK'],
            ['id' => 'reg-nw', 'name' => 'Северо-Западный регион (СПб)', 'code' => 'SPB'],
            ['id' => 'reg-vlg', 'name' => 'Приволжский регион (Самара)', 'code' => 'SAM'],
            ['id' => 'reg-url', 'name' => 'Уральский регион (Екатеринбург)', 'code' => 'EKB'],
            ['id' => 'reg-sib', 'name' => 'Сибирский регион (Новосибирск)', 'code' => 'NSK'],
            ['id' => 'reg-sth', 'name' => 'Южный регион (Краснодар)', 'code' => 'KRD'],
        ];
    }

    /** @return list<array{id: string, region_id: string, name: string, code: string}> */
    public static function warehouses(): array
    {
        return [
            ['id' => 'wh-msk-central', 'region_id' => 'reg-cbr', 'name' => 'Центральный распределительный центр Москва', 'code' => 'WH-MSK-01'],
            ['id' => 'wh-spb-north', 'region_id' => 'reg-nw', 'name' => 'Логистический хаб Санкт-Петербург', 'code' => 'WH-SPB-01'],
            ['id' => 'wh-sam-volga', 'region_id' => 'reg-vlg', 'name' => 'Региональный склад Самара', 'code' => 'WH-SAM-01'],
            ['id' => 'wh-ekb-ural', 'region_id' => 'reg-url', 'name' => 'Уральский распределительный центр', 'code' => 'WH-EKB-01'],
        ];
    }

    /** @return list<array{id: string, name: string, code: string}> */
    public static function salesChannels(): array
    {
        return [
            ['id' => 'ch-b2c-web', 'name' => 'Интернет-магазин B2C', 'code' => 'B2C_WEB'],
            ['id' => 'ch-b2b-portal', 'name' => 'Оптовый портал B2B', 'code' => 'B2B_PORTAL'],
            ['id' => 'ch-mp-ozon', 'name' => 'Маркетплейс Ozon', 'code' => 'MP_OZON'],
            ['id' => 'ch-mp-wb', 'name' => 'Маркетплейс Wildberries', 'code' => 'MP_WB'],
            ['id' => 'ch-retail-store', 'name' => 'Сеть розничных автомагазинов', 'code' => 'RETAIL_STORE'],
        ];
    }

    /** @return list<array{id: string, name: string, lead_time_days: int, reliability_score: float}> */
    public static function suppliers(): array
    {
        return [
            ['id' => 'sup-eurotech', 'name' => 'EuroTech Components Ltd', 'lead_time_days' => 6, 'reliability_score' => 0.96],
            ['id' => 'sup-vostok', 'name' => 'Восток Авто Дистрибьюшн', 'lead_time_days' => 12, 'reliability_score' => 0.88],
            ['id' => 'sup-rusauto', 'name' => 'РусАвто Импорт', 'lead_time_days' => 8, 'reliability_score' => 0.92],
            ['id' => 'sup-global', 'name' => 'Global Auto Supply Direct', 'lead_time_days' => 16, 'reliability_score' => 0.79],
        ];
    }

    /** @return list<array{id: string, category_id: string, brand_id: string, sku: string, name: string, cost_price: float, unit_price: float, seasonal_type: string, abc_class: string}> */
    public static function products(): array
    {
        return [
            // Tires & Wheels
            ['id' => 'prod-conti-wint-16', 'category_id' => 'cat-tires-wheels', 'brand_id' => 'br-continental', 'sku' => 'SKU-TIRE-W16-01', 'name' => 'Шина зимняя шипованная Continental IceContact 3 205/55 R16', 'cost_price' => 5800.00, 'unit_price' => 8900.00, 'seasonal_type' => 'winter_seasonal', 'abc_class' => 'A'],
            ['id' => 'prod-conti-wint-17', 'category_id' => 'cat-tires-wheels', 'brand_id' => 'br-continental', 'sku' => 'SKU-TIRE-W17-02', 'name' => 'Шина зимняя нешипованная Continental VikingContact 7 225/50 R17', 'cost_price' => 7900.00, 'unit_price' => 12400.00, 'seasonal_type' => 'winter_seasonal', 'abc_class' => 'A'],
            ['id' => 'prod-mich-summ-16', 'category_id' => 'cat-tires-wheels', 'brand_id' => 'br-michelin', 'sku' => 'SKU-TIRE-S16-03', 'name' => 'Шина летняя Michelin Primacy 4 205/55 R16', 'cost_price' => 5400.00, 'unit_price' => 8400.00, 'seasonal_type' => 'summer_seasonal', 'abc_class' => 'A'],
            ['id' => 'prod-mich-summ-18', 'category_id' => 'cat-tires-wheels', 'brand_id' => 'br-michelin', 'sku' => 'SKU-TIRE-S18-04', 'name' => 'Шина летняя Michelin Pilot Sport 4 235/45 R18', 'cost_price' => 10200.00, 'unit_price' => 15900.00, 'seasonal_type' => 'summer_seasonal', 'abc_class' => 'B'],

            // Brakes
            ['id' => 'prod-brembo-pad-fr', 'category_id' => 'cat-brakes', 'brand_id' => 'br-brembo', 'sku' => 'SKU-BRK-PAD-01', 'name' => 'Колодки тормозные передние Brembo P85020', 'cost_price' => 1950.00, 'unit_price' => 3200.00, 'seasonal_type' => 'regular', 'abc_class' => 'A'],
            ['id' => 'prod-brembo-disc-fr', 'category_id' => 'cat-brakes', 'brand_id' => 'br-brembo', 'sku' => 'SKU-BRK-DSC-02', 'name' => 'Диск тормозной вентилируемый Brembo 09.9145.11', 'cost_price' => 3100.00, 'unit_price' => 5100.00, 'seasonal_type' => 'regular', 'abc_class' => 'A'],
            ['id' => 'prod-bosch-pad-rr', 'category_id' => 'cat-brakes', 'brand_id' => 'br-bosch', 'sku' => 'SKU-BRK-BOS-03', 'name' => 'Колодки тормозные задние Bosch 0 986 494 004', 'cost_price' => 1450.00, 'unit_price' => 2400.00, 'seasonal_type' => 'regular', 'abc_class' => 'B'],

            // Oils & Fluids
            ['id' => 'prod-cast-edge-5w30', 'category_id' => 'cat-oils-fluids', 'brand_id' => 'br-castrol', 'sku' => 'SKU-OIL-CST-01', 'name' => 'Моторное масло Castrol EDGE 5W-30 LL 4л', 'cost_price' => 3200.00, 'unit_price' => 4950.00, 'seasonal_type' => 'regular', 'abc_class' => 'A'],
            ['id' => 'prod-luk-arm-5w40', 'category_id' => 'cat-oils-fluids', 'brand_id' => 'br-lukoil', 'sku' => 'SKU-OIL-LUK-02', 'name' => 'Моторное масло Lukoil Genesis Armortech 5W-40 4л', 'cost_price' => 1800.00, 'unit_price' => 2950.00, 'seasonal_type' => 'regular', 'abc_class' => 'A'],
            ['id' => 'prod-luk-antifreeze', 'category_id' => 'cat-oils-fluids', 'brand_id' => 'br-lukoil', 'sku' => 'SKU-FLD-ANT-03', 'name' => 'Антифриз Lukoil Red G12 5кг', 'cost_price' => 650.00, 'unit_price' => 1150.00, 'seasonal_type' => 'winter_seasonal', 'abc_class' => 'B'],
            ['id' => 'prod-cast-brake-dot4', 'category_id' => 'cat-oils-fluids', 'brand_id' => 'br-castrol', 'sku' => 'SKU-FLD-DOT-04', 'name' => 'Тормозная жидкость Castrol Brake Fluid DOT4 1л', 'cost_price' => 480.00, 'unit_price' => 850.00, 'seasonal_type' => 'regular', 'abc_class' => 'C'],

            // Filters
            ['id' => 'prod-mann-oil-w712', 'category_id' => 'cat-filters', 'brand_id' => 'br-mann', 'sku' => 'SKU-FLT-OIL-01', 'name' => 'Фильтр масляный Mann-Filter W 712/95', 'cost_price' => 420.00, 'unit_price' => 780.00, 'seasonal_type' => 'regular', 'abc_class' => 'A'],
            ['id' => 'prod-mann-air-c250', 'category_id' => 'cat-filters', 'brand_id' => 'br-mann', 'sku' => 'SKU-FLT-AIR-02', 'name' => 'Фильтр воздушный Mann-Filter C 25 004', 'cost_price' => 680.00, 'unit_price' => 1250.00, 'seasonal_type' => 'summer_seasonal', 'abc_class' => 'B'],
            ['id' => 'prod-mann-cab-cu29', 'category_id' => 'cat-filters', 'brand_id' => 'br-mann', 'sku' => 'SKU-FLT-CAB-03', 'name' => 'Фильтр салонный угольный Mann-Filter CUK 2939', 'cost_price' => 790.00, 'unit_price' => 1450.00, 'seasonal_type' => 'summer_seasonal', 'abc_class' => 'B'],
            ['id' => 'prod-bosch-fuel-01', 'category_id' => 'cat-filters', 'brand_id' => 'br-bosch', 'sku' => 'SKU-FLT-FUL-04', 'name' => 'Фильтр топливный Bosch 0 450 906 457', 'cost_price' => 1100.00, 'unit_price' => 1950.00, 'seasonal_type' => 'regular', 'abc_class' => 'C'],

            // Suspension & Steering
            ['id' => 'prod-febi-lever-fr', 'category_id' => 'cat-suspension', 'brand_id' => 'br-febi', 'sku' => 'SKU-SUS-LVR-01', 'name' => 'Рычаг передней подвески нижний левый Febi Bilstein 39274', 'cost_price' => 3800.00, 'unit_price' => 6200.00, 'seasonal_type' => 'regular', 'abc_class' => 'B'],
            ['id' => 'prod-febi-ball-02', 'category_id' => 'cat-suspension', 'brand_id' => 'br-febi', 'sku' => 'SKU-SUS-BAL-02', 'name' => 'Опора шаровая передняя Febi Bilstein 27421', 'cost_price' => 950.00, 'unit_price' => 1650.00, 'seasonal_type' => 'regular', 'abc_class' => 'C'],
            ['id' => 'prod-febi-rod-03', 'category_id' => 'cat-suspension', 'brand_id' => 'br-febi', 'sku' => 'SKU-SUS-ROD-03', 'name' => 'Стойка стабилизатора передняя Febi Bilstein 21013', 'cost_price' => 750.00, 'unit_price' => 1350.00, 'seasonal_type' => 'regular', 'abc_class' => 'B'],

            // Electrical & Lighting
            ['id' => 'prod-bosch-batt-s4', 'category_id' => 'cat-electrical', 'brand_id' => 'br-bosch', 'sku' => 'SKU-ELC-BAT-01', 'name' => 'Аккумулятор Bosch S4 008 Silver 74Ah 680A', 'cost_price' => 6200.00, 'unit_price' => 9800.00, 'seasonal_type' => 'winter_seasonal', 'abc_class' => 'A'],
            ['id' => 'prod-osram-lamp-h7', 'category_id' => 'cat-electrical', 'brand_id' => 'br-osram', 'sku' => 'SKU-ELC-LMP-02', 'name' => 'Автолампа галогенная Osram Night Breaker Laser H7 (комплект 2 шт.)', 'cost_price' => 1350.00, 'unit_price' => 2450.00, 'seasonal_type' => 'winter_seasonal', 'abc_class' => 'B'],
            ['id' => 'prod-bosch-spark-03', 'category_id' => 'cat-electrical', 'brand_id' => 'br-bosch', 'sku' => 'SKU-ELC-SPK-03', 'name' => 'Свеча зажигания иридиевая Bosch Double Iridium 0 242 240 653', 'cost_price' => 620.00, 'unit_price' => 1100.00, 'seasonal_type' => 'regular', 'abc_class' => 'B'],

            // Cooling & Climate
            ['id' => 'prod-valeo-rad-01', 'category_id' => 'cat-cooling', 'brand_id' => 'br-valeo', 'sku' => 'SKU-COL-RAD-01', 'name' => 'Радиатор охлаждения двигателя Valeo 735284', 'cost_price' => 6100.00, 'unit_price' => 9900.00, 'seasonal_type' => 'summer_seasonal', 'abc_class' => 'C'],
            ['id' => 'prod-valeo-pump-02', 'category_id' => 'cat-cooling', 'brand_id' => 'br-valeo', 'sku' => 'SKU-COL-PMP-02', 'name' => 'Насос водяной (помпа) Valeo 506689', 'cost_price' => 2400.00, 'unit_price' => 3950.00, 'seasonal_type' => 'regular', 'abc_class' => 'C'],

            // Transmission & Clutch
            ['id' => 'prod-valeo-clutch-01', 'category_id' => 'cat-transmission', 'brand_id' => 'br-valeo', 'sku' => 'SKU-TRN-CLT-01', 'name' => 'Комплект сцепления Valeo 826315', 'cost_price' => 8400.00, 'unit_price' => 13700.00, 'seasonal_type' => 'regular', 'abc_class' => 'B'],
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter DemoDataCatalogTest`
Expected: PASS (1 test, 11 assertions).

- [ ] **Step 5: Commit**

```bash
git add backend/database/seeders/Demo/DemoDataCatalog.php \
        backend/tests/Unit/DemoData/DemoDataCatalogTest.php
git commit -m "feat(analytics): define automotive e-commerce demo catalog and fixtures"
```

---

### Task 3: Deterministic Demo Dataset Generator (Seasonality, Trends, Stockouts & Overstock)

**Files:**
- Create: `backend/database/seeders/Demo/DemoDatasetGenerator.php`
- Test: `backend/tests/Unit/DemoData/DemoDatasetGeneratorTest.php`

**Interfaces:**
- Consumes: `DemoDataCatalog`, workspace ID (string), integer seed.
- Produces:
  - `generateDates(string $startDate, string $endDate): list<array<string, mixed>>`
  - `generateWorkspaceDimensions(string $workspaceId): array<string, list<array<string, mixed>>>`
  - `generateOrdersAndItems(string $workspaceId, string $startDate, string $endDate): array{orders: list<array<string, mixed>>, items: list<array<string, mixed>>}`
  - `generateInventoryDaily(string $workspaceId, string $startDate, string $endDate, array $items): list<array<string, mixed>>`
  - `generateSupplierDeliveries(string $workspaceId, string $startDate, string $endDate): list<array<string, mixed>>`

- [ ] **Step 1: Write the failing unit test for dataset generator determinism and business curves**

```php
<?php

namespace Tests\Unit\DemoData;

use Database\Seeders\Demo\DemoDatasetGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DemoDatasetGeneratorTest extends TestCase
{
    #[Test]
    public function generator_is_deterministic_and_exhibits_seasonality_and_inventory_extremes(): void
    {
        $gen1 = new DemoDatasetGenerator(seed: 42);
        $gen2 = new DemoDatasetGenerator(seed: 42);

        $dates1 = $gen1->generateDates('2025-01-01', '2025-12-31');
        $dates2 = $gen2->generateDates('2025-01-01', '2025-12-31');

        self::assertSame(365, count($dates1));
        self::assertSame($dates1, $dates2, 'Same seed must generate identical date dimension.');

        $sales1 = $gen1->generateOrdersAndItems('ws-1', '2025-01-01', '2025-12-31');
        $sales2 = $gen2->generateOrdersAndItems('ws-1', '2025-01-01', '2025-12-31');

        self::assertSame(count($sales1['orders']), count($sales2['orders']));
        self::assertSame(count($sales1['items']), count($sales2['items']));
        self::assertSame($sales1['orders'][0]['total_amount'], $sales2['orders'][0]['total_amount']);

        // Test Seasonality: Winter tires sales in Q4 (Oct-Dec) must significantly exceed Q2 (Apr-Jun)
        $winterQ4Quantity = 0;
        $winterQ2Quantity = 0;

        foreach ($sales1['items'] as $item) {
            self::assertSame('ws-1', $item['workspace_id']);
            self::assertEqualsWithDelta(
                (float) $item['total_price'] - (float) $item['total_cost'],
                (float) $item['gross_profit'],
                0.01,
                'Gross profit must equal total price minus total cost.'
            );

            $month = (int) substr((string) $item['order_date'], 5, 2);
            if ($item['product_id'] === 'prod-conti-wint-16') {
                if ($month >= 10 && $month <= 12) {
                    $winterQ4Quantity += (int) $item['quantity'];
                } elseif ($month >= 4 && $month <= 6) {
                    $winterQ2Quantity += (int) $item['quantity'];
                }
            }
        }

        self::assertGreaterThan(
            $winterQ2Quantity * 3,
            $winterQ4Quantity,
            'Winter tire volume in Q4 must be at least 3x greater than Q2.'
        );

        // Test Inventory Snapshots: stockouts and overstock must occur
        $inventory = $gen1->generateInventoryDaily('ws-1', '2025-10-01', '2025-12-31', $sales1['items']);
        $hasStockout = false;
        $hasOverstock = false;

        foreach ($inventory as $snap) {
            self::assertSame('ws-1', $snap['workspace_id']);
            if ($snap['quantity_available'] <= 0) {
                $hasStockout = true;
            }
            if ($snap['quantity_available'] > 4 * $snap['safety_stock']) {
                $hasOverstock = true;
            }
        }

        self::assertTrue($hasStockout, 'Inventory snapshots must contain stockout situations (quantity_available <= 0).');
        self::assertTrue($hasOverstock, 'Inventory snapshots must contain overstock situations.');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter DemoDatasetGeneratorTest`
Expected: FAIL with `Class "Database\Seeders\Demo\DemoDatasetGenerator" not found`.

- [ ] **Step 3: Implement DemoDatasetGenerator**

Create `backend/database/seeders/Demo/DemoDatasetGenerator.php` (см. листинг выше).

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter DemoDatasetGeneratorTest`
Expected: PASS (1 test, 10 assertions).

- [ ] **Step 5: Commit**

```bash
git add backend/database/seeders/Demo/DemoDatasetGenerator.php \
        backend/tests/Unit/DemoData/DemoDatasetGeneratorTest.php
git commit -m "feat(analytics): implement deterministic demo dataset generator with seasonality and inventory dynamics"
```

---

### Task 4: DemoDataSeeder, DatabaseSeeder Integration & Artisan CLI Command

**Files:**
- Create: `backend/database/seeders/DemoDataSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php`
- Modify: `backend/routes/console.php`
- Test: `backend/tests/Unit/DemoData/DemoDataSeederTest.php`

**Interfaces:**
- Consumes: `DemoDatasetGenerator`, `WorkspaceDatabaseSeeder`, `DB::table()`.
- Produces:
  - `DemoDataSeeder::run(): void`
  - Artisan command `php artisan demo:seed [--seed=42]`
  - Populated PostgreSQL tables with batch inserts.

- [ ] **Step 1: Write the failing unit test for DemoDataSeeder registration and execution contract**

```php
<?php

namespace Tests\Unit\DemoData;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DemoDataSeederTest extends TestCase
{
    #[Test]
    public function database_seeder_calls_demo_data_seeder(): void
    {
        $seederFile = dirname(__DIR__, 3).'/database/seeders/DatabaseSeeder.php';
        $content = (string) file_get_contents($seederFile);

        self::assertStringContainsString('DemoDataSeeder::class', $content);
        self::assertTrue(class_exists(DemoDataSeeder::class));
    }

    #[Test]
    public function console_routes_register_demo_seed_command(): void
    {
        $consoleFile = dirname(__DIR__, 3).'/routes/console.php';
        $content = (string) file_get_contents($consoleFile);

        self::assertStringContainsString('demo:seed', $content);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter DemoDataSeederTest`
Expected: FAIL with `Failed asserting that false is true`.

- [ ] **Step 3: Implement DemoDataSeeder, update DatabaseSeeder and routes/console.php**

Create `backend/database/seeders/DemoDataSeeder.php`, update `DatabaseSeeder.php` and `routes/console.php` (см. листинги выше).

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter DemoDataSeederTest`
Expected: PASS (2 tests, 3 assertions).

- [ ] **Step 5: Commit**

```bash
git add backend/database/seeders/DemoDataSeeder.php \
        backend/database/seeders/DatabaseSeeder.php \
        backend/routes/console.php \
        backend/tests/Unit/DemoData/DemoDataSeederTest.php
git commit -m "feat(analytics): integrate DemoDataSeeder into DatabaseSeeder and register artisan demo:seed command"
```

---

### Task 5: Integration Verification, Static Analysis & Docker PostgreSQL Validation

**Files:**
- Modify: `scripts/verify-integration.sh`
- Test: `make check`, `scripts/verify-integration.sh`

**Interfaces:**
- Consumes: Running Docker stack (`autobi-backend`, `autobi-postgres`), `curl`, `docker compose exec`.
- Produces: Passing end-to-end verification of database migrations, row counts, workspace boundary in Postgres.

- [ ] **Step 1: Add demo dataset verification checks to `scripts/verify-integration.sh`**

Modify `scripts/verify-integration.sh` to add verification steps for orders count and stockouts.

- [ ] **Step 2: Run linter and static analysis**

Run: `composer --working-dir=backend lint`
Expected: Pint passed, PHPStan passed [OK] No errors.

- [ ] **Step 3: Run full backend test suite**

Run: `composer --working-dir=backend test`
Expected: All tests pass including `ArchitectureTest`.

- [ ] **Step 4: Rebuild backend Docker image and execute integration verification**

Run:
```bash
make build
make integration
```
Expected:
1. Migrations run cleanly on PostgreSQL.
2. `db:seed` populates both workspaces with automotive data.
3. Health check passes.
4. Access boundary passes.
5. Demo dataset row counts and stockout conditions pass.

- [ ] **Step 5: Commit**

```bash
git add scripts/verify-integration.sh
git commit -m "test(integration): add PostgreSQL demo data model and stockout assertions to integration verification"
```

---

### Task 6: Document Progress and Close Exit Criteria for Phase 3

**Files:**
- Modify: `docs/roadmap/03-demo-data-model.md`
- Modify: `docs/roadmap/ROADMAP.md`

**Interfaces:**
- Consumes: Completed Phase 3 deliverable and test outputs.
- Produces: Updated Phase 3 document with `## Прогресс` and `## Проверка завершения`, updated index `[x] [Phase 3 — Demo Data Model]`.

- [ ] **Step 1: Document progress in `docs/roadmap/03-demo-data-model.md`**

Add `## Прогресс` and `## Проверка завершения` with date, exact commands run, assertion counts, schema structure, and verification that all exit criteria are satisfied.

- [ ] **Step 2: Update ROADMAP.md index**

Mark Phase 3 complete:
`- [x] [Phase 3 — Demo Data Model](03-demo-data-model.md)`

- [ ] **Step 3: Commit**

```bash
git add docs/roadmap/03-demo-data-model.md docs/roadmap/ROADMAP.md
git commit -m "docs(roadmap): complete Phase 3 demo data model"
```
