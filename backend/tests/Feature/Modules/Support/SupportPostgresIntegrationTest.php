<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Support;

use App\Modules\KnowledgeBase\Application\Contracts\EmbeddingModel;
use App\Modules\KnowledgeBase\Infrastructure\Ai\ConfiguredEmbeddingModelRegistry;
use App\Modules\KnowledgeBase\Infrastructure\Ai\DeterministicEmbeddingModel;
use App\Modules\KnowledgeBase\Infrastructure\Ingestion\KnowledgeIngestionService;
use App\Modules\KnowledgeBase\Infrastructure\Retrieval\PostgresKnowledgeRetriever;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class SupportPostgresIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('RUN_SUPPORT_POSTGRES_INTEGRATION') !== '1') {
            self::markTestSkipped('Set RUN_SUPPORT_POSTGRES_INTEGRATION=1 for the destructive dedicated PostgreSQL suite');
        }
        if (! extension_loaded('pdo_pgsql')) {
            self::markTestSkipped('pdo_pgsql is required');
        }
        if (DB::connection()->getDriverName() !== 'pgsql') {
            self::fail('Support PostgreSQL integration suite requires DB_CONNECTION=pgsql');
        }

        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    public function test_pgvector_migrations_repeat_ingestion_hybrid_retrieval_and_revocation(): void
    {
        self::assertSame('vector', DB::scalar("SELECT extname FROM pg_extension WHERE extname = 'vector'"));
        self::assertTrue(DB::getSchemaBuilder()->hasTable('knowledge_chunks'));
        self::assertTrue(DB::getSchemaBuilder()->hasTable('support_generations'));

        $ingestion = $this->ingestion(new DeterministicEmbeddingModel);
        $first = $ingestion->ingest($this->manifestPath(), $this->repositoryRoot());
        $publicationVersion = (int) DB::table('knowledge_publications')->where('scope', 'global')->value('version');
        $chunkCount = DB::table('knowledge_chunks')->count();
        $second = $ingestion->ingest($this->manifestPath(), $this->repositoryRoot());

        self::assertSame($first, $second);
        self::assertSame($publicationVersion, (int) DB::table('knowledge_publications')->where('scope', 'global')->value('version'));
        self::assertSame($chunkCount, DB::table('knowledge_chunks')->count());

        $retriever = $this->retriever(new DeterministicEmbeddingModel);
        $result = $retriever->retrieve('workspace-does-not-expand-global-scope', 'Как загрузить CSV импорт?', 4000);
        self::assertSame($first['build_id'], $result['build_id']);
        self::assertNotEmpty($result['chunks']);
        self::assertLessThanOrEqual(6, count($result['chunks']));

        $calibratedRetriever = $this->retrieverWithMinimumScore(0.025, new DeterministicEmbeddingModel);
        self::assertNotEmpty($calibratedRetriever->retrieve('workspace-1', 'Как найти ошибки загрузки?', 4000)['chunks']);
        self::assertSame([], $calibratedRetriever->retrieve('workspace-1', 'Какая цена биткоина?', 4000)['chunks']);

        $revokedDocumentId = (string) $result['chunks'][0]['document_id'];
        $selectedIds = array_column($result['chunks'], 'chunk_id');
        DB::table('knowledge_sources')->where('id', $revokedDocumentId)->update(['revoked_at' => now()]);

        self::assertFalse($retriever->areChunksAvailable('workspace-1', $first['build_id'], $selectedIds));
        $afterRevocation = $retriever->retrieve('workspace-1', 'Как загрузить CSV импорт?', 4000);
        self::assertNotContains($revokedDocumentId, array_column($afterRevocation['chunks'], 'document_id'));
    }

    public function test_partial_failure_and_lost_publication_cas_keep_previous_active_build(): void
    {
        $baseline = $this->ingestion(new DeterministicEmbeddingModel)->ingest($this->manifestPath(), $this->repositoryRoot());
        $partialManifest = $this->temporaryManifest('partial-v2', [
            $this->manifestDocument('getting-started', 'docs/support/getting-started.md', 'partial-v2'),
            $this->manifestDocument('missing', 'docs/support/missing.md', 'partial-v2'),
        ]);

        try {
            $this->ingestion(new DeterministicEmbeddingModel)->ingest($partialManifest, $this->repositoryRoot());
            self::fail('Partial build must fail');
        } catch (RuntimeException $exception) {
            self::assertSame('Manifest source cannot be read', $exception->getMessage());
        }
        self::assertSame($baseline['build_id'], DB::table('knowledge_publications')->where('scope', 'global')->value('active_build_id'));

        $casManifest = $this->temporaryManifest('cas-v2', [
            $this->manifestDocument('getting-started', 'docs/support/getting-started.md', 'cas-v2'),
        ]);
        try {
            $this->ingestion($this->casLosingEmbeddingModel(DB::connection()))->ingest($casManifest, $this->repositoryRoot());
            self::fail('Superseded build must fail publication');
        } catch (RuntimeException $exception) {
            self::assertSame('Knowledge build publication was superseded', $exception->getMessage());
        }

        self::assertSame($baseline['build_id'], DB::table('knowledge_publications')->where('scope', 'global')->value('active_build_id'));
        self::assertSame('failed', DB::table('knowledge_index_builds')->where('manifest_revision', 'cas-v2')->value('status'));
        self::assertSame('PUBLICATION_SUPERSEDED', DB::table('knowledge_index_builds')->where('manifest_revision', 'cas-v2')->value('error_code'));
    }

    public function test_retrieval_uses_the_embedding_model_of_the_active_build(): void
    {
        $profileAProbe = (object) ['calls' => 0];
        $profileBProbe = (object) ['calls' => 0];
        $profileA = $this->profileModel('profile-a', 8, $profileAProbe);
        $profileB = $this->profileModel('profile-b', 16, $profileBProbe);

        $this->ingestion($profileA)->ingest($this->manifestPath(), $this->repositoryRoot());
        $active = $this->ingestion($profileB)->ingest($this->manifestPath(), $this->repositoryRoot());
        $profileACallsAfterIngestion = $profileAProbe->calls;
        $profileBCallsAfterIngestion = $profileBProbe->calls;

        $result = $this->retriever($profileA, $profileB)->retrieve('workspace-1', 'Как загрузить CSV?', 4000);

        self::assertSame($active['build_id'], $result['build_id']);
        self::assertSame($profileACallsAfterIngestion, $profileAProbe->calls);
        self::assertGreaterThan($profileBCallsAfterIngestion, $profileBProbe->calls);
        self::assertSame(16, $result['trace']['profile']['dimensions']);
    }

    private function ingestion(EmbeddingModel $embeddingModel): KnowledgeIngestionService
    {
        return new KnowledgeIngestionService(DB::connection(), $embeddingModel, 500000);
    }

    private function retriever(EmbeddingModel ...$embeddingModels): PostgresKnowledgeRetriever
    {
        return $this->retrieverWithMinimumScore(0.0, ...$embeddingModels);
    }

    private function retrieverWithMinimumScore(float $minimumScore, EmbeddingModel ...$embeddingModels): PostgresKnowledgeRetriever
    {
        return new PostgresKnowledgeRetriever(
            DB::connection(),
            new ConfiguredEmbeddingModelRegistry($embeddingModels),
            30,
            6,
            60,
            $minimumScore,
        );
    }

    private function profileModel(string $profile, int $dimensions, object $probe): EmbeddingModel
    {
        return new class($profile, $dimensions, $probe) implements EmbeddingModel
        {
            public function __construct(
                private readonly string $profileName,
                private readonly int $vectorDimensions,
                private readonly object $probe,
            ) {}

            public function embedDocument(string $text): array
            {
                $this->probe->calls++;

                return array_fill(0, $this->vectorDimensions, 1 / sqrt($this->vectorDimensions));
            }

            public function embedQuery(string $text): array
            {
                return $this->embedDocument($text);
            }

            public function provider(): string
            {
                return 'test';
            }

            public function profile(): string
            {
                return $this->profileName;
            }

            public function dimensions(): int
            {
                return $this->vectorDimensions;
            }
        };
    }

    private function casLosingEmbeddingModel(Connection $connection): EmbeddingModel
    {
        return new class($connection) implements EmbeddingModel
        {
            private bool $advanced = false;

            public function __construct(private readonly Connection $connection) {}

            public function embedDocument(string $text): array
            {
                if (! $this->advanced) {
                    $this->connection->table('knowledge_publications')->where('scope', 'global')->increment('version');
                    $this->advanced = true;
                }

                return [1.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
            }

            public function embedQuery(string $text): array
            {
                return $this->embedDocument($text);
            }

            public function profile(): string
            {
                return 'cas-loser-v1';
            }

            public function provider(): string
            {
                return 'test';
            }

            public function dimensions(): int
            {
                return 8;
            }
        };
    }

    /** @param list<array<string, string>> $documents */
    private function temporaryManifest(string $revision, array $documents): string
    {
        $path = sys_get_temp_dir().'/support-manifest-'.$revision.'.json';
        file_put_contents($path, json_encode([
            'revision' => $revision,
            'language' => 'ru',
            'scope' => 'global',
            'documents' => $documents,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $path;
    }

    /** @return array<string, string> */
    private function manifestDocument(string $id, string $path, string $revision): array
    {
        return [
            'id' => $id,
            'path' => $path,
            'revision' => $revision,
            'title' => $id,
            'url' => '/support/'.$id,
        ];
    }

    private function manifestPath(): string
    {
        return $this->repositoryRoot().'/docs/support/manifest.json';
    }

    private function repositoryRoot(): string
    {
        return dirname(base_path());
    }
}
