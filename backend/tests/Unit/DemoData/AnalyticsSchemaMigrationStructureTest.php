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
