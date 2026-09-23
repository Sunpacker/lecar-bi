<?php

declare(strict_types=1);

namespace NotificationService\Notification\Application;

use DateTimeImmutable;
use NotificationService\Notification\Application\Contracts\ConsumedEventRepository;
use NotificationService\Notification\Application\Contracts\NotificationRepository;
use NotificationService\Notification\Application\Contracts\TransactionManager;
use NotificationService\Notification\Domain\Notification;
use NotificationService\Notification\Domain\NotificationId;

final class ConsumeAlertTriggered
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly ConsumedEventRepository $consumedEvents,
        private readonly TransactionManager $transaction
    ) {}

    public function handle(
        AlertTriggeredV1 $event,
        string $streamMessageId,
        ?NotificationId $notificationId = null,
        ?DateTimeImmutable $now = null
    ): ConsumeResult {
        if ($this->consumedEvents->hasBeenProcessed($event->eventId)) {
            return ConsumeResult::duplicate($event->eventId);
        }

        $id = $notificationId ?? NotificationId::generate();
        $currentTime = $now ?? new DateTimeImmutable;

        $title = $event->ruleName;
        $body = sprintf(
            'Сработал алерт для правила "%s": %s %s %s (текущее значение: %s)',
            $event->ruleName,
            $event->metric,
            $event->comparator,
            $event->thresholdValue,
            $event->currentValue
        );

        $notification = new Notification(
            id: $id,
            sourceEventId: $event->eventId,
            workspaceId: $event->workspaceId,
            alertId: $event->alertId,
            ruleId: $event->ruleId,
            severity: $event->severity,
            title: $title,
            body: $body,
            analyticalContext: $event->analyticalContext,
            occurredAt: $event->occurredAt,
            createdAt: $currentTime
        );

        $this->transaction->transaction(function () use ($notification, $event, $streamMessageId, $currentTime): void {
            $this->notifications->save($notification);
            $this->consumedEvents->register(
                eventId: $event->eventId,
                streamMessageId: $streamMessageId,
                eventType: $event->eventType,
                eventVersion: $event->eventVersion,
                producer: $event->producer,
                workspaceId: $event->workspaceId,
                occurredAt: $event->occurredAt,
                processedAt: $currentTime
            );
        });

        return ConsumeResult::processed($notification);
    }
}
