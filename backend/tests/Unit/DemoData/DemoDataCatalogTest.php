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
        self::assertGreaterThanOrEqual(20, count($products));

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
