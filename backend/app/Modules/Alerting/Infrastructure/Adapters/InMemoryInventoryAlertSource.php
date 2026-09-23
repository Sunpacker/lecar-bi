<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Infrastructure\Adapters;

use App\Modules\Alerting\Application\Contracts\InventoryAlertSourceInterface;
use App\Modules\Alerting\Application\Dtos\InventoryCandidateDto;

final class InMemoryInventoryAlertSource implements InventoryAlertSourceInterface
{
    /** @var array<string, list<InventoryCandidateDto>> key is workspaceId */
    private array $candidates = [];

    /**
     * @param  list<InventoryCandidateDto>  $candidates
     */
    public function setCandidates(string $workspaceId, array $candidates): void
    {
        $this->candidates[$workspaceId] = $candidates;
    }

    /**
     * @return list<InventoryCandidateDto>
     */
    public function getInventoryCandidates(string $workspaceId, ?string $warehouseId = null): array
    {
        $items = $this->candidates[$workspaceId] ?? [];

        if ($warehouseId === null || $warehouseId === '') {
            return $items;
        }

        return array_values(array_filter(
            $items,
            fn (InventoryCandidateDto $dto) => $dto->warehouseId === $warehouseId
        ));
    }
}
