<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Alerting\Application\Contracts\InventoryAlertSourceInterface;
use App\Modules\Alerting\Domain\Repositories\AlertRepositoryInterface;
use App\Modules\Alerting\Domain\Repositories\AlertRuleRepositoryInterface;
use App\Modules\Alerting\Infrastructure\Adapters\InMemoryInventoryAlertSource;
use App\Modules\Alerting\Infrastructure\Adapters\PostgresInventoryAlertSource;
use App\Modules\Alerting\Infrastructure\Repositories\EloquentAlertRepository;
use App\Modules\Alerting\Infrastructure\Repositories\EloquentAlertRuleRepository;
use App\Modules\Alerting\Infrastructure\Repositories\InMemoryAlertRepository;
use App\Modules\Alerting\Infrastructure\Repositories\InMemoryAlertRuleRepository;
use App\Modules\Dashboard\Domain\Repositories\DashboardRepositoryInterface;
use App\Modules\Dashboard\Domain\Repositories\SavedViewRepositoryInterface;
use App\Modules\Dashboard\Infrastructure\Persistence\Eloquent\Repositories\EloquentDashboardRepository;
use App\Modules\Dashboard\Infrastructure\Persistence\Eloquent\Repositories\EloquentSavedViewRepository;
use App\Modules\Dashboard\Infrastructure\Persistence\InMemory\InMemoryDashboardRepository;
use App\Modules\Dashboard\Infrastructure\Persistence\InMemory\InMemorySavedViewRepository;
use App\Modules\DataIngestion\Application\Contracts\ImportJobDispatcherInterface;
use App\Modules\DataIngestion\Application\Contracts\StarSchemaProjectorInterface;
use App\Modules\DataIngestion\Domain\Repositories\ImportBatchRepositoryInterface;
use App\Modules\DataIngestion\Domain\Repositories\ImportFailureRepositoryInterface;
use App\Modules\DataIngestion\Domain\Repositories\StagingRecordRepositoryInterface;
use App\Modules\DataIngestion\Infrastructure\Jobs\QueueImportJobDispatcher;
use App\Modules\DataIngestion\Infrastructure\Projection\InMemoryStarSchemaProjector;
use App\Modules\DataIngestion\Infrastructure\Projection\StarSchemaProjector;
use App\Modules\DataIngestion\Infrastructure\Repositories\EloquentImportBatchRepository;
use App\Modules\DataIngestion\Infrastructure\Repositories\EloquentImportFailureRepository;
use App\Modules\DataIngestion\Infrastructure\Repositories\EloquentStagingRecordRepository;
use App\Modules\DataIngestion\Infrastructure\Repositories\InMemoryImportBatchRepository;
use App\Modules\DataIngestion\Infrastructure\Repositories\InMemoryImportFailureRepository;
use App\Modules\DataIngestion\Infrastructure\Repositories\InMemoryStagingRecordRepository;
use App\Modules\InventoryAnalytics\Application\Contracts\ForecastReadModelInterface;
use App\Modules\InventoryAnalytics\Application\Contracts\ForecastRepositoryInterface;
use App\Modules\InventoryAnalytics\Application\Contracts\InventoryAnalyticsReadModelInterface;
use App\Modules\InventoryAnalytics\Application\Contracts\SalesTimeSeriesReaderInterface;
use App\Modules\InventoryAnalytics\Domain\Forecasting\BacktestEngine;
use App\Modules\InventoryAnalytics\Domain\Forecasting\ForecastEngine;
use App\Modules\InventoryAnalytics\Domain\Forecasting\MovingAverageForecaster;
use App\Modules\InventoryAnalytics\Domain\Forecasting\SeasonalNaiveForecaster;
use App\Modules\InventoryAnalytics\Domain\Forecasting\StockRiskCalculator;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\CachedInventoryAnalyticsReadModel;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\InMemoryForecastReadModel;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\InMemoryInventoryAnalyticsReadModel;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\PostgresForecastReadModel;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\PostgresForecastRepository;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\PostgresInventoryAnalyticsReadModel;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\PostgresSalesTimeSeriesReader;
use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Infrastructure\Persistence\CachedSalesAnalyticsReadModel;
use App\Modules\SalesAnalytics\Infrastructure\Persistence\InMemorySalesAnalyticsReadModel;
use App\Modules\SalesAnalytics\Infrastructure\Persistence\PostgresSalesAnalyticsReadModel;
use App\Modules\SupplierAnalytics\Application\Contracts\SupplierAnalyticsReadModelInterface;
use App\Modules\SupplierAnalytics\Infrastructure\Persistence\CachedSupplierAnalyticsReadModel;
use App\Modules\SupplierAnalytics\Infrastructure\Persistence\InMemorySupplierAnalyticsReadModel;
use App\Modules\SupplierAnalytics\Infrastructure\Persistence\PostgresSupplierAnalyticsReadModel;
use App\Modules\Workspace\Application\Contracts\WorkspaceMemberReadModelInterface;
use App\Modules\Workspace\Application\Contracts\WorkspaceTransactionManagerInterface;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Repositories\EloquentUserRepository;
use App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Repositories\EloquentWorkspaceRepository;
use App\Modules\Workspace\Infrastructure\Persistence\EloquentWorkspaceMemberReadModel;
use App\Modules\Workspace\Infrastructure\Persistence\InMemory\InMemoryUserRepository;
use App\Modules\Workspace\Infrastructure\Persistence\InMemory\InMemoryWorkspaceRepository;
use App\Modules\Workspace\Infrastructure\Persistence\InMemoryWorkspaceMemberReadModel;
use App\Modules\Workspace\Infrastructure\Persistence\InMemoryWorkspaceTransactionManager;
use App\Modules\Workspace\Infrastructure\Persistence\LaravelWorkspaceTransactionManager;
use App\Shared\Application\Ports\IntegrationEventTransportInterface;
use App\Shared\Application\Ports\OutboxRepositoryInterface;
use App\Shared\Application\Ports\TransactionManagerInterface;
use App\Shared\Infrastructure\Cache\AnalyticsDatasetVersionStore;
use App\Shared\Infrastructure\Cache\AnalyticsResultCache;
use App\Shared\Infrastructure\Health\DefaultDependencyHealthChecker;
use App\Shared\Infrastructure\Health\DependencyHealthCheckerInterface;
use App\Shared\Infrastructure\Metrics\PrometheusMetricsRegistry;
use App\Shared\Infrastructure\Outbox\InMemoryOutboxRepository;
use App\Shared\Infrastructure\Persistence\Eloquent\Repositories\EloquentOutboxRepository;
use App\Shared\Infrastructure\Persistence\LaravelTransactionManager;
use App\Shared\Infrastructure\Persistence\NoOpTransactionManager;
use App\Shared\Infrastructure\Security\ProductionSafetyCheck;
use App\Shared\Infrastructure\Transport\InMemoryIntegrationEventTransport;
use App\Shared\Infrastructure\Transport\RedisStreamIntegrationEventTransport;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UserRepositoryInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemoryUserRepository;
            }

            return new EloquentUserRepository;
        });

        $this->app->singleton(WorkspaceRepositoryInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemoryWorkspaceRepository;
            }

            return new EloquentWorkspaceRepository;
        });

        $this->app->singleton(WorkspaceMemberReadModelInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemoryWorkspaceMemberReadModel(
                    $this->app->make(WorkspaceRepositoryInterface::class),
                    $this->app->make(UserRepositoryInterface::class),
                );
            }

            return new EloquentWorkspaceMemberReadModel;
        });

        $this->app->singleton(WorkspaceTransactionManagerInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemoryWorkspaceTransactionManager;
            }

            return new LaravelWorkspaceTransactionManager;
        });

        $this->app->singleton(DependencyHealthCheckerInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new class implements DependencyHealthCheckerInterface
                {
                    public function check(): array
                    {
                        return [
                            'database' => 'ok',
                            'redis' => 'ok',
                        ];
                    }
                };
            }

            return new DefaultDependencyHealthChecker;
        });

        $this->app->singleton(PrometheusMetricsRegistry::class, function () {
            return new PrometheusMetricsRegistry(
                service: 'analytics',
                environment: (string) config('app.env', 'production'),
                forceMemory: $this->app->environment('testing'),
            );
        });

        $this->app->singleton(AnalyticsDatasetVersionStore::class);
        $this->app->singleton(AnalyticsResultCache::class);

        $this->app->singleton(SalesAnalyticsReadModelInterface::class, function () {
            if ($this->app->environment('testing')) {
                $inMemory = new InMemorySalesAnalyticsReadModel;
                if (! config('analytics.cache_enabled', false)) {
                    return $inMemory;
                }

                return new CachedSalesAnalyticsReadModel(
                    delegate: $inMemory,
                    cache: $this->app->make(AnalyticsResultCache::class),
                    versionStore: $this->app->make(AnalyticsDatasetVersionStore::class),
                );
            }

            $postgres = new PostgresSalesAnalyticsReadModel;

            if (! config('analytics.cache_enabled', true)) {
                return $postgres;
            }

            return new CachedSalesAnalyticsReadModel(
                delegate: $postgres,
                cache: $this->app->make(AnalyticsResultCache::class),
                versionStore: $this->app->make(AnalyticsDatasetVersionStore::class),
            );
        });

        $this->app->singleton(InventoryAnalyticsReadModelInterface::class, function () {
            if ($this->app->environment('testing')) {
                $inMemory = new InMemoryInventoryAnalyticsReadModel;
                if (! config('analytics.cache_enabled', false)) {
                    return $inMemory;
                }

                return new CachedInventoryAnalyticsReadModel(
                    delegate: $inMemory,
                    cache: $this->app->make(AnalyticsResultCache::class),
                    versionStore: $this->app->make(AnalyticsDatasetVersionStore::class),
                );
            }

            $postgres = new PostgresInventoryAnalyticsReadModel;

            if (! config('analytics.cache_enabled', true)) {
                return $postgres;
            }

            return new CachedInventoryAnalyticsReadModel(
                delegate: $postgres,
                cache: $this->app->make(AnalyticsResultCache::class),
                versionStore: $this->app->make(AnalyticsDatasetVersionStore::class),
            );
        });

        $this->app->singleton(SupplierAnalyticsReadModelInterface::class, function () {
            if ($this->app->environment('testing')) {
                $inMemory = new InMemorySupplierAnalyticsReadModel;
                if (! config('analytics.cache_enabled', false)) {
                    return $inMemory;
                }

                return new CachedSupplierAnalyticsReadModel(
                    delegate: $inMemory,
                    cache: $this->app->make(AnalyticsResultCache::class),
                    versionStore: $this->app->make(AnalyticsDatasetVersionStore::class),
                );
            }

            $postgres = new PostgresSupplierAnalyticsReadModel;

            if (! config('analytics.cache_enabled', true)) {
                return $postgres;
            }

            return new CachedSupplierAnalyticsReadModel(
                delegate: $postgres,
                cache: $this->app->make(AnalyticsResultCache::class),
                versionStore: $this->app->make(AnalyticsDatasetVersionStore::class),
            );
        });

        $this->app->singleton(DashboardRepositoryInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemoryDashboardRepository;
            }

            return new EloquentDashboardRepository;
        });

        $this->app->singleton(SavedViewRepositoryInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemorySavedViewRepository;
            }

            return new EloquentSavedViewRepository;
        });

        $this->app->singleton(ImportBatchRepositoryInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemoryImportBatchRepository;
            }

            return new EloquentImportBatchRepository;
        });

        $this->app->singleton(ImportFailureRepositoryInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemoryImportFailureRepository;
            }

            return new EloquentImportFailureRepository;
        });

        $this->app->singleton(StagingRecordRepositoryInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemoryStagingRecordRepository;
            }

            return new EloquentStagingRecordRepository;
        });

        $this->app->singleton(StarSchemaProjectorInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemoryStarSchemaProjector;
            }

            /** @var ConnectionInterface $connection */
            $connection = DB::connection();

            return new StarSchemaProjector($connection);
        });

        $this->app->singleton(ImportJobDispatcherInterface::class, function () {
            return new QueueImportJobDispatcher;
        });

        $this->app->singleton(AlertRuleRepositoryInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemoryAlertRuleRepository;
            }

            return new EloquentAlertRuleRepository;
        });

        $this->app->singleton(AlertRepositoryInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemoryAlertRepository;
            }

            return new EloquentAlertRepository;
        });

        $this->app->singleton(InventoryAlertSourceInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemoryInventoryAlertSource;
            }

            return new PostgresInventoryAlertSource;
        });

        $this->app->singleton(ForecastReadModelInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemoryForecastReadModel;
            }

            return new PostgresForecastReadModel;
        });

        $this->app->bind(
            ForecastRepositoryInterface::class,
            PostgresForecastRepository::class,
        );

        $this->app->bind(
            SalesTimeSeriesReaderInterface::class,
            PostgresSalesTimeSeriesReader::class,
        );

        $this->app->singleton(ForecastEngine::class, function () {
            $seasonal = new SeasonalNaiveForecaster;
            $movingAvg = new MovingAverageForecaster;
            $seasonalBacktest = new BacktestEngine($seasonal);
            $movingAvgBacktest = new BacktestEngine($movingAvg);

            return new ForecastEngine(
                primary: $seasonal,
                secondary: $movingAvg,
                primaryBacktest: $seasonalBacktest,
                secondaryBacktest: $movingAvgBacktest,
            );
        });

        $this->app->singleton(StockRiskCalculator::class);

        // --- Outbox / Integration Events ---

        $this->app->singleton(TransactionManagerInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new NoOpTransactionManager;
            }

            return new LaravelTransactionManager;
        });

        $this->app->singleton(OutboxRepositoryInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemoryOutboxRepository;
            }

            return new EloquentOutboxRepository;
        });

        $this->app->singleton(IntegrationEventTransportInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemoryIntegrationEventTransport;
            }

            return new RedisStreamIntegrationEventTransport;
        });
    }

    public function boot(): void
    {
        ProductionSafetyCheck::check(
            $this->app->isProduction(),
            [
                'app_debug' => config('app.debug'),
                'app_key' => config('app.key'),
                'db_password' => config('database.connections.pgsql.password'),
            ]
        );

        RateLimiter::for('login', function (Request $request) {
            $ip = (string) ($request->ip() ?? '127.0.0.1');

            return Limit::perMinute(5)
                ->by($ip)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many login attempts. Please try again later.',
                        'code' => 'TOO_MANY_REQUESTS',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('imports', function (Request $request) {
            $workspaceId = (string) (
                $request->attributes->get('current_workspace_id')
                ?: $request->header('X-Workspace-Id')
                ?: $request->ip()
                ?: 'default'
            );

            return Limit::perMinute(10)
                ->by($workspaceId)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many import requests for this workspace. Please try again later.',
                        'code' => 'TOO_MANY_REQUESTS',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('api-write', function (Request $request) {
            $key = (string) (
                $request->attributes->get('authenticated_user_id')
                ?: $request->header('X-User-Id')
                ?: $request->ip()
                ?: 'default'
            );

            return Limit::perMinute(60)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many write requests. Please try again later.',
                        'code' => 'TOO_MANY_REQUESTS',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('api-read', function (Request $request) {
            $key = (string) (
                $request->attributes->get('authenticated_user_id')
                ?: $request->header('X-User-Id')
                ?: $request->ip()
                ?: 'default'
            );

            return Limit::perMinute(300)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many read requests. Please try again later.',
                        'code' => 'TOO_MANY_REQUESTS',
                    ], 429, $headers);
                });
        });
    }
}
