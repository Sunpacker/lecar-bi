<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Commands;

final readonly class ResolveAlertCommand
{
    public function __construct(
        public string $workspaceId,
        public string $alertId,
        public string $userId,
        public ?string $resolutionNote = null,
    ) {}
}
