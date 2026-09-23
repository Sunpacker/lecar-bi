<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Dtos;

use App\Modules\Alerting\Domain\AlertRule;

final readonly class AlertRuleDto
{
    public function __construct(
        public string $id,
        public string $workspaceId,
        public string $name,
        public ?string $description,
        public string $ruleType,
        public string $severity,
        public string $metric,
        public string $comparator,
        public float $thresholdValue,
        public ?string $warehouseId,
        public ?string $categoryId,
        public ?string $productId,
        public bool $isEnabled,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromDomain(AlertRule $rule): self
    {
        return new self(
            id: $rule->id()->value(),
            workspaceId: $rule->workspaceId(),
            name: $rule->name(),
            description: $rule->description(),
            ruleType: $rule->ruleType()->value,
            severity: $rule->severity()->value,
            metric: $rule->condition()->metric()->value,
            comparator: $rule->condition()->comparator()->value,
            thresholdValue: $rule->condition()->thresholdValue(),
            warehouseId: $rule->scope()->warehouseId(),
            categoryId: $rule->scope()->categoryId(),
            productId: $rule->scope()->productId(),
            isEnabled: $rule->isEnabled(),
            createdAt: $rule->createdAt()->format('Y-m-d\TH:i:s\Z'),
            updatedAt: $rule->updatedAt()->format('Y-m-d\TH:i:s\Z'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspaceId,
            'name' => $this->name,
            'description' => $this->description,
            'rule_type' => $this->ruleType,
            'severity' => $this->severity,
            'metric' => $this->metric,
            'comparator' => $this->comparator,
            'threshold_value' => $this->thresholdValue,
            'warehouse_id' => $this->warehouseId,
            'category_id' => $this->categoryId,
            'product_id' => $this->productId,
            'is_enabled' => $this->isEnabled,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
