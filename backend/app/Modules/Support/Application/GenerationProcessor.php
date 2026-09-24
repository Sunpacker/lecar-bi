<?php

declare(strict_types=1);

namespace App\Modules\Support\Application;

use App\Modules\KnowledgeBase\Application\Contracts\KnowledgeRetriever;
use App\Modules\Support\Application\Contracts\ChatModel;
use App\Modules\Support\Application\Contracts\SupportRepositoryInterface;
use App\Modules\Workspace\Application\Guards\WorkspaceAccessGuard;
use App\Modules\Workspace\Domain\Exceptions\InsufficientWorkspaceCapabilityException;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use App\Modules\Workspace\Domain\WorkspaceCapability;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class GenerationProcessor
{
    /** @param array<string, int> $limits */
    public function __construct(
        private SupportRepositoryInterface $repository,
        private KnowledgeRetriever $retriever,
        private ChatModel $chatModel,
        private WorkspaceAccessGuard $accessGuard,
        private LoggerInterface $logger,
        private array $limits,
    ) {}

    public function process(string $generationId): void
    {
        $claimToken = $this->uuid();
        $claimed = $this->repository->claim($generationId, $claimToken, $this->limits['queue_deadline_seconds'], $this->limits['lease_seconds']);
        if (! $claimed) {
            return;
        }

        try {
            $this->processClaimed($generationId, $claimToken);
        } catch (InsufficientWorkspaceCapabilityException|UnauthorizedWorkspaceAccessException|WorkspaceNotFoundException) {
            $this->failGeneration($generationId, $claimToken, 'ACCESS_REVOKED', false, true);
        } catch (Throwable) {
            $this->failGeneration($generationId, $claimToken, 'GENERATION_FAILED', true, false);
        }
    }

    private function processClaimed(string $generationId, string $claimToken): void
    {
        $startedAt = microtime(true);
        $context = $this->repository->generationContext($generationId, $claimToken);
        $this->accessGuard->assertCapability($context['owner_user_id'], $context['workspace_id'], WorkspaceCapability::SUPPORT_USE);
        $retrievalStartedAt = microtime(true);
        $retrieval = $this->retriever->retrieve($context['workspace_id'], $context['question'], $this->limits['evidence_token_limit']);
        $retrievalMilliseconds = $this->millisecondsSince($retrievalStartedAt);
        $this->repository->recordRetrieval(
            $generationId,
            $claimToken,
            $retrieval['build_id'],
            $retrieval['trace'],
            $this->chatModel->provider(),
            $this->chatModel->model(),
        );

        if ($retrieval['chunks'] === []) {
            $completed = $this->repository->completeNoContext(
                $generationId,
                $claimToken,
                'В опубликованной документации недостаточно данных для уверенного ответа. Уточните вопрос и укажите раздел AutoBI.',
                $this->estimateTokens($context['question']),
            );
            if (! $completed) {
                return;
            }
            $this->logger->info('support.generation.completed', [
                'generation_id' => $generationId,
                'workspace_id' => $context['workspace_id'],
                'outcome' => 'no_context',
                'retrieval_ms' => $retrievalMilliseconds,
                'total_ms' => $this->millisecondsSince($startedAt),
            ]);

            return;
        }

        if (! $this->retrievalIsStillAvailable($context['workspace_id'], $retrieval)) {
            $this->failGeneration($generationId, $claimToken, 'SOURCE_REVOKED', true, false);

            return;
        }

        $this->accessGuard->assertCapability($context['owner_user_id'], $context['workspace_id'], WorkspaceCapability::SUPPORT_USE);

        $text = '';
        $firstOutputMilliseconds = null;
        $deadline = microtime(true) + $this->limits['generation_deadline_seconds'];
        foreach ($this->chatModel->stream($context['question'], $retrieval['chunks']) as $chunk) {
            $firstOutputMilliseconds ??= $this->millisecondsSince($startedAt);
            $text .= $chunk;
            if (microtime(true) >= $deadline) {
                $this->failGeneration($generationId, $claimToken, 'GENERATION_DEADLINE_EXCEEDED', true, false);

                return;
            }
            if ($this->estimateTokens($text) > $this->limits['answer_token_limit']) {
                $this->failGeneration($generationId, $claimToken, 'ANSWER_LIMIT_EXCEEDED', false, false);

                return;
            }
            if (! $this->repository->appendText($generationId, $claimToken, $text)) {
                return;
            }
            $this->repository->heartbeat($generationId, $claimToken, $this->limits['lease_seconds']);
        }

        $citations = $this->resolveCitations($this->chatModel->citedSourceIds(), $retrieval['chunks']);
        if ($citations === []) {
            $this->failGeneration($generationId, $claimToken, 'INVALID_CITATIONS', false, false);

            return;
        }

        if (! $this->retrievalIsStillAvailable($context['workspace_id'], $retrieval)) {
            $this->failGeneration($generationId, $claimToken, 'SOURCE_REVOKED', true, false);

            return;
        }

        $usage = $this->chatModel->usage();
        $completed = $this->repository->completeAnswered(
            $generationId,
            $claimToken,
            $text,
            $citations,
            $usage['input_tokens'] ?? $this->estimateTokens($context['question']),
            isset($usage) ? $usage['output_tokens'] + $usage['reasoning_tokens'] : $this->estimateTokens($text),
        );
        if (! $completed) {
            return;
        }
        $this->logger->info('support.generation.completed', [
            'generation_id' => $generationId,
            'workspace_id' => $context['workspace_id'],
            'outcome' => 'answered',
            'retrieval_ms' => $retrievalMilliseconds,
            'first_output_ms' => $firstOutputMilliseconds,
            'total_ms' => $this->millisecondsSince($startedAt),
        ]);
    }

    /**
     * @param  list<string>  $sourceIds
     * @param  list<array<string, mixed>>  $context
     * @return list<array<string, mixed>>
     */
    private function resolveCitations(array $sourceIds, array $context): array
    {
        $bySource = [];
        foreach ($context as $chunk) {
            $bySource[(string) $chunk['source_key']] = $chunk;
        }

        $citations = [];
        foreach (array_values(array_unique($sourceIds)) as $sourceId) {
            if (! isset($bySource[$sourceId])) {
                return [];
            }
            $citations[] = $bySource[$sourceId];
        }

        return $citations;
    }

    private function estimateTokens(string $text): int
    {
        return max(1, (int) ceil(mb_strlen($text) / 4));
    }

    /**
     * @param  array{build_id: ?string, chunks: list<array<string, mixed>>, trace: array<string, mixed>}  $retrieval
     *
     * @phpstan-impure
     */
    private function retrievalIsStillAvailable(string $workspaceId, array $retrieval): bool
    {
        if ($retrieval['build_id'] === null) {
            return false;
        }

        return $this->retriever->areChunksAvailable(
            $workspaceId,
            $retrieval['build_id'],
            array_map(static fn (array $chunk): string => (string) $chunk['chunk_id'], $retrieval['chunks']),
        );
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }

    private function millisecondsSince(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function failGeneration(string $generationId, string $claimToken, string $errorCode, bool $retryable, bool $usageKnown): void
    {
        if (! $this->repository->fail($generationId, $claimToken, $errorCode, $retryable, $usageKnown)) {
            return;
        }

        $this->logger->warning('support.generation.failed', [
            'generation_id' => $generationId,
            'error_code' => $errorCode,
            'retryable' => $retryable,
            'usage_status' => $usageKnown ? 'known' : 'unknown',
        ]);
    }
}
