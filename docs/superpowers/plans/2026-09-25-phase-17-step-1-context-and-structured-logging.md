# Phase 17 (Observability) — Step 1: Context Propagation & Structured Logging Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Обеспечить сквозную трассируемость запросов и событий между Next.js (frontend), Laravel (analytics) и Laravel (notification) через `request_id` / `correlation_id` и внедрить единый машиночитаемый JSON-формат логов без утечки чувствительных данных.

**Architecture:** На границе доверия HTTP-запросов (Next.js middleware и backend middleware) создаётся или валидируется `request_id` (UUID v4) и возвращается в заголовке `X-Request-Id`. При вызове analytics из Next.js заголовок `X-Request-Id` пробрасывается через openapi-fetch middleware. Для асинхронных задач и событий `correlation_id` связывает исходный HTTP-вызов с outbox и notification consumer через опциональное поле в event envelope. Все сервисы настраиваются на вывод однострочных JSON-логов с фиксированным набором полей (`timestamp`, `level`, `service`, `environment`, `operation`, `request_id`, `correlation_id`, `event_id`, `job_id`, `error_code`, `message`, `context`). В долгоживущих worker-процессах контекст очищается между задачами.

**Tech Stack:** PHP 8.3, Laravel 13, Monolog 3, Next.js 16 (App Router), TypeScript, openapi-fetch, JSON Schema (draft-07).

**Spec:** [docs/roadmap/17-observability.md](../../docs/roadmap/17-observability.md), [docs/architecture/09-infrastructure-deployment-observability.md](../../docs/architecture/09-infrastructure-deployment-observability.md)

## Global Constraints

- Domain Layer аналитики и уведомлений остаётся полностью независимым от инфраструктуры логирования и Laravel Context.
- `contracts/events/alert-triggered.v1.schema.json` расширяется строго обратно совместимо: `correlation_id` является опциональным (nullable string) и не ломает существующих потребителей.
- Никаких секретов, токенов авторизации, cookies, содержимого импорта или полных payload событий в логах: все поля контекста санируются (sanitized fail-safe).
- `request_id`, `event_id` и `trace_id` передаются как поля записи лога, а не высококардинальные Loki labels.
- Контекст `Context::flush()` обязан вызываться в начале и конце каждого цикла обработки в worker-процессах (`PublishOutboxMessagesJob` и `RedisStreamConsumer`) во избежание утечки идентификаторов между задачами.

---

### Task 1: Event Contract: Add optional `correlation_id` to `alert.triggered.v1`

**Files:**
- Modify: `contracts/events/alert-triggered.v1.schema.json:17-25`
- Test: `contracts/events/alert-triggered.v1.schema.json` (validate via redocly/json-schema tests)

**Interfaces:**
- Consumes: JSON Schema Draft-07
- Produces: `correlation_id` field definition in event envelope schema

- [ ] **Step 1: Write failing test / validation expectation**

Run contract validation before schema change to establish baseline:
```bash
npm --prefix frontend run contracts:validate
```
Expected: PASS.

- [ ] **Step 2: Add `correlation_id` to schema properties**

Update `contracts/events/alert-triggered.v1.schema.json` under `properties`:
```json
    "correlation_id": {
      "type": ["string", "null"],
      "description": "Optional correlation/request identifier from the originating HTTP request or workflow.",
      "minLength": 1
    },
```

- [ ] **Step 3: Run validation to verify schema is valid**

Run: `npm --prefix frontend run contracts:validate`
Expected: PASS (0 errors).

- [ ] **Step 4: Commit**

```bash
git add contracts/events/alert-triggered.v1.schema.json
git commit -m "contract(events): add optional correlation_id to alert.triggered.v1 schema"
```

---

### Task 2: Backend: TraceContextMiddleware for Analytics Service

**Files:**
- Create: `backend/app/Shared/Infrastructure/Http/Middleware/TraceContextMiddleware.php`
- Modify: `backend/bootstrap/app.php:36-41`
- Test: `backend/tests/Unit/Shared/Infrastructure/Http/Middleware/TraceContextMiddlewareTest.php`

**Interfaces:**
- Consumes: Incoming HTTP request headers (`X-Request-Id`, `X-Correlation-Id`)
- Produces: `Context::add('request_id', ...)`, `Context::add('correlation_id', ...)`, `Context::add('operation', ...)`, response headers `X-Request-Id`, `X-Correlation-Id`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Unit/Shared/Infrastructure/Http/Middleware/TraceContextMiddlewareTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Http\Middleware;

use App\Shared\Infrastructure\Http\Middleware\TraceContextMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;

final class TraceContextMiddlewareTest extends TestCase
{
    private TraceContextMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new TraceContextMiddleware();
        Context::flush();
    }

    public function test_generates_new_request_id_when_missing(): void
    {
        $request = Request::create('/api/v1/health', 'GET');

        $response = $this->middleware->handle($request, function ($req) {
            $requestId = Context::get('request_id');
            $this->assertNotNull($requestId);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $requestId);
            return new Response('ok');
        });

        $this->assertTrue($response->headers->has('X-Request-Id'));
        $this->assertSame(Context::get('request_id'), $response->headers->get('X-Request-Id'));
    }

    public function test_accepts_and_sanitizes_valid_incoming_request_id(): void
    {
        $request = Request::create('/api/v1/health', 'GET');
        $request->headers->set('X-Request-Id', 'client-req-12345');

        $response = $this->middleware->handle($request, function ($req) {
            $this->assertSame('client-req-12345', Context::get('request_id'));
            return new Response('ok');
        });

        $this->assertSame('client-req-12345', $response->headers->get('X-Request-Id'));
    }

    public function test_replaces_invalid_incoming_request_id_with_uuid(): void
    {
        $request = Request::create('/api/v1/health', 'GET');
        $request->headers->set('X-Request-Id', 'invalid<script>id!@#$%^&*()');

        $response = $this->middleware->handle($request, function ($req) {
            $requestId = Context::get('request_id');
            $this->assertNotSame('invalid<script>id!@#$%^&*()', $requestId);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $requestId);
            return new Response('ok');
        });

        $this->assertNotSame('invalid<script>id!@#$%^&*()', $response->headers->get('X-Request-Id'));
    }

    public function test_sets_operation_in_context(): void
    {
        $request = Request::create('/api/v1/health', 'GET');

        $this->middleware->handle($request, function ($req) {
            $this->assertSame('GET api/v1/health', Context::get('operation'));
            return new Response('ok');
        });
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=TraceContextMiddlewareTest`
Expected: FAIL with "Class 'App\Shared\Infrastructure\Http\Middleware\TraceContextMiddleware' not found".

- [ ] **Step 3: Implement `TraceContextMiddleware` and register in `bootstrap/app.php`**

Create `backend/app/Shared/Infrastructure/Http/Middleware/TraceContextMiddleware.php`:
```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class TraceContextMiddleware
{
    private const REQUEST_ID_REGEX = '/^[a-zA-Z0-9_\-\.]{1,64}$/';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rawRequestId = $request->header('X-Request-Id');
        if (is_string($rawRequestId) && preg_match(self::REQUEST_ID_REGEX, trim($rawRequestId)) === 1) {
            $requestId = trim($rawRequestId);
        } else {
            $requestId = Str::uuid()->toString();
        }

        $rawCorrelationId = $request->header('X-Correlation-Id');
        if (is_string($rawCorrelationId) && preg_match(self::REQUEST_ID_REGEX, trim($rawCorrelationId)) === 1) {
            $correlationId = trim($rawCorrelationId);
        } else {
            $correlationId = $requestId;
        }

        $operation = $request->method().' '.($request->route()?->uri() ?? ltrim($request->path(), '/'));

        Context::add([
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'operation' => $operation,
        ]);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);
        $response->headers->set('X-Correlation-Id', $correlationId);

        return $response;
    }
}
```

Register globally in `backend/bootstrap/app.php`:
```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Shared\Infrastructure\Http\Middleware\TraceContextMiddleware::class);
        $middleware->alias([
            'workspace.can' => RequireWorkspaceCapabilityMiddleware::class,
        ]);
    })
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter=TraceContextMiddlewareTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Shared/Infrastructure/Http/Middleware/TraceContextMiddleware.php backend/bootstrap/app.php backend/tests/Unit/Shared/Infrastructure/Http/Middleware/TraceContextMiddlewareTest.php
git commit -m "feat(analytics): add TraceContextMiddleware for request_id and correlation_id propagation"
```

---

### Task 3: Backend: Structured JSON Log Formatter

**Files:**
- Create: `backend/app/Shared/Infrastructure/Logging/JsonLogFormatter.php`
- Modify: `backend/config/logging.php:1-6`
- Test: `backend/tests/Unit/Shared/Infrastructure/Logging/JsonLogFormatterTest.php`

**Interfaces:**
- Consumes: Monolog `LogRecord` (with level, message, context, extra, datetime)
- Produces: Single-line JSON string with fields: `timestamp`, `level`, `service`, `environment`, `operation`, `request_id`, `correlation_id`, `event_id`, `job_id`, `error_code`, `message`, `context`

- [ ] **Step 1: Write the failing test**

Create `backend/tests/Unit/Shared/Infrastructure/Logging/JsonLogFormatterTest.php`:
```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=JsonLogFormatterTest`
Expected: FAIL with "Class 'App\Shared\Infrastructure\Logging\JsonLogFormatter' not found".

- [ ] **Step 3: Implement `JsonLogFormatter` and configure in `config/logging.php`**

Create `backend/app/Shared/Infrastructure/Logging/JsonLogFormatter.php`:
```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\LogRecord;

final class JsonLogFormatter extends NormalizerFormatter
{
    private const SENSITIVE_PATTERN = '/^(.*_)?(password|token|secret|authorization|auth|cookie|key|credential)(_.*)?$/i';

    public function __construct(
        private readonly string $serviceName = 'analytics',
        ?string $dateFormat = 'Y-m-d\TH:i:s.v\Z'
    ) {
        parent::__construct($dateFormat);
    }

    public function format(LogRecord $record): string
    {
        $normalized = $this->normalizeRecord($record);

        $extra = $record->extra;
        $context = $record->context;

        $requestId = $extra['request_id'] ?? $context['request_id'] ?? null;
        $correlationId = $extra['correlation_id'] ?? $context['correlation_id'] ?? $requestId;
        $operation = $extra['operation'] ?? $context['operation'] ?? null;
        $eventId = $extra['event_id'] ?? $context['event_id'] ?? null;
        $jobId = $extra['job_id'] ?? $context['job_id'] ?? null;
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
     * @param  mixed  $data
     * @return mixed
     */
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
```

Update `backend/config/logging.php`:
```php
<?php

use App\Shared\Infrastructure\Logging\JsonLogFormatter;
use Monolog\Handler\StreamHandler;

return [
    'default' => env('LOG_CHANNEL', 'stderr'),
    'channels' => [
        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => JsonLogFormatter::class,
            'with' => ['stream' => 'php://stderr'],
        ],
        'stack' => [
            'driver' => 'stack',
            'channels' => ['stderr'],
            'ignore_exceptions' => false,
        ],
    ],
];
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer --working-dir=backend test -- --filter=JsonLogFormatterTest`
Expected: PASS.

- [ ] **Step 5: Run full backend test suite to ensure existing tests pass with JsonLogFormatter**

Run: `composer --working-dir=backend test`
Expected: All 353 tests PASS.

- [ ] **Step 6: Commit**

```bash
git add backend/app/Shared/Infrastructure/Logging/JsonLogFormatter.php backend/config/logging.php backend/tests/Unit/Shared/Infrastructure/Logging/JsonLogFormatterTest.php
git commit -m "feat(analytics): add JsonLogFormatter for structured JSON logging"
```

---

### Task 4: Backend: Propagate `correlation_id` through Outbox and Worker Context

**Files:**
- Modify: `backend/app/Shared/Application/IntegrationEvent.php:23-54`
- Modify: `backend/app/Modules/Alerting/Application/Mappers/AlertTriggeredIntegrationMapper.php:14-48`
- Modify: `backend/app/Shared/Infrastructure/Jobs/PublishOutboxMessagesJob.php:44-105`
- Test: `backend/tests/Unit/Modules/Alerting/AlertTriggeredMapperTest.php`
- Test: `backend/tests/Unit/Shared/Infrastructure/Jobs/PublishOutboxMessagesJobTest.php`

**Interfaces:**
- Consumes: `Context::get('correlation_id')` or `Context::get('request_id')`
- Produces: `IntegrationEvent::$correlationId`, envelope `correlation_id`, worker context logging with `event_id`, `job_id`, `correlation_id`

- [ ] **Step 1: Write failing test for `correlation_id` in `AlertTriggeredMapperTest`**

Add test method to `backend/tests/Unit/Modules/Alerting/AlertTriggeredMapperTest.php`:
```php
    public function test_maps_correlation_id_from_context_when_available(): void
    {
        \Illuminate\Support\Facades\Context::add('correlation_id', 'corr-ctx-12345');

        $integration = $this->mapper->map($this->createFixedAlertTriggered());
        $envelope = $integration->toEnvelope();

        self::assertSame('corr-ctx-12345', $integration->correlationId);
        self::assertSame('corr-ctx-12345', $envelope['correlation_id']);

        \Illuminate\Support\Facades\Context::flush();
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=backend test -- --filter=AlertTriggeredMapperTest::test_maps_correlation_id_from_context_when_available`
Expected: FAIL (property `correlationId` does not exist on `IntegrationEvent`).

- [ ] **Step 3: Update `IntegrationEvent`, `AlertTriggeredIntegrationMapper` and `PublishOutboxMessagesJob`**

In `backend/app/Shared/Application/IntegrationEvent.php`:
```php
final class IntegrationEvent
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array{type: string, id: string}  $aggregate
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly int $eventVersion,
        public readonly string $occurredAt,
        public readonly string $producer,
        public readonly string $workspaceId,
        public readonly array $aggregate,
        public readonly array $payload,
        public readonly ?string $correlationId = null,
    ) {}

    /** @return array<string, mixed> */
    public function toEnvelope(): array
    {
        $envelope = [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'event_version' => $this->eventVersion,
            'occurred_at' => $this->occurredAt,
            'producer' => $this->producer,
            'workspace_id' => $this->workspaceId,
            'aggregate' => $this->aggregate,
            'payload' => $this->payload,
        ];

        if ($this->correlationId !== null) {
            $envelope['correlation_id'] = $this->correlationId;
        }

        return $envelope;
    }
}
```

In `backend/app/Modules/Alerting/Application/Mappers/AlertTriggeredIntegrationMapper.php`:
```php
    public function map(AlertTriggered $event): IntegrationEvent
    {
        $correlationId = \Illuminate\Support\Facades\Context::get('correlation_id')
            ?? \Illuminate\Support\Facades\Context::get('request_id');

        return new IntegrationEvent(
            eventId: $event->eventId()->value(),
            eventType: 'alert.triggered',
            eventVersion: 1,
            occurredAt: $event->occurredAt()->format(\DateTimeInterface::ATOM),
            producer: 'analytics',
            workspaceId: $event->workspaceId(),
            aggregate: [
                'type' => 'alert',
                'id' => $event->alertId()->value(),
            ],
            payload: [
                'rule_id' => $event->ruleId()?->value(),
                'rule_name' => $event->ruleName(),
                'severity' => $event->severity()->value,
                'metric' => $event->metric()->value,
                'comparator' => $event->comparator()->value,
                'current_value' => $event->currentValue(),
                'threshold_value' => $event->thresholdValue(),
                'analytical_context' => [
                    'target' => $event->context()->target(),
                    'product_id' => $event->context()->productId(),
                    'product_name' => $event->context()->productName(),
                    'product_sku' => $event->context()->productSku(),
                    'warehouse_id' => $event->context()->warehouseId(),
                    'warehouse_name' => $event->context()->warehouseName(),
                ],
            ],
            correlationId: is_string($correlationId) ? $correlationId : null,
        );
    }
```

In `backend/app/Shared/Infrastructure/Jobs/PublishOutboxMessagesJob.php`:
Set and flush worker context properly:
```php
    public function handle(
        OutboxRepositoryInterface $outboxRepository,
        IntegrationEventTransportInterface $transport,
    ): void {
        \Illuminate\Support\Facades\Context::flush();
        $jobId = $this->job?->getJobId() ?? \Illuminate\Support\Str::uuid()->toString();
        \Illuminate\Support\Facades\Context::add([
            'job_id' => (string) $jobId,
            'operation' => 'PublishOutboxMessagesJob',
        ]);

        try {
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
                $correlationId = $envelope['correlation_id'] ?? null;

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
            \Illuminate\Support\Facades\Context::flush();
        }
    }
```
And in `toIntegrationEvent`:
```php
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
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `composer --working-dir=backend test`
Expected: PASS (all tests pass).

- [ ] **Step 5: Commit**

```bash
git add backend/app/Shared/Application/IntegrationEvent.php backend/app/Modules/Alerting/Application/Mappers/AlertTriggeredIntegrationMapper.php backend/app/Shared/Infrastructure/Jobs/PublishOutboxMessagesJob.php backend/tests/Unit/Modules/Alerting/AlertTriggeredMapperTest.php
git commit -m "feat(outbox): propagate correlation_id through outbox integration events and sanitize worker logging"
```

---

### Task 5: Notification Service: TraceContextMiddleware and JsonLogFormatter

**Files:**
- Create: `notification/app/Http/Middleware/TraceContextMiddleware.php`
- Create: `notification/app/Shared/Infrastructure/Logging/JsonLogFormatter.php`
- Modify: `notification/bootstrap/app.php:17-18`
- Modify: `notification/config/logging.php:5-20`
- Test: `notification/tests/Unit/Http/Middleware/TraceContextMiddlewareTest.php`
- Test: `notification/tests/Unit/Shared/Infrastructure/Logging/JsonLogFormatterTest.php`

**Interfaces:**
- Consumes: HTTP request headers in Notification HTTP endpoints (`/health/live`, `/health/ready`)
- Produces: `X-Request-Id`, `X-Correlation-Id` headers and structured JSON logs with `service: "notification"`

- [ ] **Step 1: Write failing tests for Notification TraceContextMiddleware and JsonLogFormatter**

Create `notification/tests/Unit/Http/Middleware/TraceContextMiddlewareTest.php`:
```php
<?php

declare(strict_types=1);

namespace NotificationService\Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Context;
use NotificationService\Http\Middleware\TraceContextMiddleware;
use Tests\TestCase;

final class TraceContextMiddlewareTest extends TestCase
{
    private TraceContextMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new TraceContextMiddleware();
        Context::flush();
    }

    public function test_assigns_and_returns_request_id(): void
    {
        $request = Request::create('/api/v1/health/live', 'GET');
        $response = $this->middleware->handle($request, function ($req) {
            $this->assertNotNull(Context::get('request_id'));
            return new Response('ok');
        });

        $this->assertTrue($response->headers->has('X-Request-Id'));
    }
}
```

Create `notification/tests/Unit/Shared/Infrastructure/Logging/JsonLogFormatterTest.php`:
```php
<?php

declare(strict_types=1);

namespace NotificationService\Tests\Unit\Shared\Infrastructure\Logging;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use NotificationService\Shared\Infrastructure\Logging\JsonLogFormatter;
use Tests\TestCase;

final class JsonLogFormatterTest extends TestCase
{
    public function test_formats_json_with_notification_service_name(): void
    {
        $formatter = new JsonLogFormatter();
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
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `composer --working-dir=notification test -- --filter=TraceContextMiddlewareTest`
Expected: FAIL.

- [ ] **Step 3: Implement `TraceContextMiddleware` and `JsonLogFormatter` for Notification Service**

Create `notification/app/Http/Middleware/TraceContextMiddleware.php`:
```php
<?php

declare(strict_types=1);

namespace NotificationService\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class TraceContextMiddleware
{
    private const REQUEST_ID_REGEX = '/^[a-zA-Z0-9_\-\.]{1,64}$/';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $rawRequestId = $request->header('X-Request-Id');
        if (is_string($rawRequestId) && preg_match(self::REQUEST_ID_REGEX, trim($rawRequestId)) === 1) {
            $requestId = trim($rawRequestId);
        } else {
            $requestId = Str::uuid()->toString();
        }

        $rawCorrelationId = $request->header('X-Correlation-Id');
        if (is_string($rawCorrelationId) && preg_match(self::REQUEST_ID_REGEX, trim($rawCorrelationId)) === 1) {
            $correlationId = trim($rawCorrelationId);
        } else {
            $correlationId = $requestId;
        }

        $operation = $request->method().' '.($request->route()?->uri() ?? ltrim($request->path(), '/'));

        Context::add([
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'operation' => $operation,
        ]);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);
        $response->headers->set('X-Correlation-Id', $correlationId);

        return $response;
    }
}
```

Create `notification/app/Shared/Infrastructure/Logging/JsonLogFormatter.php`:
```php
<?php

declare(strict_types=1);

namespace NotificationService\Shared\Infrastructure\Logging;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\LogRecord;

final class JsonLogFormatter extends NormalizerFormatter
{
    private const SENSITIVE_PATTERN = '/^(.*_)?(password|token|secret|authorization|auth|cookie|key|credential)(_.*)?$/i';

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

        $requestId = $extra['request_id'] ?? $context['request_id'] ?? null;
        $correlationId = $extra['correlation_id'] ?? $context['correlation_id'] ?? $requestId;
        $operation = $extra['operation'] ?? $context['operation'] ?? null;
        $eventId = $extra['event_id'] ?? $context['event_id'] ?? null;
        $jobId = $extra['job_id'] ?? $context['job_id'] ?? null;
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
```

Update `notification/bootstrap/app.php`:
```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\NotificationService\Http\Middleware\TraceContextMiddleware::class);
    })
```

Update `notification/config/logging.php`:
```php
<?php

use Monolog\Handler\StreamHandler;
use NotificationService\Shared\Infrastructure\Logging\JsonLogFormatter;

return [
    'default' => env('LOG_CHANNEL', 'stderr'),
    'channels' => [
        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => JsonLogFormatter::class,
            'with' => ['stream' => 'php://stderr'],
        ],
        'stack' => [
            'driver' => 'stack',
            'channels' => ['stderr'],
            'ignore_exceptions' => false,
        ],
    ],
];
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `composer --working-dir=notification test`
Expected: PASS (all tests pass).

- [ ] **Step 5: Commit**

```bash
git add notification/app/Http/Middleware/TraceContextMiddleware.php notification/app/Shared/Infrastructure/Logging/JsonLogFormatter.php notification/bootstrap/app.php notification/config/logging.php notification/tests/Unit/Http/Middleware/TraceContextMiddlewareTest.php notification/tests/Unit/Shared/Infrastructure/Logging/JsonLogFormatterTest.php
git commit -m "feat(notification): add TraceContextMiddleware and JsonLogFormatter for notification service"
```

---

### Task 6: Notification Service: Decode `correlation_id` and Flush Context in Worker

**Files:**
- Modify: `notification/app/Notification/Application/AlertTriggeredV1.php:10-32`
- Modify: `notification/app/Notification/Application/AlertTriggeredV1Decoder.php:127-145`
- Modify: `notification/app/Integration/Infrastructure/Redis/RedisStreamConsumer.php:74-126`
- Test: `notification/tests/Unit/Notification/AlertTriggeredV1DecoderTest.php`
- Test: `notification/tests/Unit/Integration/RedisStreamConsumerContextTest.php`

**Interfaces:**
- Consumes: `correlation_id` from event envelope in Redis Stream fields
- Produces: `Context::flush()` + `Context::add('correlation_id', ...)` in consumer, structured worker logging

- [ ] **Step 1: Write failing test for `AlertTriggeredV1Decoder` with `correlation_id`**

Add test to `notification/tests/Unit/Notification/AlertTriggeredV1DecoderTest.php` (or create if not present):
```php
    public function test_decodes_optional_correlation_id_when_present(): void
    {
        $payload = $this->validEnvelope();
        $payload['correlation_id'] = 'corr-req-789';

        $event = $this->decoder->decode($payload);
        $this->assertSame('corr-req-789', $event->correlationId);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer --working-dir=notification test -- --filter=AlertTriggeredV1DecoderTest`
Expected: FAIL (property `correlationId` undefined).

- [ ] **Step 3: Update `AlertTriggeredV1`, `AlertTriggeredV1Decoder` and `RedisStreamConsumer`**

In `notification/app/Notification/Application/AlertTriggeredV1.php`:
```php
final class AlertTriggeredV1
{
    /**
     * @param  array<string, mixed>  $analyticalContext
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly int $eventVersion,
        public readonly DateTimeImmutable $occurredAt,
        public readonly string $producer,
        public readonly string $workspaceId,
        public readonly string $alertId,
        public readonly ?string $ruleId,
        public readonly string $ruleName,
        public readonly NotificationSeverity $severity,
        public readonly string $metric,
        public readonly string $comparator,
        public readonly float $currentValue,
        public readonly float $thresholdValue,
        public readonly array $analyticalContext,
        public readonly ?string $correlationId = null,
    ) {}
}
```

In `notification/app/Notification/Application/AlertTriggeredV1Decoder.php`:
Extract `correlation_id`:
```php
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
```

In `notification/app/Integration/Infrastructure/Redis/RedisStreamConsumer.php`:
Flush and set context before and after each message:
```php
    public function processMessage(string $messageId, array $fields): void
    {
        \Illuminate\Support\Facades\Context::flush();

        $startTime = microtime(true);
        $eventId = isset($fields['event_id']) && is_string($fields['event_id']) ? $fields['event_id'] : null;

        // Try extracting correlation_id from fields or embedded JSON payload
        $correlationId = null;
        if (isset($fields['correlation_id']) && is_string($fields['correlation_id'])) {
            $correlationId = $fields['correlation_id'];
        } elseif (isset($fields['payload']) && is_string($fields['payload'])) {
            $decoded = json_decode($fields['payload'], true);
            if (is_array($decoded) && isset($decoded['correlation_id']) && is_string($decoded['correlation_id'])) {
                $correlationId = $decoded['correlation_id'];
            }
        }

        \Illuminate\Support\Facades\Context::add([
            'stream_message_id' => $messageId,
            'event_id' => $eventId,
            'correlation_id' => $correlationId,
            'operation' => 'notifications:consume',
        ]);

        try {
            $result = $this->router->route($messageId, $fields);

            if ($result->shouldAck()) {
                $this->client->ack($this->streamName, $this->groupName, $messageId);

                $this->logger->info('Integration event processed successfully', [
                    'stream_message_id' => $messageId,
                    'event_id' => $result->eventId ?? $eventId,
                    'correlation_id' => $correlationId,
                    'outcome' => $result->status,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return;
            }

            if ($result->isDeadLetter()) {
                $this->deadLetterPublisher->publish(
                    sourceStream: $this->streamName,
                    sourceStreamId: $messageId,
                    reason: $result->reason ?? 'Dead letter',
                    rawFields: $fields,
                    sourceEventId: $result->eventId ?? $eventId
                );

                $this->client->ack($this->streamName, $this->groupName, $messageId);

                $this->logger->warning('Integration event rejected and moved to dead-letter stream', [
                    'stream_message_id' => $messageId,
                    'event_id' => $result->eventId ?? $eventId,
                    'correlation_id' => $correlationId,
                    'reason' => $result->reason,
                    'outcome' => 'dead_letter',
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return;
            }
        } catch (Throwable $e) {
            $this->logger->error('Transient error while processing integration event, message retained in PEL', [
                'stream_message_id' => $messageId,
                'event_id' => $eventId,
                'correlation_id' => $correlationId,
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);
        } finally {
            \Illuminate\Support\Facades\Context::flush();
        }
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `composer --working-dir=notification test`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add notification/app/Notification/Application/AlertTriggeredV1.php notification/app/Notification/Application/AlertTriggeredV1Decoder.php notification/app/Integration/Infrastructure/Redis/RedisStreamConsumer.php notification/tests/
git commit -m "feat(notification): decode correlation_id and isolate worker context per message cycle"
```

---

### Task 7: Frontend: Next.js Request ID Propagation & Structured Logger

**Files:**
- Modify: `frontend/middleware.ts:1-39`
- Create: `frontend/src/shared/observability/request-id.ts`
- Create: `frontend/src/shared/lib/logger.ts`
- Modify: `frontend/src/shared/api/analytics-client.ts:1-9`
- Modify: `frontend/app/api/health/route.ts:1-4`
- Test: `frontend/src/shared/observability/request-id.test.ts`
- Test: `frontend/src/shared/lib/logger.test.ts`
- Test: `frontend/app/api/health/route.test.ts`

**Interfaces:**
- Consumes: Next.js incoming requests (`X-Request-Id`)
- Produces: Outgoing request `x-request-id` header in `analyticsClient`, response `x-request-id` header in Next.js middleware, structured JSON stdout logs

- [ ] **Step 1: Write failing tests for Next.js logger and request-id helper**

Create `frontend/src/shared/lib/logger.test.ts`:
```typescript
import { describe, it, expect, vi } from 'vitest'
import { logInfo, logError, formatLogEntry } from './logger'

describe('Structured Logger', () => {
  it('formats entry with standard JSON schema', () => {
    const entry = formatLogEntry('info', 'Page rendered', {
      requestId: 'req-abc',
      operation: 'GET /dashboard',
      context: { userId: 'usr-1' },
    })

    expect(entry.service).toBe('web')
    expect(entry.level).toBe('INFO')
    expect(entry.message).toBe('Page rendered')
    expect(entry.request_id).toBe('req-abc')
    expect(entry.operation).toBe('GET /dashboard')
    expect(entry.context).toEqual({ userId: 'usr-1' })
    expect(entry.timestamp).toMatch(/^\d{4}-\d{2}-\d{2}T/)
  })

  it('redacts sensitive fields in context', () => {
    const entry = formatLogEntry('info', 'Auth action', {
      context: { password: 'secret', token: 'bearer', safe: 123 },
    })

    expect(entry.context.password).toBe('[REDACTED]')
    expect(entry.context.token).toBe('[REDACTED]')
    expect(entry.context.safe).toBe(123)
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm --prefix frontend test -- logger.test.ts`
Expected: FAIL with "Cannot find module './logger'".

- [ ] **Step 3: Implement `logger.ts`, `request-id.ts`, update `middleware.ts` and `analytics-client.ts`**

Create `frontend/src/shared/lib/logger.ts`:
```typescript
export interface LogEntryPayload {
  requestId?: string | null
  correlationId?: string | null
  operation?: string | null
  errorCode?: string | null
  context?: Record<string, unknown>
}

const SENSITIVE_KEY_REGEX = /^(.*_)?(password|token|secret|authorization|auth|cookie|key|credential)(_.*)?$/i

function sanitize(data: unknown): unknown {
  if (data === null || typeof data !== 'object') {
    return data
  }
  if (Array.isArray(data)) {
    return data.map(sanitize)
  }
  const result: Record<string, unknown> = {}
  for (const [key, value] of Object.entries(data as Record<string, unknown>)) {
    if (SENSITIVE_KEY_REGEX.test(key)) {
      result[key] = '[REDACTED]'
    } else {
      result[key] = sanitize(value)
    }
  }
  return result
}

export function formatLogEntry(
  level: 'debug' | 'info' | 'warn' | 'error',
  message: string,
  payload?: LogEntryPayload
) {
  return {
    timestamp: new Date().toISOString(),
    level: level.toUpperCase(),
    service: 'web',
    environment: process.env.NODE_ENV ?? 'development',
    operation: payload?.operation ?? null,
    request_id: payload?.requestId ?? null,
    correlation_id: payload?.correlationId ?? payload?.requestId ?? null,
    event_id: null,
    job_id: null,
    error_code: payload?.errorCode ?? null,
    message,
    context: (sanitize(payload?.context ?? {}) as Record<string, unknown>),
  }
}

export function logInfo(message: string, payload?: LogEntryPayload): void {
  console.log(JSON.stringify(formatLogEntry('info', message, payload)))
}

export function logWarn(message: string, payload?: LogEntryPayload): void {
  console.warn(JSON.stringify(formatLogEntry('warn', message, payload)))
}

export function logError(message: string, payload?: LogEntryPayload): void {
  console.error(JSON.stringify(formatLogEntry('error', message, payload)))
}
```

Create `frontend/src/shared/observability/request-id.ts`:
```typescript
const REQUEST_ID_REGEX = /^[a-zA-Z0-9_\-.]{1,64}$/

export function sanitizeOrGenerateRequestId(headerValue?: string | null): string {
  if (headerValue && REQUEST_ID_REGEX.test(headerValue.trim())) {
    return headerValue.trim()
  }
  return crypto.randomUUID()
}
```

Update `frontend/middleware.ts`:
```typescript
import { NextResponse, type NextRequest } from 'next/server'
import { SESSION_COOKIE_NAME, parseSessionValue } from './src/features/auth/model/session'
import { sanitizeOrGenerateRequestId } from './src/shared/observability/request-id'

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl
  const requestId = sanitizeOrGenerateRequestId(request.headers.get('x-request-id'))

  const requestHeaders = new Headers(request.headers)
  requestHeaders.set('x-request-id', requestId)

  const sessionCookie = request.cookies.get(SESSION_COOKIE_NAME)?.value
  const session = parseSessionValue(sessionCookie)
  const isAuthenticated = session !== null

  if (pathname === '/login') {
    if (isAuthenticated) {
      const redirectRes = NextResponse.redirect(new URL('/', request.url))
      redirectRes.headers.set('x-request-id', requestId)
      return redirectRes
    }
    const nextRes = NextResponse.next({ request: { headers: requestHeaders } })
    nextRes.headers.set('x-request-id', requestId)
    return nextRes
  }

  // Allow technical and auth API routes, static assets, etc.
  if (
    pathname.startsWith('/api/auth') ||
    pathname.startsWith('/api/health') ||
    pathname.startsWith('/_next') ||
    pathname.includes('.')
  ) {
    const nextRes = NextResponse.next({ request: { headers: requestHeaders } })
    nextRes.headers.set('x-request-id', requestId)
    return nextRes
  }

  // All other pages require authentication
  if (!isAuthenticated) {
    const loginUrl = new URL('/login', request.url)
    const redirectRes = NextResponse.redirect(loginUrl)
    redirectRes.headers.set('x-request-id', requestId)
    return redirectRes
  }

  const nextRes = NextResponse.next({ request: { headers: requestHeaders } })
  nextRes.headers.set('x-request-id', requestId)
  return nextRes
}

export const config = {
  matcher: ['/((?!_next/static|_next/image|favicon.ico).*)'],
}
```

Update `frontend/src/shared/api/analytics-client.ts`:
```typescript
import createClient, { type Middleware } from 'openapi-fetch'

import type { paths } from './generated/schema'
import { env } from '../config/env'
import { sanitizeOrGenerateRequestId } from '../observability/request-id'

const requestIdMiddleware: Middleware = {
  async onRequest({ request }) {
    if (!request.headers.has('x-request-id')) {
      let requestId: string | null = null
      // In server runtime (Node.js/Next.js SSR), attempt reading headers()
      if (typeof window === 'undefined') {
        try {
          const { headers } = await import('next/headers')
          const headerList = await headers()
          requestId = headerList.get('x-request-id')
        } catch {
          // outside request context or in test
        }
      }
      request.headers.set('x-request-id', sanitizeOrGenerateRequestId(requestId))
    }
    return request
  },
}

export const analyticsClient = createClient<paths>({
  baseUrl: env.analyticsApiUrl,
})

analyticsClient.use(requestIdMiddleware)
```

Update `frontend/app/api/health/route.ts`:
```typescript
import { headers } from 'next/headers'
import { logInfo } from '../../../src/shared/lib/logger'
import { sanitizeOrGenerateRequestId } from '../../../src/shared/observability/request-id'

export async function GET(): Promise<Response> {
  const headerList = await headers()
  const requestId = sanitizeOrGenerateRequestId(headerList.get('x-request-id'))

  logInfo('Health check probe requested', {
    requestId,
    operation: 'GET /api/health',
  })

  return Response.json(
    { status: 'ok', service: 'web' },
    {
      headers: {
        'x-request-id': requestId,
      },
    }
  )
}
```

- [ ] **Step 4: Run frontend tests to verify all pass**

Run: `npm --prefix frontend test`
Expected: PASS (all 220+ tests pass).

- [ ] **Step 5: Run frontend lint & format check**

Run:
```bash
npm --prefix frontend run lint
npm --prefix frontend run format:check
npm --prefix frontend run typecheck
```
Expected: PASS with 0 errors.

- [ ] **Step 6: Commit**

```bash
git add frontend/src/shared/lib/logger.ts frontend/src/shared/lib/logger.test.ts frontend/src/shared/observability/request-id.ts frontend/src/shared/observability/request-id.test.ts frontend/middleware.ts frontend/src/shared/api/analytics-client.ts frontend/app/api/health/route.ts
git commit -m "feat(frontend): propagate request_id via Next.js middleware, analyticsClient, and structured logger"
```

---

### Task 8: Cross-Service Verification & Exit Criteria Checkpoint

**Files:**
- Create: `backend/tests/Feature/ObservabilityTraceContextTest.php`
- Modify: `docs/roadmap/17-observability.md:49-53`

**Interfaces:**
- Verifies: Exit criterion 1 of Phase 17:
  - Server call frontend → analytics carries `request_id`
  - Browser direct call to analytics receives `X-Request-Id` and logs it
  - Outbox message and integration event carry `correlation_id` to notification service
  - Worker context cleaned between cycles

- [ ] **Step 1: Write integration test `ObservabilityTraceContextTest` in backend**

Create `backend/tests/Feature/ObservabilityTraceContextTest.php`:
```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Alerting\Domain\AlertContext;
use App\Modules\Alerting\Domain\AlertId;
use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\AlertSeverity;
use App\Modules\Alerting\Domain\Events\AlertTriggered;
use App\Modules\Alerting\Domain\RuleComparator;
use App\Modules\Alerting\Domain\RuleMetric;
use App\Modules\Alerting\Application\Mappers\AlertTriggeredIntegrationMapper;
use App\Shared\Domain\DomainEventId;
use DateTimeImmutable;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;

final class ObservabilityTraceContextTest extends TestCase
{
    public function test_http_request_returns_request_id_and_logs_in_context(): void
    {
        $response = $this->withHeaders([
            'X-Request-Id' => 'test-req-boundary-1',
            'X-User-Id' => 'usr-1',
        ])->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertHeader('X-Request-Id', 'test-req-boundary-1');
        $response->assertHeader('X-Correlation-Id', 'test-req-boundary-1');
    }

    public function test_event_mapper_inherits_request_correlation_id(): void
    {
        Context::flush();
        Context::add([
            'request_id' => 'orig-http-req-999',
            'correlation_id' => 'orig-http-req-999',
        ]);

        $mapper = new AlertTriggeredIntegrationMapper();
        $event = new AlertTriggered(
            eventId: new DomainEventId('evt-obs-1'),
            alertId: new AlertId('alt-obs-1'),
            workspaceId: 'ws-obs',
            ruleId: new AlertRuleId('rule-obs'),
            ruleName: 'Test Alert',
            severity: AlertSeverity::CRITICAL,
            metric: RuleMetric::QUANTITY_AVAILABLE,
            comparator: RuleComparator::LESS_THAN_OR_EQUAL,
            currentValue: 1.0,
            thresholdValue: 10.0,
            context: new AlertContext(target: 'inventory'),
            occurredAt: new DateTimeImmutable(),
        );

        $integrationEvent = $mapper->map($event);
        $envelope = $integrationEvent->toEnvelope();

        $this->assertSame('orig-http-req-999', $envelope['correlation_id']);
        $this->assertSame('orig-http-req-999', $integrationEvent->correlationId);

        Context::flush();
    }
}
```

- [ ] **Step 2: Run backend integration test**

Run: `composer --working-dir=backend test -- --filter=ObservabilityTraceContextTest`
Expected: PASS.

- [ ] **Step 3: Run comprehensive verification across all services**

```bash
npm --prefix frontend run contracts:validate
npm --prefix frontend test
npm --prefix frontend run lint
npm --prefix frontend run typecheck
composer --working-dir=backend lint
composer --working-dir=backend test
composer --working-dir=notification lint
composer --working-dir=notification test
```
Expected: All suites PASS with 0 errors.

- [ ] **Step 4: Update Progress section in `docs/roadmap/17-observability.md`**

Record completion of Step 1 (Context and structured logging) in `docs/roadmap/17-observability.md`.

- [ ] **Step 5: Commit**

```bash
git add backend/tests/Feature/ObservabilityTraceContextTest.php docs/roadmap/17-observability.md
git commit -m "docs(observability): record completion of Phase 17 Step 1 (context propagation & structured logging)"
```

---

## Verification Plan

### Automated Tests
1. Contract validation:
   ```bash
   npm --prefix frontend run contracts:validate
   ```
2. Frontend unit/gateway/middleware tests:
   ```bash
   npm --prefix frontend test
   npm --prefix frontend run lint
   npm --prefix frontend run typecheck
   ```
3. Backend unit and feature tests:
   ```bash
   composer --working-dir=backend lint
   composer --working-dir=backend test
   ```
4. Notification unit and integration tests:
   ```bash
   composer --working-dir=notification lint
   composer --working-dir=notification test
   ```
5. Full project checks:
   ```bash
   make check
   ```

### Manual / Integration Verification
- Execute a request to `frontend/app/api/health/route.ts` and inspect stdout: confirm JSON output with `timestamp`, `level`, `service: "web"`, `request_id`, `operation: "GET /api/health"`.
- Execute a request to `GET /api/v1/health` with `X-Request-Id: test-browser-123`: confirm response header `X-Request-Id: test-browser-123` and stderr JSON output containing `"service":"analytics"` and `"request_id":"test-browser-123"`.
- Trigger an outbox publish cycle and consumer cycle: inspect log output for `PublishOutboxMessagesJob` and `RedisStreamConsumer` to confirm `event_id`, `correlation_id`, `job_id`, and verify that `Context` is flushed between cycles without leaking state.
