<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Queries;

use App\Modules\Alerting\Application\Dtos\AlertDto;
use App\Modules\Alerting\Domain\AlertId;
use App\Modules\Alerting\Domain\Exceptions\AlertNotFoundException;
use App\Modules\Alerting\Domain\Repositories\AlertRepositoryInterface;

final class GetAlertByIdHandler
{
    public function __construct(
        private AlertRepositoryInterface $alertRepository,
    ) {}

    public function handle(GetAlertByIdQuery $query): AlertDto
    {
        $alert = $this->alertRepository->findById($query->workspaceId, new AlertId($query->id));
        if ($alert === null) {
            throw AlertNotFoundException::withId($query->id);
        }

        return AlertDto::fromDomain($alert);
    }
}
