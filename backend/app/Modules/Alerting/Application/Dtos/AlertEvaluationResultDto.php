<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Dtos;

final readonly class AlertEvaluationResultDto
{
    public function __construct(
        public int $rulesEvaluated,
        public int $alertsTriggered,
        public int $alertsCreated,
        public int $alertsUpdated,
    ) {}
}
