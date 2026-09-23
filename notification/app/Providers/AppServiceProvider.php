<?php

declare(strict_types=1);

namespace NotificationService\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use NotificationService\Integration\Application\IntegrationEventRouter;
use NotificationService\Integration\Application\IntegrationEventRouterInterface;
use NotificationService\Integration\Infrastructure\Redis\PredisStreamClient;
use NotificationService\Integration\Infrastructure\Redis\RedisDeadLetterPublisher;
use NotificationService\Integration\Infrastructure\Redis\RedisStreamClientInterface;
use NotificationService\Integration\Infrastructure\Redis\RedisStreamConsumer;
use NotificationService\Notification\Application\Contracts\ConsumedEventRepository;
use NotificationService\Notification\Application\Contracts\NotificationRepository;
use NotificationService\Notification\Application\Contracts\TransactionManager;
use NotificationService\Notification\Infrastructure\Persistence\EloquentConsumedEventRepository;
use NotificationService\Notification\Infrastructure\Persistence\EloquentNotificationRepository;
use NotificationService\Shared\Infrastructure\LaravelTransactionManager;
use Psr\Log\LoggerInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationRepository::class, EloquentNotificationRepository::class);
        $this->app->bind(ConsumedEventRepository::class, EloquentConsumedEventRepository::class);
        $this->app->bind(TransactionManager::class, LaravelTransactionManager::class);
        $this->app->bind(IntegrationEventRouterInterface::class, IntegrationEventRouter::class);

        $this->app->singleton(RedisStreamClientInterface::class, function (Application $app): RedisStreamClientInterface {
            $connection = (string) config('integration-events.redis_connection', 'integration_events');

            return new PredisStreamClient($connection);
        });

        $this->app->singleton(RedisDeadLetterPublisher::class, function (Application $app): RedisDeadLetterPublisher {
            return new RedisDeadLetterPublisher(
                client: $app->make(RedisStreamClientInterface::class),
                deadLetterStream: (string) config('integration-events.dead_letter_stream', 'autobi.integration-events.dead-letter')
            );
        });

        $this->app->singleton(RedisStreamConsumer::class, function (Application $app): RedisStreamConsumer {
            return new RedisStreamConsumer(
                client: $app->make(RedisStreamClientInterface::class),
                router: $app->make(IntegrationEventRouter::class),
                deadLetterPublisher: $app->make(RedisDeadLetterPublisher::class),
                logger: $app->make(LoggerInterface::class),
                streamName: (string) config('integration-events.stream_name', 'autobi.integration-events'),
                groupName: (string) config('integration-events.group_name', 'notification-service-v1'),
                consumerName: (string) config('integration-events.consumer_name', 'notification-worker-1'),
                batchSize: (int) config('integration-events.batch_size', 10),
                blockTimeoutMs: (int) config('integration-events.block_timeout_ms', 2000),
                staleIdleMs: (int) config('integration-events.stale_idle_ms', 60000)
            );
        });
    }

    public function boot(): void {}
}
