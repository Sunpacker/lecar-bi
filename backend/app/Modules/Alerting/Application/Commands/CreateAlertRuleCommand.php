<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Commands;

final readonly class CreateAlertRuleCommand
{
    public function __construct(
        public string $workspaceId,
        public string $name,
        public ?string $description,
        public string $ruleType,
        public string $severity,
        public string $metric,
        public string $comparator,
        public float $thresholdValue,
        public ?string $warehouseId = null,
        public ?string $categoryId = null,
        public ?string $productId = null,
        public bool $isEnabled = true,
        public ?string $id = null,
    ) {}
}
