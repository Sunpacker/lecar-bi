<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Dtos;

use App\Modules\Alerting\Domain\Alert;

final readonly class AlertDto
{
    /**
     * @param  array<string, mixed>  $contextData
     */
    public function __construct(
        public string $id,
        public string $workspaceId,
        public ?string $ruleId,
        public string $ruleName,
        public string $severity,
        public string $status,
        public string $dedupFingerprint,
        public ?string $productId,
        public ?string $productName,
        public ?string $productSku,
        public ?string $warehouseId,
        public ?string $warehouseName,
        public ?float $currentValue,
        public ?float $thresholdValue,
        public array $contextData,
        public string $triggeredAt,
        public ?string $acknowledgedAt,
        public ?string $acknowledgedBy,
        public ?string $resolvedAt,
        public ?string $resolvedBy,
        public ?string $resolutionNote,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromDomain(Alert $alert): self
    {
        return new self(
            id: $alert->id()->value(),
            workspaceId: $alert->workspaceId(),
            ruleId: $alert->ruleId()?->value(),
            ruleName: $alert->ruleName(),
            severity: $alert->severity()->value,
            status: $alert->status()->value,
            dedupFingerprint: $alert->dedupFingerprint()->value(),
            productId: $alert->context()->productId(),
            productName: $alert->context()->productName(),
            productSku: $alert->context()->productSku(),
            warehouseId: $alert->context()->warehouseId(),
            warehouseName: $alert->context()->warehouseName(),
            currentValue: $alert->context()->currentValue(),
            thresholdValue: $alert->context()->thresholdValue(),
            contextData: $alert->context()->toArray(),
            triggeredAt: $alert->triggeredAt()->format('Y-m-d\TH:i:s\Z'),
            acknowledgedAt: $alert->acknowledgedAt()?->format('Y-m-d\TH:i:s\Z'),
            acknowledgedBy: $alert->acknowledgedBy(),
            resolvedAt: $alert->resolvedAt()?->format('Y-m-d\TH:i:s\Z'),
            resolvedBy: $alert->resolvedBy(),
            resolutionNote: $alert->resolutionNote(),
            createdAt: $alert->createdAt()->format('Y-m-d\TH:i:s\Z'),
            updatedAt: $alert->updatedAt()->format('Y-m-d\TH:i:s\Z'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspaceId,
            'rule_id' => $this->ruleId,
            'rule_name' => $this->ruleName,
            'severity' => $this->severity,
            'status' => $this->status,
            'dedup_fingerprint' => $this->dedupFingerprint,
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'product_sku' => $this->productSku,
            'warehouse_id' => $this->warehouseId,
            'warehouse_name' => $this->warehouseName,
            'current_value' => $this->currentValue,
            'threshold_value' => $this->thresholdValue,
            'context_data' => $this->contextData,
            'triggered_at' => $this->triggeredAt,
            'acknowledged_at' => $this->acknowledgedAt,
            'acknowledged_by' => $this->acknowledgedBy,
            'resolved_at' => $this->resolvedAt,
            'resolved_by' => $this->resolvedBy,
            'resolution_note' => $this->resolutionNote,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
