<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\InventoryAnalytics\Domain;

use App\Modules\InventoryAnalytics\Domain\AbcClass;
use App\Modules\InventoryAnalytics\Domain\AbcXyzCalculator;
use App\Modules\InventoryAnalytics\Domain\AbcXyzGroup;
use App\Modules\InventoryAnalytics\Domain\XyzClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AbcXyzCalculatorTest extends TestCase
{
    #[Test]
    public function it_calculates_coefficient_of_variation_correctly(): void
    {
        // Stable demand: 10, 10, 10 -> std dev = 0, CV = 0
        $cvStable = AbcXyzCalculator::calculateVariation([10.0, 10.0, 10.0]);
        self::assertNotNull($cvStable);
        self::assertSame(0.0, $cvStable);

        // Moderate variation: 10, 12, 11 -> mean = 11, variance = ((1)+ (1) + 0)/2 = 1, std = 1, CV = 1/11 = 0.0909
        $cvModerate = AbcXyzCalculator::calculateVariation([10.0, 12.0, 11.0]);
        self::assertNotNull($cvModerate);
        self::assertEqualsWithDelta(0.0909, $cvModerate, 0.001);

        // Volatile demand: 0, 100, 0 -> high CV
        $cvVolatile = AbcXyzCalculator::calculateVariation([0.0, 100.0, 0.0]);
        self::assertNotNull($cvVolatile);
        self::assertGreaterThan(0.35, $cvVolatile);
    }

    #[Test]
    public function it_handles_variation_boundary_cases(): void
    {
        // Empty array
        self::assertNull(AbcXyzCalculator::calculateVariation([]));

        // All zeroes -> null CV (zero mean)
        self::assertNull(AbcXyzCalculator::calculateVariation([0.0, 0.0, 0.0]));

        // Single period -> 0.0 CV
        self::assertSame(0.0, AbcXyzCalculator::calculateVariation([50.0]));
    }

    #[Test]
    public function it_classifies_abc_correctly(): void
    {
        self::assertSame(AbcClass::A, AbcXyzCalculator::classifyAbc(0.50, 1000.0));
        self::assertSame(AbcClass::A, AbcXyzCalculator::classifyAbc(0.80, 500.0));
        self::assertSame(AbcClass::B, AbcXyzCalculator::classifyAbc(0.85, 300.0));
        self::assertSame(AbcClass::B, AbcXyzCalculator::classifyAbc(0.95, 200.0));
        self::assertSame(AbcClass::C, AbcXyzCalculator::classifyAbc(0.98, 50.0));
        self::assertSame(AbcClass::C, AbcXyzCalculator::classifyAbc(1.00, 10.0));

        // Zero revenue always classifies as C regardless of cumulative share
        self::assertSame(AbcClass::C, AbcXyzCalculator::classifyAbc(0.50, 0.0));
    }

    #[Test]
    public function it_classifies_xyz_correctly(): void
    {
        self::assertSame(XyzClass::X, AbcXyzCalculator::classifyXyz(0.05, 50));
        self::assertSame(XyzClass::X, AbcXyzCalculator::classifyXyz(0.15, 30));
        self::assertSame(XyzClass::Y, AbcXyzCalculator::classifyXyz(0.20, 20));
        self::assertSame(XyzClass::Y, AbcXyzCalculator::classifyXyz(0.35, 15));
        self::assertSame(XyzClass::Z, AbcXyzCalculator::classifyXyz(0.40, 10));

        // Zero units or null CV classifies as Z
        self::assertSame(XyzClass::Z, AbcXyzCalculator::classifyXyz(null, 0));
        self::assertSame(XyzClass::Z, AbcXyzCalculator::classifyXyz(0.05, 0));
    }

    #[Test]
    public function it_combines_groups_and_exposes_recommendations(): void
    {
        $groupAx = AbcXyzGroup::from('AX');
        self::assertSame(AbcClass::A, $groupAx->abcClass());
        self::assertSame(XyzClass::X, $groupAx->xyzClass());
        self::assertNotEmpty($groupAx->label());
        self::assertNotEmpty($groupAx->description());
        self::assertNotEmpty($groupAx->recommendation());

        $groupCz = AbcXyzGroup::from('CZ');
        self::assertSame(AbcClass::C, $groupCz->abcClass());
        self::assertSame(XyzClass::Z, $groupCz->xyzClass());
    }

    #[Test]
    public function it_analyzes_product_dataset_and_produces_matrix_and_items(): void
    {
        $dataset = [
            [
                'product_id' => 'prod-1',
                'product_name' => 'Шина Michelin 16',
                'product_sku' => 'SKU-1',
                'category_id' => 'cat-tires',
                'category_name' => 'Шины',
                'brand_name' => 'Michelin',
                'supplier_id' => 'sup-1',
                'supplier_name' => 'EuroTech',
                'revenue' => 70000.0,
                'units_sold' => 70,
                'period_sales' => [23.0, 24.0, 23.0], // stable -> X
                'current_stock' => 15,
                'inventory_value' => 120000.0,
            ],
            [
                'product_id' => 'prod-2',
                'product_name' => 'Колодки Brembo',
                'product_sku' => 'SKU-2',
                'category_id' => 'cat-brakes',
                'category_name' => 'Тормоза',
                'brand_name' => 'Brembo',
                'supplier_id' => 'sup-1',
                'supplier_name' => 'EuroTech',
                'revenue' => 20000.0,
                'units_sold' => 20,
                'period_sales' => [5.0, 10.0, 5.0], // fluctuating -> Y or Z
                'current_stock' => 8,
                'inventory_value' => 25000.0,
            ],
            [
                'product_id' => 'prod-3',
                'product_name' => 'Клипса обшивки',
                'product_sku' => 'SKU-3',
                'category_id' => 'cat-other',
                'category_name' => 'Прочее',
                'brand_name' => 'Febi',
                'supplier_id' => null,
                'supplier_name' => null,
                'revenue' => 10000.0,
                'units_sold' => 100,
                'period_sales' => [33.0, 34.0, 33.0], // stable -> X
                'current_stock' => 200,
                'inventory_value' => 5000.0,
            ],
            [
                'product_id' => 'prod-4',
                'product_name' => 'Неликвидный датчик',
                'product_sku' => 'SKU-4',
                'category_id' => 'cat-elec',
                'category_name' => 'Электрика',
                'brand_name' => 'Bosch',
                'supplier_id' => null,
                'supplier_name' => null,
                'revenue' => 0.0,
                'units_sold' => 0,
                'period_sales' => [0.0, 0.0, 0.0], // zero -> Z
                'current_stock' => 5,
                'inventory_value' => 10000.0,
            ],
        ];

        $analysis = AbcXyzCalculator::analyze($dataset);

        self::assertSame(4, $analysis['total_products']);
        self::assertSame(100000.0, $analysis['total_revenue']);
        self::assertSame(160000.0, $analysis['total_inventory_value']);

        // Check products classified
        $items = $analysis['items'];
        self::assertCount(4, $items);

        // Prod 1: 70k of 100k = 70% -> Class A, CV ~ 0.025 -> Class X => AX
        self::assertSame('prod-1', $items[0]['product_id']);
        self::assertSame('A', $items[0]['abc_class']);
        self::assertSame('X', $items[0]['xyz_class']);
        self::assertSame('AX', $items[0]['abc_xyz_group']);
        self::assertSame(0.70, $items[0]['revenue_share']);
        self::assertSame(0.70, $items[0]['cumulative_revenue_share']);

        // Prod 2: 20k of 100k = 20% -> cumulative 90% -> Class B
        self::assertSame('prod-2', $items[1]['product_id']);
        self::assertSame('B', $items[1]['abc_class']);

        // Prod 3: 10k of 100k = 10% -> cumulative 100% -> Class C, stable -> X => CX
        self::assertSame('prod-3', $items[2]['product_id']);
        self::assertSame('C', $items[2]['abc_class']);
        self::assertSame('X', $items[2]['xyz_class']);
        self::assertSame('CX', $items[2]['abc_xyz_group']);

        // Prod 4: 0 revenue -> C and Z => CZ, current stock and inventory value preserved!
        self::assertSame('prod-4', $items[3]['product_id']);
        self::assertSame('C', $items[3]['abc_class']);
        self::assertSame('Z', $items[3]['xyz_class']);
        self::assertSame('CZ', $items[3]['abc_xyz_group']);
        self::assertSame(10000.0, $items[3]['inventory_value']);

        // Matrix grid checks: 9 cells present
        $matrix = $analysis['matrix'];
        self::assertCount(9, $matrix);
        $matrixCodes = array_column($matrix, 'code');
        self::assertSame(['AX', 'AY', 'AZ', 'BX', 'BY', 'BZ', 'CX', 'CY', 'CZ'], $matrixCodes);

        // Verify AX cell
        $axCell = $matrix[0];
        self::assertSame('AX', $axCell['code']);
        self::assertSame(1, $axCell['count']);
        self::assertSame(70000.0, $axCell['revenue']);
        self::assertSame(120000.0, $axCell['inventory_value']);
        self::assertSame(0.70, $axCell['revenue_share']);
        self::assertSame(0.75, $axCell['inventory_value_share']); // 120k / 160k = 0.75
    }

    #[Test]
    public function it_handles_completely_empty_and_zero_revenue_datasets(): void
    {
        $emptyAnalysis = AbcXyzCalculator::analyze([]);
        self::assertSame(0, $emptyAnalysis['total_products']);
        self::assertSame(0.0, $emptyAnalysis['total_revenue']);
        self::assertSame(0.0, $emptyAnalysis['total_inventory_value']);
        self::assertCount(9, $emptyAnalysis['matrix']);

        $zeroRevenueDataset = [
            [
                'product_id' => 'p1',
                'product_name' => 'Item 1',
                'product_sku' => 'SKU-1',
                'category_id' => 'c1',
                'category_name' => 'Cat 1',
                'brand_name' => 'Brand 1',
                'supplier_id' => null,
                'supplier_name' => null,
                'revenue' => 0.0,
                'units_sold' => 0,
                'period_sales' => [0.0, 0.0],
                'current_stock' => 10,
                'inventory_value' => 5000.0,
            ],
        ];

        $zeroAnalysis = AbcXyzCalculator::analyze($zeroRevenueDataset);
        self::assertSame(1, $zeroAnalysis['total_products']);
        self::assertSame(0.0, $zeroAnalysis['total_revenue']);
        self::assertSame('CZ', $zeroAnalysis['items'][0]['abc_xyz_group']);
    }
}
