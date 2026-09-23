<?php

declare(strict_types=1);

namespace NotificationService\Integration\Application;

use InvalidArgumentException;
use NotificationService\Notification\Application\AlertTriggeredV1Decoder;
use NotificationService\Notification\Application\ConsumeAlertTriggered;

final class IntegrationEventRouter implements IntegrationEventRouterInterface
{
    public function __construct(
        private readonly AlertTriggeredV1Decoder $decoder,
        private readonly ConsumeAlertTriggered $consumeAlertTriggered
    ) {}

    /**
     * @param  array<string, mixed>  $fields
     */
    public function route(string $streamMessageId, array $fields): RouteResult
    {
        $rawPayload = $fields['payload'] ?? $fields['event'] ?? null;
        $eventType = $fields['event_type'] ?? null;
        $eventId = isset($fields['event_id']) && is_string($fields['event_id']) ? $fields['event_id'] : null;

        // If event_type is present in message fields and is not handled by this service
        if (is_string($eventType) && $eventType !== 'alert.triggered') {
            return RouteResult::ignored("Unhandled event_type: '{$eventType}'");
        }

        $toDecode = $fields;
        if (is_string($rawPayload)) {
            $decodedJson = json_decode($rawPayload, true);
            if (is_array($decodedJson)) {
                if (isset($decodedJson['event_id'])) {
                    $toDecode = $decodedJson;
                } else {
                    $toDecode['payload'] = $decodedJson;
                }
            } else {
                $toDecode = $rawPayload;
            }
        }

        if (is_array($toDecode) && ! isset($toDecode['aggregate']) && isset($toDecode['aggregate_type'], $toDecode['aggregate_id'])) {
            $toDecode['aggregate'] = [
                'type' => $toDecode['aggregate_type'],
                'id' => $toDecode['aggregate_id'],
            ];
        }

        try {
            $event = $this->decoder->decode($toDecode);
        } catch (InvalidArgumentException $e) {
            return RouteResult::deadLetter(
                reason: $e->getMessage(),
                fields: $fields,
                eventId: $eventId
            );
        }

        $result = $this->consumeAlertTriggered->handle($event, $streamMessageId);

        if ($result->isDuplicate()) {
            return RouteResult::duplicate($event->eventId);
        }

        return RouteResult::processed($event->eventId);
    }
}
