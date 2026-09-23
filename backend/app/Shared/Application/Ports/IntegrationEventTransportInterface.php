<?php

declare(strict_types=1);

namespace App\Shared\Application\Ports;

use App\Shared\Application\IntegrationEvent;

interface IntegrationEventTransportInterface
{
    /**
     * Publishes the integration event to the transport layer (e.g. Redis Stream).
     *
     * Returns the transport-specific message ID (e.g. Redis stream entry ID "1234567890123-0").
     * The caller is responsible for persisting this ID via OutboxRepositoryInterface::markPublished().
     *
     * @throws \RuntimeException on transport failure
     */
    public function publish(IntegrationEvent $event): string;
}
