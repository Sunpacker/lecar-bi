<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Performance;

use App\Modules\InventoryAnalytics\Application\Contracts\InventoryAnalyticsReadModelInterface;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzSummaryCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventoryItemsCriteriaDto;
use App\Modules\InventoryAnalytics\Application\Dtos\InventorySummaryCriteriaDto;
use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Application\Dtos\SalesFilterCriteriaDto;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordsCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Contracts\SupplierAnalyticsReadModelInterface;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierDeliveriesCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierOverviewCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierPerformanceCriteriaDto;
use Closure;
use Database\Seeders\Performance\PerformanceDatasetGenerator;
use InvalidArgumentException;

final readonly class BenchmarkScenario
{
    /**
     * @param  list<string>  $supportedCacheStates
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $module,
        public int $budgetP95Ms,
        public array $supportedCacheStates,
        public Closure $executor,
        public bool $isReadOnly = true,
    ) {}

    /**
     * @return array<string, self>
     */
    public static function all(string $workspaceId): array
    {
        $dim = fn (string $baseId) => PerformanceDatasetGenerator::dimensionId($baseId, $workspaceId);

        $sales = fn () => app(SalesAnalyticsReadModelInterface::class);
        $inventory = fn () => app(InventoryAnalyticsReadModelInterface::class);
        $supplier = fn () => app(SupplierAnalyticsReadModelInterface::class);

        $scenarios = [
            // --- Sales Analytics ---
            'SALES-01' => new self(
                id: 'SALES-01',
                name: 'Sales Overview (Default)',
                module: 'SalesAnalytics',
                budgetP95Ms: 1000,
                supportedCacheStates: ['disabled', 'cold', 'warm'],
                executor: fn () => $sales()->getSalesOverview($workspaceId, new SalesFilterCriteriaDto),
            ),
            'SALES-02' => new self(
                id: 'SALES-02',
                name: 'Sales Overview (Selective)',
                module: 'SalesAnalytics',
                budgetP95Ms: 1000,
                supportedCacheStates: ['disabled', 'cold', 'warm'],
                executor: fn () => $sales()->getSalesOverview($workspaceId, new SalesFilterCriteriaDto(
                    dateFrom: '2026-06-01',
                    dateTo: '2026-06-30',
                    categoryId: $dim('cat-electronics'),
                    regionId: $dim('reg-north'),
                )),
            ),
            'SALES-03' => new self(
                id: 'SALES-03',
                name: 'Sales Filter Options',
                module: 'SalesAnalytics',
                budgetP95Ms: 1000,
                supportedCacheStates: ['disabled', 'cold', 'warm'],
                executor: fn () => $sales()->getFilterOptions($workspaceId),
            ),
            'SALES-04' => new self(
                id: 'SALES-04',
                name: 'Sales Records (Default Pagination)',
                module: 'SalesAnalytics',
                budgetP95Ms: 1500,
                supportedCacheStates: ['disabled'],
                executor: fn () => $sales()->getSalesRecords($workspaceId, new SalesRecordsCriteriaDto(
                    page: 1,
                    perPage: 20,
                    sortBy: 'order_date',
                    sortDirection: 'desc',
                )),
            ),
            'SALES-05' => new self(
                id: 'SALES-05',
                name: 'Sales Records (Selective Filtered)',
                module: 'SalesAnalytics',
                budgetP95Ms: 1500,
                supportedCacheStates: ['disabled'],
                executor: fn () => $sales()->getSalesRecords($workspaceId, new SalesRecordsCriteriaDto(
                    dateFrom: '2026-01-01',
                    dateTo: '2026-03-31',
                    categoryId: $dim('cat-electronics'),
                    regionId: $dim('reg-west'),
                    page: 5,
                    perPage: 20,
                    sortBy: 'total_amount',
                    sortDirection: 'desc',
                )),
            ),

            // --- Inventory Analytics ---
            'INV-01' => new self(
                id: 'INV-01',
                name: 'Inventory Summary (Default)',
                module: 'InventoryAnalytics',
                budgetP95Ms: 1000,
                supportedCacheStates: ['disabled', 'cold', 'warm'],
                executor: fn () => $inventory()->getInventorySummary($workspaceId, new InventorySummaryCriteriaDto),
            ),
            'INV-02' => new self(
                id: 'INV-02',
                name: 'Inventory Summary (Selective)',
                module: 'InventoryAnalytics',
                budgetP95Ms: 1000,
                supportedCacheStates: ['disabled', 'cold', 'warm'],
                executor: fn () => $inventory()->getInventorySummary($workspaceId, new InventorySummaryCriteriaDto(
                    warehouseId: $dim('wh-central'),
                    asOfDate: '2026-05-15',
                )),
            ),
            'INV-03' => new self(
                id: 'INV-03',
                name: 'Inventory Filter Options',
                module: 'InventoryAnalytics',
                budgetP95Ms: 1000,
                supportedCacheStates: ['disabled', 'cold', 'warm'],
                executor: fn () => $inventory()->getFilterOptions($workspaceId),
            ),
            'INV-04' => new self(
                id: 'INV-04',
                name: 'Inventory Items (Default Pagination)',
                module: 'InventoryAnalytics',
                budgetP95Ms: 1500,
                supportedCacheStates: ['disabled'],
                executor: fn () => $inventory()->getInventoryItems($workspaceId, new InventoryItemsCriteriaDto(
                    page: 1,
                    perPage: 20,
                    sortBy: 'quantity_available',
                    sortDirection: 'asc',
                )),
            ),
            'INV-05' => new self(
                id: 'INV-05',
                name: 'Inventory Items (Selective Search)',
                module: 'InventoryAnalytics',
                budgetP95Ms: 1500,
                supportedCacheStates: ['disabled'],
                executor: fn () => $inventory()->getInventoryItems($workspaceId, new InventoryItemsCriteriaDto(
                    warehouseId: $dim('wh-central'),
                    stockHealth: 'low_stock',
                    search: 'Filter',
                    page: 2,
                    perPage: 20,
                )),
            ),
            'INV-06' => new self(
                id: 'INV-06',
                name: 'ABC/XYZ Summary (90 Days Default)',
                module: 'InventoryAnalytics',
                budgetP95Ms: 1000,
                supportedCacheStates: ['disabled', 'cold', 'warm'],
                executor: fn () => $inventory()->getAbcXyzSummary($workspaceId, new AbcXyzSummaryCriteriaDto(
                    periodDays: 90,
                )),
            ),
            'INV-07' => new self(
                id: 'INV-07',
                name: 'ABC/XYZ Summary (Selective Filtered)',
                module: 'InventoryAnalytics',
                budgetP95Ms: 1000,
                supportedCacheStates: ['disabled', 'cold', 'warm'],
                executor: fn () => $inventory()->getAbcXyzSummary($workspaceId, new AbcXyzSummaryCriteriaDto(
                    periodDays: 30,
                    warehouseId: $dim('wh-north'),
                    categoryId: $dim('cat-auto-parts'),
                    supplierId: $dim('sup-bosch'),
                )),
            ),
            'INV-08' => new self(
                id: 'INV-08',
                name: 'ABC/XYZ Items (Group AX Page 1)',
                module: 'InventoryAnalytics',
                budgetP95Ms: 1500,
                supportedCacheStates: ['disabled'],
                executor: fn () => $inventory()->getAbcXyzItems($workspaceId, new AbcXyzItemsCriteriaDto(
                    periodDays: 90,
                    group: 'AX',
                    page: 1,
                    perPage: 20,
                    sortBy: 'total_revenue',
                    sortDirection: 'desc',
                )),
            ),
            'INV-09' => new self(
                id: 'INV-09',
                name: 'ABC/XYZ Items (Selective Filtered)',
                module: 'InventoryAnalytics',
                budgetP95Ms: 1500,
                supportedCacheStates: ['disabled'],
                executor: fn () => $inventory()->getAbcXyzItems($workspaceId, new AbcXyzItemsCriteriaDto(
                    periodDays: 90,
                    categoryId: $dim('cat-electronics'),
                    abcClass: 'A',
                    xyzClass: 'X',
                    search: 'Sensor',
                    page: 1,
                    perPage: 20,
                )),
            ),

            // --- Supplier Analytics ---
            'SUP-01' => new self(
                id: 'SUP-01',
                name: 'Supplier Overview (Default)',
                module: 'SupplierAnalytics',
                budgetP95Ms: 1000,
                supportedCacheStates: ['disabled', 'cold', 'warm'],
                executor: fn () => $supplier()->getSupplierOverview($workspaceId, new SupplierOverviewCriteriaDto),
            ),
            'SUP-02' => new self(
                id: 'SUP-02',
                name: 'Supplier Overview (Selective)',
                module: 'SupplierAnalytics',
                budgetP95Ms: 1000,
                supportedCacheStates: ['disabled', 'cold', 'warm'],
                executor: fn () => $supplier()->getSupplierOverview($workspaceId, new SupplierOverviewCriteriaDto(
                    dateFrom: '2026-01-01',
                    dateTo: '2026-06-30',
                    supplierId: $dim('sup-valeo'),
                    warehouseId: $dim('wh-central'),
                )),
            ),
            'SUP-03' => new self(
                id: 'SUP-03',
                name: 'Supplier Filter Options',
                module: 'SupplierAnalytics',
                budgetP95Ms: 1000,
                supportedCacheStates: ['disabled', 'cold', 'warm'],
                executor: fn () => $supplier()->getFilterOptions($workspaceId),
            ),
            'SUP-04' => new self(
                id: 'SUP-04',
                name: 'Supplier Performance (Default Paginated)',
                module: 'SupplierAnalytics',
                budgetP95Ms: 1500,
                supportedCacheStates: ['disabled'],
                executor: fn () => $supplier()->getSupplierPerformance($workspaceId, new SupplierPerformanceCriteriaDto(
                    page: 1,
                    perPage: 20,
                    sortBy: 'total_spend',
                    sortDirection: 'desc',
                )),
            ),
            'SUP-05' => new self(
                id: 'SUP-05',
                name: 'Supplier Performance (Selective Search)',
                module: 'SupplierAnalytics',
                budgetP95Ms: 1500,
                supportedCacheStates: ['disabled'],
                executor: fn () => $supplier()->getSupplierPerformance($workspaceId, new SupplierPerformanceCriteriaDto(
                    dateFrom: '2026-01-01',
                    dateTo: '2026-06-30',
                    warehouseId: $dim('wh-north'),
                    search: 'Auto',
                    page: 1,
                    perPage: 20,
                )),
            ),
            'SUP-06' => new self(
                id: 'SUP-06',
                name: 'Supplier Deliveries (Default Paginated)',
                module: 'SupplierAnalytics',
                budgetP95Ms: 1500,
                supportedCacheStates: ['disabled'],
                executor: fn () => $supplier()->getSupplierDeliveries($workspaceId, new SupplierDeliveriesCriteriaDto(
                    page: 1,
                    perPage: 20,
                    sortBy: 'order_date',
                    sortDirection: 'desc',
                )),
            ),
            'SUP-07' => new self(
                id: 'SUP-07',
                name: 'Supplier Deliveries (Selective Delayed)',
                module: 'SupplierAnalytics',
                budgetP95Ms: 1500,
                supportedCacheStates: ['disabled'],
                executor: fn () => $supplier()->getSupplierDeliveries($workspaceId, new SupplierDeliveriesCriteriaDto(
                    supplierId: $dim('sup-valeo'),
                    warehouseId: $dim('wh-central'),
                    status: 'delayed',
                    dateFrom: '2026-01-01',
                    dateTo: '2026-06-30',
                    page: 1,
                    perPage: 20,
                )),
            ),

            // --- Dashboard Fan-out ---
            'DASH-01' => new self(
                id: 'DASH-01',
                name: 'Executive Dashboard Fan-out',
                module: 'Dashboard',
                budgetP95Ms: 2000,
                supportedCacheStates: ['disabled', 'cold', 'warm'],
                executor: function () use ($sales, $inventory, $supplier, $workspaceId) {
                    return [
                        $sales()->getSalesOverview($workspaceId, new SalesFilterCriteriaDto),
                        $inventory()->getInventorySummary($workspaceId, new InventorySummaryCriteriaDto),
                        $supplier()->getSupplierOverview($workspaceId, new SupplierOverviewCriteriaDto),
                        $sales()->getFilterOptions($workspaceId),
                    ];
                },
            ),
            'DASH-02' => new self(
                id: 'DASH-02',
                name: 'Dashboard Duplicate Aggregates',
                module: 'Dashboard',
                budgetP95Ms: 2000,
                supportedCacheStates: ['disabled', 'cold', 'warm'],
                executor: function () use ($sales, $workspaceId) {
                    $criteria = new SalesFilterCriteriaDto;

                    return [
                        $sales()->getSalesOverview($workspaceId, $criteria),
                        $sales()->getSalesOverview($workspaceId, $criteria),
                        $sales()->getSalesOverview($workspaceId, $criteria),
                    ];
                },
            ),
        ];

        return $scenarios;
    }

    public static function find(string $id, string $workspaceId): ?self
    {
        $all = self::all($workspaceId);

        return $all[$id] ?? null;
    }

    public static function get(string $id, string $workspaceId): self
    {
        $scenario = self::find($id, $workspaceId);
        if ($scenario === null) {
            throw new InvalidArgumentException("Unknown benchmark scenario ID '{$id}'.");
        }

        return $scenario;
    }
}
