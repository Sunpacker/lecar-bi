<?php

namespace App\Modules\DataIngestion\Application\Queries;

final readonly class GetImportBatchesQuery
{
    public function __construct(
        public string $workspaceId,
        public ?string $status = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {}
}
