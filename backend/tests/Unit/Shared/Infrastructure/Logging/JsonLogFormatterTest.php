<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Logging;

use App\Shared\Infrastructure\Logging\JsonLogFormatter;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\TestCase;

final class JsonLogFormatterTest extends TestCase
{
    private JsonLogFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new JsonLogFormatter(serviceName: 'analytics');
    }

    public function test_formats_record_as_valid_json_with_standard_schema(): void
    {
        $record = new LogRecord(
            datetime: new DateTimeImmutable('2026-09-25T08:30:00.123456+00:00'),
            channel: 'stderr',
            level: Level::Info,
            message: 'Sales overview retrieved',
            context: ['workspace_id' => 'ws-123'],
            extra: [
                'request_id' => 'req-456',
                'correlation_id' => 'corr-789',
                'operation' => 'GET api/v1/sales/overview',
            ]
        );

        $output = $this->formatter->format($record);
        $this->assertStringEndsWith("\n", $output);

        $decoded = json_decode(trim($output), true);
        $this->assertIsArray($decoded);

        $this->assertSame('2026-09-25T08:30:00.123Z', $decoded['timestamp']);
        $this->assertSame('INFO', $decoded['level']);
        $this->assertSame('analytics', $decoded['service']);
        $this->assertSame('Sales overview retrieved', $decoded['message']);
        $this->assertSame('GET api/v1/sales/overview', $decoded['operation']);
        $this->assertSame('req-456', $decoded['request_id']);
        $this->assertSame('corr-789', $decoded['correlation_id']);
        $this->assertSame('ws-123', $decoded['context']['workspace_id']);
    }

    public function test_redacts_sensitive_keys_in_context(): void
    {
        $record = new LogRecord(
            datetime: new DateTimeImmutable('2026-09-25T08:30:00+00:00'),
            channel: 'stderr',
            level: Level::Warning,
            message: 'Auth failure',
            context: [
                'password' => 'secret_password',
                'token' => 'bearer_token_xyz',
                'authorization' => 'Bearer token_xyz',
                'api_key' => 'key_secret',
                'safe_field' => 'visible',
            ]
        );

        $output = $this->formatter->format($record);
        $decoded = json_decode(trim($output), true);

        $this->assertSame('[REDACTED]', $decoded['context']['password']);
        $this->assertSame('[REDACTED]', $decoded['context']['token']);
        $this->assertSame('[REDACTED]', $decoded['context']['authorization']);
        $this->assertSame('[REDACTED]', $decoded['context']['api_key']);
        $this->assertSame('visible', $decoded['context']['safe_field']);
    }
}
