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
use App\Modules\InventoryAnalytics\Application\Contracts\InventoryAnalyticsReadModelInterface;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\InMemoryInventoryAnalyticsReadModel;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\PostgresInventoryAnalyticsReadModel;
use App\Modules\KnowledgeBase\Application\Contracts\EmbeddingModel;
use App\Modules\KnowledgeBase\Application\Contracts\EmbeddingModelRegistry;
use App\Modules\KnowledgeBase\Application\Contracts\KnowledgeRetriever;
use App\Modules\KnowledgeBase\Infrastructure\Ai\ConfiguredEmbeddingModelRegistry;
use App\Modules\KnowledgeBase\Infrastructure\Ai\DeterministicEmbeddingModel;
use App\Modules\KnowledgeBase\Infrastructure\Ai\NeuronGeminiEmbeddingModel;
use App\Modules\KnowledgeBase\Infrastructure\Ai\UnconfiguredEmbeddingModel;
use App\Modules\KnowledgeBase\Infrastructure\Ingestion\KnowledgeIngestionService;
use App\Modules\KnowledgeBase\Infrastructure\Retrieval\PostgresKnowledgeRetriever;
use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Infrastructure\Persistence\InMemorySalesAnalyticsReadModel;
use App\Modules\SalesAnalytics\Infrastructure\Persistence\PostgresSalesAnalyticsReadModel;
use App\Modules\SupplierAnalytics\Application\Contracts\SupplierAnalyticsReadModelInterface;
use App\Modules\SupplierAnalytics\Infrastructure\Persistence\InMemorySupplierAnalyticsReadModel;
use App\Modules\SupplierAnalytics\Infrastructure\Persistence\PostgresSupplierAnalyticsReadModel;
use App\Modules\Support\Application\Contracts\ChatModel;
use App\Modules\Support\Application\Contracts\GenerationDispatcherInterface;
use App\Modules\Support\Application\Contracts\SupportRepositoryInterface;
use App\Modules\Support\Application\GenerationProcessor;
use App\Modules\Support\Application\SupportService;
use App\Modules\Support\Infrastructure\Ai\DeterministicChatModel;
use App\Modules\Support\Infrastructure\Ai\NeuronGemmaChatModel;
use App\Modules\Support\Infrastructure\Ai\UnconfiguredChatModel;
use App\Modules\Support\Infrastructure\Jobs\QueueGenerationDispatcher;
use App\Modules\Support\Infrastructure\Persistence\PostgresSupportRepository;
use App\Modules\Workspace\Application\Contracts\WorkspaceMemberReadModelInterface;
use App\Modules\Workspace\Application\Contracts\WorkspaceTransactionManagerInterface;
use App\Modules\Workspace\Application\Guards\WorkspaceAccessGuard;
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
use App\Shared\Infrastructure\Ai\StreamingGuzzleHttpClient;
use App\Shared\Infrastructure\Outbox\InMemoryOutboxRepository;
use App\Shared\Infrastructure\Persistence\Eloquent\Repositories\EloquentOutboxRepository;
use App\Shared\Infrastructure\Persistence\LaravelTransactionManager;
use App\Shared\Infrastructure\Persistence\NoOpTransactionManager;
use App\Shared\Infrastructure\Transport\InMemoryIntegrationEventTransport;
use App\Shared\Infrastructure\Transport\RedisStreamIntegrationEventTransport;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\RAG\Embeddings\GeminiEmbeddingsProvider;
use Psr\Log\LoggerInterface;

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

        $this->app->singleton(SalesAnalyticsReadModelInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemorySalesAnalyticsReadModel;
            }

            return new PostgresSalesAnalyticsReadModel;
        });

        $this->app->singleton(InventoryAnalyticsReadModelInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemoryInventoryAnalyticsReadModel;
            }

            return new PostgresInventoryAnalyticsReadModel;
        });

        $this->app->singleton(SupplierAnalyticsReadModelInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemorySupplierAnalyticsReadModel;
            }

            return new PostgresSupplierAnalyticsReadModel;
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

        $this->app->singleton(SupportRepositoryInterface::class, fn () => new PostgresSupportRepository(
            DB::connection(),
            (int) config('support.retention_days'),
            (int) config('support.deletion_grace_hours'),
        ));
        $this->app->singleton(GenerationDispatcherInterface::class, fn () => new QueueGenerationDispatcher((string) config('support.generation_queue')));
        $this->app->singleton(EmbeddingModel::class, fn () => $this->supportEmbeddingModel());
        $this->app->singleton(EmbeddingModelRegistry::class, function () {
            $configured = [$this->app->make(EmbeddingModel::class)];
            if (! $this->app->environment('production') && ! $this->deterministicSupportAdaptersAllowed()) {
                $configured[] = new DeterministicEmbeddingModel;
            }

            return new ConfiguredEmbeddingModelRegistry($configured);
        });
        $this->app->bind(ChatModel::class, fn () => $this->supportChatModel());
        $this->app->singleton(KnowledgeRetriever::class, fn () => new PostgresKnowledgeRetriever(
            DB::connection(),
            $this->app->make(EmbeddingModelRegistry::class),
            (int) config('support.retrieval.candidate_limit'),
            (int) config('support.retrieval.context_limit'),
            (int) config('support.retrieval.rrf_k'),
            (float) config('support.retrieval.minimum_score'),
            (float) config('support.retrieval.minimum_hybrid_vector_score'),
            (float) config('support.retrieval.minimum_semantic_vector_score'),
        ));
        $this->app->singleton(KnowledgeIngestionService::class, fn () => new KnowledgeIngestionService(
            DB::connection(),
            $this->app->make(EmbeddingModel::class),
            (int) config('support.indexing_daily_token_budget'),
        ));
        $this->app->bind(SupportService::class, fn () => new SupportService(
            $this->app->make(WorkspaceAccessGuard::class),
            $this->app->make(SupportRepositoryInterface::class),
            $this->app->make(GenerationDispatcherInterface::class),
            $this->supportLimits(),
        ));
        $this->app->bind(GenerationProcessor::class, fn () => new GenerationProcessor(
            $this->app->make(SupportRepositoryInterface::class),
            $this->app->make(KnowledgeRetriever::class),
            $this->app->make(ChatModel::class),
            $this->app->make(WorkspaceAccessGuard::class),
            $this->app->make(LoggerInterface::class),
            $this->supportLimits(),
        ));
    }

    public function boot(): void {}

    /** @return array<string, int> */
    private function supportLimits(): array
    {
        return [
            'question_character_limit' => (int) config('support.question_character_limit'),
            'question_token_limit' => (int) config('support.question_token_limit'),
            'evidence_token_limit' => (int) config('support.evidence_token_limit'),
            'answer_token_limit' => (int) config('support.answer_token_limit'),
            'generation_reservation_tokens' => (int) config('support.generation_reservation_tokens'),
            'user_rate_per_minute' => (int) config('support.user_rate_per_minute'),
            'workspace_rate_per_minute' => (int) config('support.workspace_rate_per_minute'),
            'active_per_user' => (int) config('support.active_per_user'),
            'active_per_workspace' => (int) config('support.active_per_workspace'),
            'workspace_daily_token_budget' => (int) config('support.workspace_daily_token_budget'),
            'environment_daily_token_budget' => (int) config('support.environment_daily_token_budget'),
            'queue_deadline_seconds' => (int) config('support.queue_deadline_seconds'),
            'generation_deadline_seconds' => (int) config('support.generation_deadline_seconds'),
            'lease_seconds' => (int) config('support.lease_seconds'),
        ];
    }

    private function deterministicSupportAdaptersAllowed(): bool
    {
        if ($this->app->environment('testing')) {
            return true;
        }

        return ! $this->app->environment('production') && config('support.ai_adapter') === 'deterministic';
    }

    private function supportEmbeddingModel(): EmbeddingModel
    {
        if ($this->deterministicSupportAdaptersAllowed()) {
            return new DeterministicEmbeddingModel;
        }
        if (! $this->googleSupportAdapterConfigured()) {
            return new UnconfiguredEmbeddingModel;
        }

        $model = (string) config('support.google.embedding_model');
        $dimensions = (int) config('support.google.embedding_dimensions');
        $provider = new GeminiEmbeddingsProvider(
            key: $this->googleApiKey(),
            model: $model,
            config: ['output_dimensionality' => $dimensions],
            httpClient: $this->supportHttpClient(),
        );

        return new NeuronGeminiEmbeddingModel($provider, $model, $dimensions);
    }

    private function supportChatModel(): ChatModel
    {
        if ($this->deterministicSupportAdaptersAllowed()) {
            return new DeterministicChatModel;
        }
        if (! $this->googleSupportAdapterConfigured()) {
            return new UnconfiguredChatModel;
        }

        $model = (string) config('support.google.chat_model');
        $provider = new Gemini(
            key: $this->googleApiKey(),
            model: $model,
            parameters: [
                'generationConfig' => [
                    'temperature' => (float) config('support.google.temperature'),
                    'maxOutputTokens' => (int) config('support.answer_token_limit'),
                    'thinkingConfig' => ['thinkingLevel' => (string) config('support.google.thinking_level')],
                ],
            ],
            httpClient: $this->supportHttpClient(),
        );

        return new NeuronGemmaChatModel($provider, $model);
    }

    private function googleSupportAdapterConfigured(): bool
    {
        return config('support.ai_adapter') === 'google' && $this->googleApiKey() !== '';
    }

    private function googleApiKey(): string
    {
        return trim((string) config('support.google.api_key'));
    }

    private function supportHttpClient(): StreamingGuzzleHttpClient
    {
        return new StreamingGuzzleHttpClient(
            timeout: (float) config('support.google.timeout_seconds'),
            connectTimeout: 10,
            options: [
                'version' => 1.1,
            ],
        );
    }
}
