<?php

namespace Tests\Feature\Modules\DataIngestion;

use App\Modules\DataIngestion\Domain\DatasetType;
use App\Modules\DataIngestion\Domain\ImportBatch;
use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\Repositories\ImportBatchRepositoryInterface;
use App\Modules\DataIngestion\Domain\SourceFormat;
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

final class ImportBatchApiTest extends TestCase
{
    private ImportBatchRepositoryInterface $batchRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $userRepo = $this->app->make(UserRepositoryInterface::class);
        $wsRepo = $this->app->make(WorkspaceRepositoryInterface::class);
        $this->batchRepo = $this->app->make(ImportBatchRepositoryInterface::class);

        $user1 = new User(new UserId('user-1'), 'user1@example.com', 'User One');
        $user2 = new User(new UserId('user-2'), 'user2@example.com', 'User Two');
        $userRepo->save($user1);
        $userRepo->save($user2);

        $ws1 = new Workspace(new WorkspaceId('ws-1'), 'Workspace 1', 'workspace-1');
        $ws1->addMember(new UserId('user-1'), MembershipRole::OWNER);
        $wsRepo->save($ws1);

        $ws2 = new Workspace(new WorkspaceId('ws-2'), 'Workspace 2', 'workspace-2');
        $ws2->addMember(new UserId('user-2'), MembershipRole::OWNER);
        $wsRepo->save($ws2);
    }

    #[Test]
    public function unauthenticated_requests_return_401(): void
    {
        $this->getJson('/api/v1/imports')->assertStatus(401);
        $this->postJson('/api/v1/imports')->assertStatus(401);
    }

    #[Test]
    public function uploads_valid_csv_file_returns_202(): void
    {
        $file = UploadedFile::fake()->createWithContent('sales.csv', "order_number,order_date,channel_code,region_code,warehouse_code,sku,quantity,unit_price,unit_cost,order_status\nORD-1,2026-03-01,online,RU-MSK,WH-01,SKU-A,1,10.0,5.0,completed\n");

        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->post('/api/v1/imports', [
            'file' => $file,
            'dataset_type' => 'sales',
        ]);

        $response->assertStatus(202);
        $response->assertJsonStructure([
            'batch' => [
                'id',
                'workspace_id',
                'dataset_type',
                'source_format',
                'original_filename',
                'status',
                'total_rows',
                'processed_rows',
                'successful_rows',
                'failed_rows',
                'progress_percentage',
                'created_at',
            ],
        ]);
        self::assertContains($response->json('batch.status'), ['pending', 'completed']);
    }

    #[Test]
    public function lists_workspace_batches(): void
    {
        $batch = ImportBatch::create(ImportBatchId::generate(), 'ws-1', DatasetType::SALES, SourceFormat::CSV, 's.csv', 'p');
        $this->batchRepo->save($batch);

        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/imports');

        $response->assertOk();
        $response->assertJsonStructure([
            'items',
            'total',
            'page',
            'per_page',
            'total_pages',
        ]);
        self::assertSame(1, $response->json('total'));
    }

    #[Test]
    public function prevents_cross_workspace_access_with_403_or_404(): void
    {
        $batchWs2 = ImportBatch::create(ImportBatchId::generate(), 'ws-2', DatasetType::SALES, SourceFormat::CSV, 's.csv', 'p');
        $this->batchRepo->save($batchWs2);

        // User 1 requests ws-2 -> 403 Forbidden
        $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-2',
        ])->getJson("/api/v1/imports/{$batchWs2->id()->toString()}")
            ->assertStatus(403);

        // User 1 in ws-1 requests batch from ws-2 -> 404 Not Found
        $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/imports/{$batchWs2->id()->toString()}")
            ->assertStatus(404);
    }

    #[Test]
    public function retrying_non_failed_batch_returns_409_conflict(): void
    {
        $batch = ImportBatch::create(ImportBatchId::generate(), 'ws-1', DatasetType::SALES, SourceFormat::CSV, 's.csv', 'p');
        $this->batchRepo->save($batch);

        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson("/api/v1/imports/{$batch->id()->toString()}/retry");

        $response->assertStatus(409);
        $response->assertJsonPath('code', 'CONFLICT');
    }
}
