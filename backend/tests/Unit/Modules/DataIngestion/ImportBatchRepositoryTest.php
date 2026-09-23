<?php

namespace Tests\Unit\Modules\DataIngestion;

use App\Modules\DataIngestion\Domain\DatasetType;
use App\Modules\DataIngestion\Domain\ImportBatch;
use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\ImportStatus;
use App\Modules\DataIngestion\Domain\SourceFormat;
use App\Modules\DataIngestion\Infrastructure\Repositories\EloquentImportBatchRepository;
use App\Modules\DataIngestion\Infrastructure\Repositories\InMemoryImportBatchRepository;
use App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Models\WorkspaceModel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ImportBatchRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private WorkspaceModel $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = WorkspaceModel::factory()->create();
    }

    public function test_in_memory_repository_can_save_and_find_batch()
    {
        $repository = new InMemoryImportBatchRepository;
        $this->runSaveAndFindTest($repository);
    }

    public function test_eloquent_repository_can_save_and_find_batch()
    {
        $repository = new EloquentImportBatchRepository;
        $this->runSaveAndFindTest($repository);
    }

    private function runSaveAndFindTest($repository)
    {
        $batchId = ImportBatchId::generate();
        $batch = ImportBatch::create(
            $batchId,
            $this->workspace->id,
            DatasetType::SALES,
            SourceFormat::CSV,
            'sales.csv',
            'path/to/sales.csv'
        );

        $repository->save($batch);

        $found = $repository->findById($batchId, $this->workspace->id);
        $this->assertNotNull($found);
        $this->assertEquals($batchId->toString(), $found->id()->toString());
        $this->assertEquals(ImportStatus::PENDING, $found->status());

        $batch->startProcessing(100);
        $batch->recordProgress(100, 90, 10);
        $batch->markCompletedWithErrors();

        $repository->save($batch);

        $updated = $repository->findById($batchId, $this->workspace->id);
        $this->assertEquals(ImportStatus::COMPLETED_WITH_ERRORS, $updated->status());
        $this->assertEquals(100, $updated->totalRows());
        $this->assertEquals(90, $updated->successfulRows());
        $this->assertEquals(10, $updated->failedRows());

        $batches = $repository->listByWorkspace($this->workspace->id, null, 1, 10);
        $this->assertCount(1, $batches);
        $this->assertEquals($batchId->toString(), $batches[0]->id()->toString());

        $count = $repository->countByWorkspace($this->workspace->id);
        $this->assertEquals(1, $count);
    }
}
