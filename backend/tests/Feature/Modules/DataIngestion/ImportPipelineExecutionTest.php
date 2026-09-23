<?php

namespace Tests\Feature\Modules\DataIngestion;

use App\Modules\DataIngestion\Application\Contracts\StarSchemaProjectorInterface;
use App\Modules\DataIngestion\Infrastructure\Projection\InMemoryStarSchemaProjector;
use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ImportPipelineExecutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $userRepo = $this->app->make(UserRepositoryInterface::class);
        $wsRepo = $this->app->make(WorkspaceRepositoryInterface::class);

        $user = new User(new UserId('user-1'), 'admin@example.com', 'Admin');
        $userRepo->save($user);

        $ws = new Workspace(new WorkspaceId('ws-1'), 'Workspace 1', 'workspace-1');
        $ws->addMember(new UserId('user-1'), MembershipRole::OWNER);
        $wsRepo->save($ws);
    }

    #[Test]
    public function full_lifecycle_upload_process_inspect_and_retry(): void
    {
        // 1. Upload CSV with 1 valid row and 1 invalid row
        $csvContent = "order_number,order_date,channel_code,region_code,warehouse_code,sku,quantity,unit_price,unit_cost,order_status\nORD-001,2026-03-01,online,RU-MSK,WH-01,BRAKE-01,2,100.00,50.00,completed\nORD-002,invalid-date,online,RU-MSK,WH-01,BRAKE-02,1,100.00,50.00,completed\n";
        $file = UploadedFile::fake()->createWithContent('orders.csv', $csvContent);

        $uploadResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->post('/api/v1/imports', [
            'file' => $file,
            'dataset_type' => 'sales',
        ])->assertStatus(202);

        $batchId = $uploadResponse->json('batch.id');
        self::assertNotEmpty($batchId);

        // 2. Inspect batch detail (processed synchronously by queue in testing)
        $detailResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/imports/{$batchId}")
            ->assertOk();

        self::assertSame('completed_with_errors', $detailResponse->json('batch.status'));
        self::assertSame(2, $detailResponse->json('batch.total_rows'));
        self::assertSame(1, $detailResponse->json('batch.successful_rows'));
        self::assertSame(1, $detailResponse->json('batch.failed_rows'));

        // 3. Inspect failure rows
        $failuresResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/imports/{$batchId}/failures")
            ->assertOk();

        self::assertSame(1, $failuresResponse->json('total'));
        self::assertSame(2, $failuresResponse->json('items.0.row_number'));
        self::assertSame('order_date', $failuresResponse->json('items.0.field'));

        // 4. Trigger Retry
        $retryResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson("/api/v1/imports/{$batchId}/retry")
            ->assertStatus(202);

        self::assertContains($retryResponse->json('batch.status'), ['pending', 'completed_with_errors']);

        // 5. Confirm star schema projection (1 row on initial upload, 1 row on retry re-projection)
        /** @var InMemoryStarSchemaProjector $projector */
        $projector = $this->app->make(StarSchemaProjectorInterface::class);
        self::assertCount(2, $projector->projectedSalesRows);
        self::assertSame('ORD-001', $projector->projectedSalesRows[0]['row']['order_number']);
        self::assertSame('BRAKE-01', $projector->projectedSalesRows[0]['row']['sku']);
        self::assertSame('ORD-001', $projector->projectedSalesRows[1]['row']['order_number']);
    }

    #[Test]
    public function full_lifecycle_inventory_import_pipeline_projects_to_fact_inventory_daily(): void
    {
        $csvContent = "snapshot_date,warehouse_code,sku,quantity_on_hand,quantity_reserved,safety_stock,reorder_point,unit_cost\n2026-03-01,WH-01,BRAKE-01,50,5,10,15,45.00\n";
        $file = UploadedFile::fake()->createWithContent('inventory.csv', $csvContent);

        $uploadResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->post('/api/v1/imports', [
            'file' => $file,
            'dataset_type' => 'inventory',
        ])->assertStatus(202);

        $batchId = $uploadResponse->json('batch.id');
        self::assertNotEmpty($batchId);

        $detailResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/imports/{$batchId}")
            ->assertOk();

        self::assertSame('completed', $detailResponse->json('batch.status'));
        self::assertSame(1, $detailResponse->json('batch.total_rows'));
        self::assertSame(1, $detailResponse->json('batch.successful_rows'));
        self::assertSame(0, $detailResponse->json('batch.failed_rows'));

        /** @var InMemoryStarSchemaProjector $projector */
        $projector = $this->app->make(StarSchemaProjectorInterface::class);
        self::assertCount(1, $projector->projectedInventoryRows);
        self::assertSame('WH-01', $projector->projectedInventoryRows[0]['row']['warehouse_code']);
        self::assertSame('BRAKE-01', $projector->projectedInventoryRows[0]['row']['sku']);
        self::assertSame('50', $projector->projectedInventoryRows[0]['row']['quantity_on_hand']);
    }
}
