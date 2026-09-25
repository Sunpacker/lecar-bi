<?php

declare(strict_types=1);

namespace NotificationService\Shared\Infrastructure\Logging;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Facade;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\LogRecord;

final class JsonLogFormatter extends NormalizerFormatter
{
    private const SENSITIVE_PATTERN = '/^(.*_)?(password|token|secret|authorization|auth|cookie|credential)(_.*)?$|^(.*_)?(api_?key|secret_?key|private_?key|auth_?key|access_?key|^key)$/i';

    public function __construct(
        private readonly string $serviceName = 'notification',
        ?string $dateFormat = 'Y-m-d\TH:i:s.v\Z'
    ) {
        parent::__construct($dateFormat);
    }

    public function format(LogRecord $record): string
    {
        $extra = $record->extra;
        $context = $record->context;
        $contextAll = $this->getContextAll();

        $requestId = $extra['request_id'] ?? $context['request_id'] ?? $contextAll['request_id'] ?? null;
        $correlationId = $extra['correlation_id'] ?? $context['correlation_id'] ?? $contextAll['correlation_id'] ?? $requestId;
        $operation = $extra['operation'] ?? $context['operation'] ?? $contextAll['operation'] ?? null;
        $eventId = $extra['event_id'] ?? $context['event_id'] ?? $contextAll['event_id'] ?? null;
        $jobId = $extra['job_id'] ?? $context['job_id'] ?? $contextAll['job_id'] ?? null;
        $errorCode = $extra['error_code'] ?? $context['error_code'] ?? null;

        if (isset($context['exception']) && is_array($context['exception']) && $errorCode === null) {
            $errorCode = $context['exception']['class'] ?? null;
        }

        unset(
            $context['request_id'],
            $context['correlation_id'],
            $context['operation'],
            $context['event_id'],
            $context['job_id'],
            $context['error_code']
        );

        foreach ($contextAll as $key => $value) {
            if (! isset($context[$key]) && ! in_array($key, ['request_id', 'correlation_id', 'operation', 'event_id', 'job_id'], true)) {
                $context[$key] = $value;
            }
        }

        $sanitizedContext = $this->sanitizeData($context);

        $entry = [
            'timestamp' => $record->datetime->format('Y-m-d\TH:i:s.v\Z'),
            'level' => $record->level->getName(),
            'service' => $this->serviceName,
            'environment' => config('app.env', 'testing'),
            'operation' => $operation !== null ? (string) $operation : null,
            'request_id' => $requestId !== null ? (string) $requestId : null,
            'correlation_id' => $correlationId !== null ? (string) $correlationId : null,
            'event_id' => $eventId !== null ? (string) $eventId : null,
            'job_id' => $jobId !== null ? (string) $jobId : null,
            'error_code' => $errorCode !== null ? (string) $errorCode : null,
            'message' => $record->message,
            'context' => $sanitizedContext,
        ];

        return json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function getContextAll(): array
    {
        if (
            class_exists(Context::class)
            && Facade::getFacadeApplication() !== null
            && Facade::getFacadeApplication()->bound(Dispatcher::class)
        ) {
            return Context::all();
        }

        return [];
    }

    private function sanitizeData(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_PATTERN, $key) === 1) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
