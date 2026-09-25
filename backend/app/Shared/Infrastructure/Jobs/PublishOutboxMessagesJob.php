<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Jobs;

use App\Shared\Application\IntegrationEvent;
use App\Shared\Application\Ports\IntegrationEventTransportInterface;
use App\Shared\Application\Ports\OutboxRepositoryInterface;
use App\Shared\Infrastructure\Health\OutboxHealthService;
use App\Shared\Infrastructure\Persistence\Eloquent\Repositories\EloquentOutboxRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Publishes a batch of pending outbox messages to the integration event transport.
 *
 * This job is unique per queue — only one instance runs at a time.
 * It is dispatched every minute via the scheduler.
 *
 * Delivery semantics: at-least-once. If the process crashes after XADD but before
 * markPublished(), the same event will be re-published on the next run with the same event_id.
 */
final class PublishOutboxMessagesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct()
    {
        $this->onQueue('outbox');
    }

    public function handle(
        OutboxRepositoryInterface $outboxRepository,
        IntegrationEventTransportInterface $transport,
    ): void {
        Context::flush();
        $jobId = $this->job?->getJobId() ?? Str::uuid()->toString();
        Context::add([
            'job_id' => (string) $jobId,
            'operation' => 'PublishOutboxMessagesJob',
        ]);

        try {
            OutboxHealthService::recordPublisherHeartbeat();
            $batchSize = (int) config('outbox.batch_size', 100);
            $messages = $outboxRepository->claimPendingBatch($batchSize);

            if (count($messages) === 0) {
                return;
            }

            foreach ($messages as $message) {
                $eventId = (string) $message['id'];
                $envelope = is_array($message['envelope'])
                    ? $message['envelope']
                    : json_decode((string) $message['envelope'], true, 512, JSON_THROW_ON_ERROR);
                $correlationId = isset($envelope['correlation_id']) && is_string($envelope['correlation_id'])
                    ? $envelope['correlation_id']
                    : null;

                try {
                    $integrationEvent = $this->toIntegrationEvent($message);
                    $transportMessageId = $transport->publish($integrationEvent);
                    $outboxRepository->markPublished($eventId, $transportMessageId);

                    Log::info('Outbox message published', [
                        'event_id' => $eventId,
                        'correlation_id' => $correlationId,
                        'event_type' => $message['event_type'] ?? 'unknown',
                        'outcome' => 'published',
                    ]);
                } catch (\Throwable $e) {
                    $currentAttempts = (int) ($message['attempt_count'] ?? 0);
                    $nextAttemptAt = EloquentOutboxRepository::nextRetryAt($currentAttempts);
                    $sanitizedError = $this->sanitizeError($e->getMessage());

                    $outboxRepository->scheduleRetry($eventId, $nextAttemptAt, $sanitizedError);

                    // Log only error type and sanitized message — no payload, no credentials.
                    Log::warning('Outbox publish failed', [
                        'event_id' => $eventId,
                        'correlation_id' => $correlationId,
                        'event_type' => $message['event_type'] ?? 'unknown',
                        'attempt' => $currentAttempts + 1,
                        'next_attempt_at' => $nextAttemptAt->format('Y-m-d H:i:s'),
                        'error' => $sanitizedError,
                        'outcome' => 'retry_scheduled',
                    ]);
                }
            }
        } finally {
            Context::flush();
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function toIntegrationEvent(array $message): IntegrationEvent
    {
        $envelope = is_array($message['envelope'])
            ? $message['envelope']
            : json_decode((string) $message['envelope'], true, 512, JSON_THROW_ON_ERROR);

        return new IntegrationEvent(
            eventId: (string) $message['id'],
            eventType: (string) $message['event_type'],
            eventVersion: (int) $message['event_version'],
            occurredAt: (string) ($envelope['occurred_at'] ?? $message['occurred_at']),
            producer: (string) $message['producer'],
            workspaceId: (string) $message['workspace_id'],
            aggregate: [
                'type' => (string) $message['aggregate_type'],
                'id' => (string) $message['aggregate_id'],
            ],
            payload: (array) ($envelope['payload'] ?? []),
            correlationId: isset($envelope['correlation_id']) && is_string($envelope['correlation_id']) ? $envelope['correlation_id'] : null,
        );
    }

    private function sanitizeError(string $error): string
    {
        // Truncate and remove any potential credential-like patterns.
        return mb_substr(preg_replace('/\b(password|secret|token|key|auth)\s*=\s*\S+/i', '[REDACTED]', $error) ?? $error, 0, 500);
    }
}
