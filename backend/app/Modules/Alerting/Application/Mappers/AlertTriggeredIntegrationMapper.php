<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Application\Mappers;

use App\Modules\Alerting\Domain\Events\AlertTriggered;
use App\Shared\Application\IntegrationEvent;

/**
 * Maps the AlertTriggered domain event to an IntegrationEvent envelope
 * conforming to contracts/events/alert-triggered.v1.schema.json.
 */
final class AlertTriggeredIntegrationMapper
{
    public function map(AlertTriggered $event): IntegrationEvent
    {
        return new IntegrationEvent(
            eventId: $event->eventId()->value(),
            eventType: 'alert.triggered',
            eventVersion: 1,
            occurredAt: $event->occurredAt()->format(\DateTimeInterface::ATOM),
            producer: 'analytics',
            workspaceId: $event->workspaceId(),
            aggregate: [
                'type' => 'alert',
                'id' => $event->alertId()->value(),
            ],
            payload: [
                'rule_id' => $event->ruleId()?->value(),
                'rule_name' => $event->ruleName(),
                'severity' => $event->severity()->value,
                'metric' => $event->metric()->value,
                'comparator' => $event->comparator()->value,
                'current_value' => $event->currentValue(),
                'threshold_value' => $event->thresholdValue(),
                'analytical_context' => [
                    'target' => $event->context()->target(),
                    'product_id' => $event->context()->productId(),
                    'product_name' => $event->context()->productName(),
                    'product_sku' => $event->context()->productSku(),
                    'warehouse_id' => $event->context()->warehouseId(),
                    'warehouse_name' => $event->context()->warehouseName(),
                ],
            ],
        );
    }
}
