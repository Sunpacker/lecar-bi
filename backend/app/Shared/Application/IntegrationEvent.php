<?php

declare(strict_types=1);

namespace App\Shared\Application;

/**
 * Integration Event — versioned external contract for inter-service communication.
 * This is NOT a Domain Event. Domain Events are mapped to Integration Events by Application-layer mappers.
 *
 * The envelope follows the alert-triggered.v1.schema.json contract.
 *
 * @phpstan-type AnalyticalContext array{
 *     target: string,
 *     product_id: string|null,
 *     product_name: string|null,
 *     product_sku: string|null,
 *     warehouse_id: string|null,
 *     warehouse_name: string|null,
 * }
 * @phpstan-type AggregateRef array{type: string, id: string}
 */
final class IntegrationEvent
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array{type: string, id: string}  $aggregate
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly int $eventVersion,
        public readonly string $occurredAt,
        public readonly string $producer,
        public readonly string $workspaceId,
        public readonly array $aggregate,
        public readonly array $payload,
    ) {}

    /** @return array<string, mixed> */
    public function toEnvelope(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'event_version' => $this->eventVersion,
            'occurred_at' => $this->occurredAt,
            'producer' => $this->producer,
            'workspace_id' => $this->workspaceId,
            'aggregate' => $this->aggregate,
            'payload' => $this->payload,
        ];
    }
}
