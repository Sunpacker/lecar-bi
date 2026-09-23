<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Application\Queries;

final readonly class GetSupplierFilterOptionsQuery
{
    public function __construct(
        public string $workspaceId,
    ) {}
}
