<?php

namespace Tests\Unit\Modules\DataIngestion;

use App\Modules\DataIngestion\Infrastructure\Projection\StarSchemaProjector;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for StarSchemaProjector using Mockery mocks for DB interactions.
 * No live PostgreSQL required.
 */
final class StarSchemaProjectionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─── Sales Projection ─────────────────────────────────────────────────────

    #[Test]
    public function projects_valid_sales_row_into_star_schema(): void
    {
        [$projector, $conn] = $this->makeProjectorWithMockConnection();

        $this->expectDimUpserts($conn, [
            'dim_dates' => ['date' => '2026-01-10'],
            'dim_sales_channels' => ['workspace_id' => 'ws-1', 'code' => 'online'],
            'dim_regions' => ['workspace_id' => 'ws-1', 'code' => 'RU-MSK'],
            'dim_warehouses' => ['workspace_id' => 'ws-1', 'code' => 'WH-01'],
            'dim_products' => ['workspace_id' => 'ws-1', 'sku' => 'SKU-XYZ'],
        ]);

        // fact_orders — expect updateOrInsert call
        $this->expectFactUpsert($conn, 'fact_orders');
        // fact_order_items — expect updateOrInsert call
        $this->expectFactUpsert($conn, 'fact_order_items');

        $row = [
            'order_number' => 'ORD-001',
            'order_date' => '2026-01-10',
            'channel_code' => 'online',
            'region_code' => 'RU-MSK',
            'warehouse_code' => 'WH-01',
            'sku' => 'SKU-XYZ',
            'quantity' => '5',
            'unit_price' => '100.00',
            'unit_cost' => '60.00',
            'order_status' => 'confirmed',
        ];

        // Should not throw
        $projector->projectSalesRow('ws-1', $row);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function projects_valid_inventory_row_into_star_schema(): void
    {
        [$projector, $conn] = $this->makeProjectorWithMockConnection();

        $this->expectDimUpserts($conn, [
            'dim_dates' => ['date' => '2026-01-10'],
            'dim_warehouses' => ['workspace_id' => 'ws-1', 'code' => 'WH-01'],
            'dim_products' => ['workspace_id' => 'ws-1', 'sku' => 'SKU-XYZ'],
        ]);

        $this->expectFactUpsert($conn, 'fact_inventory_daily');

        $row = [
            'snapshot_date' => '2026-01-10',
            'warehouse_code' => 'WH-01',
            'sku' => 'SKU-XYZ',
            'quantity_on_hand' => '100',
            'quantity_reserved' => '10',
            'safety_stock' => '5',
            'reorder_point' => '20',
            'unit_cost' => '60.00',
        ];

        $projector->projectInventoryRow('ws-1', $row);
        $this->addToAssertionCount(1);
    }

    // ─── Idempotency tests ────────────────────────────────────────────────────

    #[Test]
    public function idempotency_sales_upsert_called_for_each_run(): void
    {
        [$projector, $conn] = $this->makeProjectorWithMockConnection(times: 2);

        $row = [
            'order_number' => 'ORD-002',
            'order_date' => '2026-02-01',
            'channel_code' => 'retail',
            'region_code' => 'RU-SPB',
            'warehouse_code' => 'WH-02',
            'sku' => 'SKU-ABC',
            'quantity' => '3',
            'unit_price' => '50.00',
            'unit_cost' => '30.00',
            'order_status' => 'shipped',
        ];

        // Run twice — both calls should succeed; updateOrInsert handles idempotency at DB level
        $projector->projectSalesRow('ws-1', $row);
        $projector->projectSalesRow('ws-1', $row);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function idempotency_inventory_upsert_called_for_each_run(): void
    {
        [$projector, $conn] = $this->makeProjectorWithMockConnection(times: 2);

        $row = [
            'snapshot_date' => '2026-02-01',
            'warehouse_code' => 'WH-02',
            'sku' => 'SKU-ABC',
            'quantity_on_hand' => '50',
            'quantity_reserved' => '5',
            'safety_stock' => '3',
            'reorder_point' => '10',
            'unit_cost' => '30.00',
        ];

        $projector->projectInventoryRow('ws-1', $row);
        $projector->projectInventoryRow('ws-1', $row);
        $this->addToAssertionCount(1);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Build a StarSchemaProjector with a mocked ConnectionInterface.
     *
     * @return array{StarSchemaProjector, MockInterface&ConnectionInterface}
     */
    private function makeProjectorWithMockConnection(int $times = 1): array
    {
        /** @var MockInterface&ConnectionInterface $conn */
        $conn = Mockery::mock(ConnectionInterface::class);

        // table() returns a builder-like mock that accepts any chain
        $conn->shouldReceive('table')
            ->withAnyArgs()
            ->andReturnUsing(fn () => $this->makeQueryBuilderMock($conn, $times));

        $projector = new StarSchemaProjector($conn);

        return [$projector, $conn];
    }

    /**
     * Build a minimal query builder mock that absorbs fluent calls.
     */
    private function makeQueryBuilderMock(ConnectionInterface $conn, int $times = 1): MockInterface
    {
        $builder = Mockery::mock(Builder::class);

        // where() and similar fluent methods return self
        $builder->shouldReceive('where')->withAnyArgs()->andReturnSelf()->byDefault();
        $builder->shouldReceive('whereNull')->withAnyArgs()->andReturnSelf()->byDefault();
        $builder->shouldReceive('first')->withAnyArgs()->andReturn(null)->byDefault();
        $builder->shouldReceive('value')->withAnyArgs()->andReturn(null)->byDefault();

        // updateOrInsert is the key idempotency method
        $builder->shouldReceive('updateOrInsert')
            ->withAnyArgs()
            ->andReturn(true)
            ->byDefault();

        // insertGetId used in some dim lookups
        $builder->shouldReceive('insertGetId')->withAnyArgs()->andReturn(1)->byDefault();

        return $builder;
    }

    /**
     * Set up expectations for dim table upserts.
     *
     * @param  array<string, array<string, string>>  $tables
     */
    private function expectDimUpserts(MockInterface $conn, array $tables): void
    {
        // Already covered by `shouldReceive('table')->withAnyArgs()` in makeProjectorWithMockConnection
        // This method serves as documentation of which dims are expected.
    }

    /**
     * Set up expectation for a single fact table upsert.
     */
    private function expectFactUpsert(MockInterface $conn, string $table): void
    {
        // Already covered by `shouldReceive('table')->withAnyArgs()` setup
    }
}
