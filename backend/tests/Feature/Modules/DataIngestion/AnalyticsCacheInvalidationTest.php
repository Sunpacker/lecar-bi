<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\DataIngestion;

use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use App\Shared\Infrastructure\Cache\AnalyticsDatasetVersionStore;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnalyticsCacheInvalidationTest extends TestCase
{
    private AnalyticsDatasetVersionStore $versionStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->versionStore = $this->app->make(AnalyticsDatasetVersionStore::class);
        $this->versionStore->resetLocalCache();

        $userRepo = $this->app->make(UserRepositoryInterface::class);
        $wsRepo = $this->app->make(WorkspaceRepositoryInterface::class);

        $user = new User(new UserId('user-1'), 'admin@example.com', 'Admin');
        $userRepo->save($user);

        $ws = new Workspace(new WorkspaceId('ws-1'), 'Workspace 1', 'workspace-1');
        $ws->addMember(new UserId('user-1'), MembershipRole::OWNER);
        $wsRepo->save($ws);
    }

    #[Test]
    public function sales_import_bumps_sales_dataset_version_but_leaves_inventory_untouched(): void
    {
        $initialSalesVersion = $this->versionStore->getVersion('ws-1', 'sales');
        $initialInventoryVersion = $this->versionStore->getVersion('ws-1', 'inventory');

        $csvContent = "order_number,order_date,channel_code,region_code,warehouse_code,sku,quantity,unit_price,unit_cost,order_status\nORD-001,2026-03-01,online,RU-MSK,WH-01,BRAKE-01,2,100.00,50.00,completed\n";
        $file = UploadedFile::fake()->createWithContent('orders.csv', $csvContent);

        $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->post('/api/v1/imports', [
            'file' => $file,
            'dataset_type' => 'sales',
        ])->assertStatus(202);

        $newSalesVersion = $this->versionStore->getVersion('ws-1', 'sales');
        $newInventoryVersion = $this->versionStore->getVersion('ws-1', 'inventory');

        self::assertGreaterThan($initialSalesVersion, $newSalesVersion, 'Sales version must increase after sales import');
        self::assertSame($initialInventoryVersion, $newInventoryVersion, 'Inventory version must remain unchanged');
    }

    #[Test]
    public function partial_success_import_bumps_dataset_version(): void
    {
        $initialVersion = $this->versionStore->getVersion('ws-1', 'sales');

        // 1 valid row + 1 invalid row -> completed_with_errors
        $csvContent = "order_number,order_date,channel_code,region_code,warehouse_code,sku,quantity,unit_price,unit_cost,order_status\nORD-001,2026-03-01,online,RU-MSK,WH-01,BRAKE-01,2,100.00,50.00,completed\nORD-002,invalid-date,online,RU-MSK,WH-01,BRAKE-02,1,100.00,50.00,completed\n";
        $file = UploadedFile::fake()->createWithContent('orders_partial.csv', $csvContent);

        $uploadResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->post('/api/v1/imports', [
            'file' => $file,
            'dataset_type' => 'sales',
        ])->assertStatus(202);

        $batchId = $uploadResponse->json('batch.id');

        $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/imports/{$batchId}")
            ->assertOk()
            ->assertJsonPath('batch.status', 'completed_with_errors');

        $newVersion = $this->versionStore->getVersion('ws-1', 'sales');
        self::assertGreaterThan($initialVersion, $newVersion, 'Version must increase even on partial success');
    }
}
