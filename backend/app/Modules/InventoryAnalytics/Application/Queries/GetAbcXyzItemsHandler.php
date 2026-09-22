<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Queries;

use App\Modules\InventoryAnalytics\Application\Contracts\InventoryAnalyticsReadModelInterface;
use App\Modules\InventoryAnalytics\Application\Dtos\AbcXyzProductItemsPaginatedDto;

final readonly class GetAbcXyzItemsHandler
{
    public function __construct(
        private InventoryAnalyticsReadModelInterface $readModel,
    ) {}

    public function handle(GetAbcXyzItemsQuery $query): AbcXyzProductItemsPaginatedDto
    {
        return $this->readModel->getAbcXyzItems($query->workspaceId, $query->criteria);
    }
}
