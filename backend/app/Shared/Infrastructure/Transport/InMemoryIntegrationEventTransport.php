<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Transport;

use App\Shared\Application\IntegrationEvent;
use App\Shared\Application\Ports\IntegrationEventTransportInterface;

/**
 * In-memory transport stub for unit tests.
 * Records all published events for assertion; never calls Redis.
 */
final class InMemoryIntegrationEventTransport implements IntegrationEventTransportInterface
{
    /** @var list<IntegrationEvent> */
    private array $published = [];

    private bool $shouldFail = false;

    private string $failMessage = 'Simulated transport failure';

    public function publish(IntegrationEvent $event): string
    {
        if ($this->shouldFail) {
            throw new \RuntimeException($this->failMessage);
        }

        $this->published[] = $event;

        // Return a deterministic fake stream ID
        return '1000000000000-0';
    }

    public function failOnNextCall(string $message = 'Simulated transport failure'): void
    {
        $this->shouldFail = true;
        $this->failMessage = $message;
    }

    public function resetFailure(): void
    {
        $this->shouldFail = false;
    }

    /** @return list<IntegrationEvent> */
    public function published(): array
    {
        return $this->published;
    }

    public function publishedCount(): int
    {
        return count($this->published);
    }
}
