<?php

namespace App\Modules\DataIngestion\Application\Contracts;

interface StarSchemaProjectorInterface
{
    /** @param array<string, string> $row */
    public function projectSalesRow(string $workspaceId, array $row): void;

    /** @param array<string, string> $row */
    public function projectInventoryRow(string $workspaceId, array $row): void;
}
