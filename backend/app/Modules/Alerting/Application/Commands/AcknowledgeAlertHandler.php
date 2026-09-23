<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Commands;

use App\Modules\Alerting\Application\Dtos\AlertDto;
use App\Modules\Alerting\Domain\AlertId;
use App\Modules\Alerting\Domain\Exceptions\AlertNotFoundException;
use App\Modules\Alerting\Domain\Repositories\AlertRepositoryInterface;

final class AcknowledgeAlertHandler
{
    public function __construct(
        private AlertRepositoryInterface $alertRepository,
    ) {}

    public function handle(AcknowledgeAlertCommand $command): AlertDto
    {
        $id = new AlertId($command->alertId);
        $alert = $this->alertRepository->findById($command->workspaceId, $id);
        if ($alert === null) {
            throw AlertNotFoundException::withId($command->alertId);
        }

        $alert->acknowledge($command->userId);
        $this->alertRepository->save($alert);

        return AlertDto::fromDomain($alert);
    }
}
