<?php

namespace Tests\Unit\Modules\DataIngestion;

use App\Modules\DataIngestion\Domain\DatasetType;
use App\Modules\DataIngestion\Domain\ImportBatch;
use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\ImportStatus;
use App\Modules\DataIngestion\Domain\RowError;
use App\Modules\DataIngestion\Domain\SourceFormat;
use App\Modules\DataIngestion\Infrastructure\Repositories\InMemoryImportBatchRepository;
use App\Modules\DataIngestion\Infrastructure\Repositories\InMemoryImportFailureRepository;
use PHPUnit\Framework\TestCase;

class ImportBatchRepositoryTest extends TestCase
{
    private string $workspaceId = 'workspace-1';

    public function test_in_memory_batch_repository_crud()
    {
        $repository = new InMemoryImportBatchRepository;
        $batchId = ImportBatchId::generate();

        // Create
        $batch = ImportBatch::create(
            $batchId,
            $this->workspaceId,
            DatasetType::SALES,
            SourceFormat::CSV,
            'sales.csv',
            'path/to/sales.csv'
        );
        $repository->save($batch);

        // Read
        $found = $repository->findById($batchId, $this->workspaceId);
        $this->assertNotNull($found);
        $this->assertEquals($batchId->toString(), $found->id()->toString());
        $this->assertEquals(ImportStatus::PENDING, $found->status());

        // Update
        $batch->startProcessing(100);
        $batch->recordProgress(100, 90, 10);
        $batch->markCompletedWithErrors();
        $repository->save($batch);

        $updated = $repository->findById($batchId, $this->workspaceId);
        $this->assertEquals(ImportStatus::COMPLETED_WITH_ERRORS, $updated->status());
        $this->assertEquals(100, $updated->totalRows());
        $this->assertEquals(90, $updated->successfulRows());
        $this->assertEquals(10, $updated->failedRows());

        // List and Count
        $batches = $repository->listByWorkspace($this->workspaceId, null, 1, 10);
        $this->assertCount(1, $batches);

        $count = $repository->countByWorkspace($this->workspaceId);
        $this->assertEquals(1, $count);

        $emptyBatches = $repository->listByWorkspace($this->workspaceId, ImportStatus::FAILED, 1, 10);
        $this->assertCount(0, $emptyBatches);

        // Delete
        $repository->delete($batchId, $this->workspaceId);
        $this->assertNull($repository->findById($batchId, $this->workspaceId));
        $this->assertEquals(0, $repository->countByWorkspace($this->workspaceId));
    }

    public function test_in_memory_failure_repository_crud()
    {
        $repository = new InMemoryImportFailureRepository;
        $batchId = ImportBatchId::generate();

        // Create
        $errors = [
            new RowError(1, 'field_1', 'val', 'msg 1'),
            new RowError(2, 'field_2', 'val2', 'msg 2'),
        ];
        $repository->recordFailures($batchId, $this->workspaceId, $errors);

        // List
        $list = $repository->listByBatchId($batchId, $this->workspaceId, 1, 10);
        $this->assertCount(2, $list);
        $this->assertEquals(1, $list[0]->rowNumber);
        $this->assertEquals(2, $list[1]->rowNumber);

        // Count
        $this->assertEquals(2, $repository->countByBatchId($batchId, $this->workspaceId));

        // Delete
        $repository->deleteByBatchId($batchId, $this->workspaceId);
        $this->assertEquals(0, $repository->countByBatchId($batchId, $this->workspaceId));
        $this->assertCount(0, $repository->listByBatchId($batchId, $this->workspaceId, 1, 10));
    }
}
