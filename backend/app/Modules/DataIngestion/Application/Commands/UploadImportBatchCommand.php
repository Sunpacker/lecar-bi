<?php

namespace App\Modules\DataIngestion\Application\Commands;

final readonly class UploadImportBatchCommand
{
    public function __construct(
        public string $workspaceId,
        public string $datasetType,
        public string $sourceFormat,
        public string $originalFilename,
        public string $storedFilePath,
    ) {}
}
