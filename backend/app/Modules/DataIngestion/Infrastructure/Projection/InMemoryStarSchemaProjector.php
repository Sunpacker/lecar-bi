<?php

namespace App\Modules\DataIngestion\Infrastructure\Projection;

use App\Modules\DataIngestion\Application\Contracts\StarSchemaProjectorInterface;

final class InMemoryStarSchemaProjector implements StarSchemaProjectorInterface
{
    /** @var list<array{workspaceId: string, row: array<string, string>}> */
    public array $projectedSalesRows = [];

    /** @var list<array{workspaceId: string, row: array<string, string>}> */
    public array $projectedInventoryRows = [];

    public function projectSalesRow(string $workspaceId, array $row): void
    {
        $this->projectedSalesRows[] = ['workspaceId' => $workspaceId, 'row' => $row];
    }

    public function projectInventoryRow(string $workspaceId, array $row): void
    {
        $this->projectedInventoryRows[] = ['workspaceId' => $workspaceId, 'row' => $row];
    }
}
