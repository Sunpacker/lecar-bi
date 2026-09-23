<?php

declare(strict_types=1);

namespace App\Shared\Domain;

trait HasDomainEvents
{
    /** @var list<DomainEvent> */
    private array $domainEvents = [];

    protected function recordDomainEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    /**
     * Returns all recorded domain events and clears the internal list.
     *
     * @return list<DomainEvent>
     */
    public function releaseDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    /**
     * Returns recorded events without clearing them (for inspection).
     *
     * @return list<DomainEvent>
     */
    public function peekDomainEvents(): array
    {
        return $this->domainEvents;
    }
}
