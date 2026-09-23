<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Contracts;

use App\Modules\Alerting\Application\Dtos\InventoryCandidateDto;

interface InventoryAlertSourceInterface
{
    /**
     * @return list<InventoryCandidateDto>
     */
    public function getInventoryCandidates(string $workspaceId, ?string $warehouseId = null): array;
}
