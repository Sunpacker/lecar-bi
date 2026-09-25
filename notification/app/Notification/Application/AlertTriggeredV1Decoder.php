<?php

declare(strict_types=1);

namespace NotificationService\Notification\Application;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use NotificationService\Notification\Domain\NotificationSeverity;

final class AlertTriggeredV1Decoder
{
    /**
     * @param  array<string, mixed>|string  $raw
     *
     * @throws InvalidArgumentException
     */
    public function decode(array|string $raw): AlertTriggeredV1
    {
        if (is_string($raw)) {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new InvalidArgumentException('Malformed JSON envelope: '.$e->getMessage(), 0, $e);
            }

            if (! is_array($decoded)) {
                throw new InvalidArgumentException('Decoded JSON is not an array structure');
            }

            $raw = $decoded;
        }

        // Validate top-level fields
        $eventId = $this->requireNonEmptyString($raw, 'event_id');

        $eventType = $this->requireNonEmptyString($raw, 'event_type');
        if ($eventType !== 'alert.triggered') {
            throw new InvalidArgumentException("Unsupported event_type: '{$eventType}', expected 'alert.triggered'");
        }

        $eventVersion = $raw['event_version'] ?? null;
        if (! is_int($eventVersion) && ! (is_string($eventVersion) && ctype_digit($eventVersion))) {
            throw new InvalidArgumentException('Invalid or missing event_version');
        }
        $eventVersion = (int) $eventVersion;
        if ($eventVersion !== 1) {
            throw new InvalidArgumentException("Unsupported event_version: {$eventVersion}, expected 1");
        }

        $occurredAtStr = $this->requireNonEmptyString($raw, 'occurred_at');
        $occurredAt = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $occurredAtStr);
        if ($occurredAt === false) {
            // Also try with microseconds if present
            $occurredAt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', $occurredAtStr);
        }
        if ($occurredAt === false) {
            // Fallback general parse
            try {
                $occurredAt = new DateTimeImmutable($occurredAtStr);
            } catch (\Exception $e) {
                throw new InvalidArgumentException("Invalid occurred_at timestamp: '{$occurredAtStr}'", 0, $e);
            }
        }

        $producer = $this->requireNonEmptyString($raw, 'producer');
        if ($producer !== 'analytics') {
            throw new InvalidArgumentException("Unsupported producer: '{$producer}', expected 'analytics'");
        }

        $workspaceId = $this->requireNonEmptyString($raw, 'workspace_id');

        // Validate aggregate
        $aggregate = $raw['aggregate'] ?? null;
        if (! is_array($aggregate)) {
            throw new InvalidArgumentException('Missing or invalid aggregate object');
        }
        $aggregateType = $this->requireNonEmptyString($aggregate, 'type');
        if ($aggregateType !== 'alert') {
            throw new InvalidArgumentException("Unsupported aggregate type: '{$aggregateType}', expected 'alert'");
        }
        $alertId = $this->requireNonEmptyString($aggregate, 'id');

        // Validate payload
        $payload = $raw['payload'] ?? null;
        if (is_string($payload)) {
            try {
                $payload = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new InvalidArgumentException('Malformed payload JSON: '.$e->getMessage(), 0, $e);
            }
        }
        if (! is_array($payload)) {
            throw new InvalidArgumentException('Missing or invalid payload object');
        }

        $ruleId = isset($payload['rule_id']) && is_string($payload['rule_id']) && trim($payload['rule_id']) !== ''
            ? trim($payload['rule_id'])
            : null;

        $ruleName = $this->requireNonEmptyString($payload, 'rule_name');
        $severityStr = $this->requireNonEmptyString($payload, 'severity');
        $severity = NotificationSeverity::fromString($severityStr);

        $metric = $this->requireNonEmptyString($payload, 'metric');
        $comparator = $this->requireNonEmptyString($payload, 'comparator');

        if (! isset($payload['current_value']) || ! is_numeric($payload['current_value'])) {
            throw new InvalidArgumentException('Missing or non-numeric current_value');
        }
        $currentValue = (float) $payload['current_value'];

        if (! isset($payload['threshold_value']) || ! is_numeric($payload['threshold_value'])) {
            throw new InvalidArgumentException('Missing or non-numeric threshold_value');
        }
        $thresholdValue = (float) $payload['threshold_value'];

        $context = $payload['analytical_context'] ?? null;
        if (! is_array($context)) {
            throw new InvalidArgumentException('Missing or invalid analytical_context object');
        }
        if (empty($context['target']) || ! is_string($context['target'])) {
            throw new InvalidArgumentException('Missing analytical_context.target');
        }

        /** @var array<string, mixed> $context */
        $correlationId = isset($raw['correlation_id']) && is_string($raw['correlation_id']) && trim($raw['correlation_id']) !== ''
            ? trim($raw['correlation_id'])
            : null;

        return new AlertTriggeredV1(
            eventId: $eventId,
            eventType: $eventType,
            eventVersion: $eventVersion,
            occurredAt: $occurredAt,
            producer: $producer,
            workspaceId: $workspaceId,
            alertId: $alertId,
            ruleId: $ruleId,
            ruleName: $ruleName,
            severity: $severity,
            metric: $metric,
            comparator: $comparator,
            currentValue: $currentValue,
            thresholdValue: $thresholdValue,
            analyticalContext: $context,
            correlationId: $correlationId,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requireNonEmptyString(array $data, string $key): string
    {
        if (! isset($data[$key]) || ! is_string($data[$key]) || trim($data[$key]) === '') {
            throw new InvalidArgumentException("Missing or empty string property: '{$key}'");
        }

        return trim($data[$key]);
    }
}
