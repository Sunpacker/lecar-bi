<?php

namespace App\Modules\DataIngestion\Application\Queries;

final readonly class GetImportFailuresQuery
{
    public function __construct(
        public string $workspaceId,
        public string $batchId,
        public int $page = 1,
        public int $perPage = 50,
    ) {}
}
