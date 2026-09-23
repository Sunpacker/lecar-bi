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

        // List with negative page
        $negativeBatches = $repository->listByWorkspace($this->workspaceId, null, -1, 10);
        $this->assertCount(1, $negativeBatches);

        // Delete
        $repository->delete($batchId, $this->workspaceId);
        $this->assertNull($repository->findById($batchId, $this->workspaceId));
        $this->assertEquals(0, $repository->countByWorkspace($this->workspaceId));
    }

    public function test_in_memory_failure_repository_crud()
    {
        $repository = new InMemoryImportFailureRepository;
        $batchId = ImportBatchId::generate();

        // Create with '0' as field and value
        $errors = [
            new RowError(1, '0', '0', 'msg 1'),
            new RowError(2, 'field_2', 'val2', 'msg 2'),
        ];
        $repository->recordFailures($batchId, $this->workspaceId, $errors);

        // List
        $list = $repository->listByBatchId($batchId, $this->workspaceId, 1, 10);
        $this->assertCount(2, $list);
        $this->assertEquals(1, $list[0]->rowNumber);
        $this->assertEquals('0', $list[0]->field);
        $this->assertEquals('0', $list[0]->value);

        // List with negative page (should not fail, defaults to offset 0)
        $listNeg = $repository->listByBatchId($batchId, $this->workspaceId, -1, 10);
        $this->assertCount(2, $listNeg);

        // Count
        $this->assertEquals(2, $repository->countByBatchId($batchId, $this->workspaceId));

        // Delete
        $repository->deleteByBatchId($batchId, $this->workspaceId);
        $this->assertEquals(0, $repository->countByBatchId($batchId, $this->workspaceId));
        $this->assertCount(0, $repository->listByBatchId($batchId, $this->workspaceId, 1, 10));
    }

    public function test_failure_repository_preserves_zero_string_field(): void
    {
        $repo = new InMemoryImportFailureRepository;
        $batchId = ImportBatchId::fromString('batch-1');
        $failure = new RowError(rowNumber: 1, field: '0', value: '0', message: 'err');
        $repo->recordFailures($batchId, 'ws-1', [$failure]);
        $results = $repo->listByBatchId($batchId, 'ws-1', 1, 10);
        $this->assertCount(1, $results);
        $this->assertSame('0', $results[0]->field);
        $this->assertSame('0', $results[0]->value);
    }

    public function test_list_by_workspace_with_page_zero_returns_results(): void
    {
        $repo = new InMemoryImportBatchRepository;
        $batch = ImportBatch::create(
            ImportBatchId::generate(),
            'ws-1',
            DatasetType::SALES,
            SourceFormat::CSV,
            'file.csv',
            'path/file.csv'
        );
        $repo->save($batch);
        $results = $repo->listByWorkspace('ws-1', null, 0, 10);
        $this->assertIsArray($results);
    }

    public function test_delete_removes_batch(): void
    {
        $repo = new InMemoryImportBatchRepository;
        $batch = ImportBatch::create(
            ImportBatchId::generate(),
            'ws-1',
            DatasetType::SALES,
            SourceFormat::CSV,
            'file.csv',
            'path/file.csv'
        );
        $repo->save($batch);
        $repo->delete($batch->id(), 'ws-1');
        $this->assertNull($repo->findById($batch->id(), 'ws-1'));
    }
}
