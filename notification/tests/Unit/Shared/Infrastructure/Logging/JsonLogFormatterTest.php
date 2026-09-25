<?php

declare(strict_types=1);

namespace NotificationService\Tests\Unit\Shared\Infrastructure\Logging;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use NotificationService\Shared\Infrastructure\Logging\JsonLogFormatter;
use NotificationService\Tests\TestCase;

final class JsonLogFormatterTest extends TestCase
{
    public function test_formats_json_with_notification_service_name(): void
    {
        $formatter = new JsonLogFormatter;
        $record = new LogRecord(
            datetime: new DateTimeImmutable('2026-09-25T08:30:00+00:00'),
            channel: 'stderr',
            level: Level::Info,
            message: 'Consumer started',
            context: ['stream' => 'autobi.integration-events'],
            extra: ['operation' => 'notifications:consume']
        );

        $output = $formatter->format($record);
        $decoded = json_decode(trim($output), true);

        $this->assertSame('notification', $decoded['service']);
        $this->assertSame('Consumer started', $decoded['message']);
        $this->assertSame('notifications:consume', $decoded['operation']);
    }

    public function test_redacts_sensitive_keys_in_context(): void
    {
        $formatter = new JsonLogFormatter;
        $record = new LogRecord(
            datetime: new DateTimeImmutable('2026-09-25T08:30:00+00:00'),
            channel: 'stderr',
            level: Level::Warning,
            message: 'Token issue',
            context: [
                'token' => 'secret_token',
                'password' => 'secret_pass',
                'ok_key' => 'ok_value',
            ]
        );

        $output = $formatter->format($record);
        $decoded = json_decode(trim($output), true);

        $this->assertSame('[REDACTED]', $decoded['context']['token']);
        $this->assertSame('[REDACTED]', $decoded['context']['password']);
        $this->assertSame('ok_value', $decoded['context']['ok_key']);
    }
}
