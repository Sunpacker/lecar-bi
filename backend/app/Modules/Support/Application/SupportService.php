<?php

declare(strict_types=1);

namespace App\Modules\Support\Application;

use App\Modules\Support\Application\Contracts\GenerationDispatcherInterface;
use App\Modules\Support\Application\Contracts\SupportRepositoryInterface;
use App\Modules\Support\Domain\SupportOperationException;
use App\Modules\Workspace\Application\Guards\WorkspaceAccessGuard;
use App\Modules\Workspace\Domain\WorkspaceCapability;
use Throwable;

final readonly class SupportService
{
    /** @param array<string, int> $limits */
    public function __construct(
        private WorkspaceAccessGuard $accessGuard,
        private SupportRepositoryInterface $repository,
        private GenerationDispatcherInterface $dispatcher,
        private array $limits,
    ) {}

    /** @return array<string, mixed> */
    public function createConversation(string $workspaceId, string $userId, ?string $title, string $idempotencyKey): array
    {
        $this->authorize($workspaceId, $userId);
        $payloadHash = $this->hash(['title' => $title]);

        return ['conversation' => $this->repository->createConversation($workspaceId, $userId, $title, $idempotencyKey, $payloadHash)];
    }

    /** @return array<string, mixed> */
    public function listConversations(string $workspaceId, string $userId, ?string $cursor, int $perPage): array
    {
        $this->authorize($workspaceId, $userId);

        return $this->repository->listConversations($workspaceId, $userId, $cursor, $this->pageSize($perPage));
    }

    /** @return array<string, mixed> */
    public function getConversation(string $workspaceId, string $userId, string $conversationId): array
    {
        $this->authorize($workspaceId, $userId);

        return ['conversation' => $this->repository->getConversation($workspaceId, $userId, $conversationId)];
    }

    public function deleteConversation(string $workspaceId, string $userId, string $conversationId): void
    {
        $this->authorize($workspaceId, $userId);
        $this->repository->deleteConversation($workspaceId, $userId, $conversationId);
    }

    /** @return array<string, string> */
    public function sendMessage(string $workspaceId, string $userId, string $conversationId, string $clientMessageId, string $content, string $idempotencyKey): array
    {
        $this->authorize($workspaceId, $userId);
        $this->validateQuestion($content);
        $payloadHash = $this->hash(['client_message_id' => $clientMessageId, 'content' => $content]);
        $result = $this->repository->admitMessage($workspaceId, $userId, $conversationId, $clientMessageId, $content, $idempotencyKey, $payloadHash, $this->limits);
        $this->dispatchSafely($result['generation_id']);

        return $result;
    }

    /** @return array<string, mixed> */
    public function listMessages(string $workspaceId, string $userId, string $conversationId, ?string $cursor, int $perPage): array
    {
        $this->authorize($workspaceId, $userId);

        return $this->repository->listMessages($workspaceId, $userId, $conversationId, $cursor, $this->pageSize($perPage));
    }

    /** @return array<string, mixed> */
    public function getGeneration(string $workspaceId, string $userId, string $generationId): array
    {
        $this->authorize($workspaceId, $userId);

        return ['generation' => $this->repository->getGeneration($workspaceId, $userId, $generationId)];
    }

    /** @return array<string, string> */
    public function retryGeneration(string $workspaceId, string $userId, string $generationId, string $retryRequestId, string $idempotencyKey): array
    {
        $this->authorize($workspaceId, $userId);
        $payloadHash = $this->hash(['generation_id' => $generationId, 'retry_request_id' => $retryRequestId]);
        $result = $this->repository->retryGeneration($workspaceId, $userId, $generationId, $retryRequestId, $idempotencyKey, $payloadHash, $this->limits);
        $this->dispatchSafely($result['generation_id']);

        return $result;
    }

    /** @return array<string, mixed> */
    public function upsertFeedback(string $workspaceId, string $userId, string $messageId, string $rating): array
    {
        $this->authorize($workspaceId, $userId);

        return $this->repository->upsertFeedback($workspaceId, $userId, $messageId, $rating);
    }

    private function authorize(string $workspaceId, string $userId): void
    {
        $this->accessGuard->assertCapability($userId, $workspaceId, WorkspaceCapability::SUPPORT_USE);
    }

    private function validateQuestion(string $content): void
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            throw SupportOperationException::invalid('EMPTY_QUESTION', 'Question must not be empty');
        }
        if (mb_strlen($trimmed) > $this->limits['question_character_limit']) {
            throw SupportOperationException::invalid('QUESTION_TOO_LONG', 'Question exceeds character limit');
        }
        if ((int) ceil(mb_strlen($trimmed) / 4) > $this->limits['question_token_limit']) {
            throw SupportOperationException::invalid('QUESTION_TOO_LONG', 'Question exceeds token limit');
        }
    }

    /** @param array<string, mixed> $payload */
    private function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function pageSize(int $perPage): int
    {
        return min(100, max(1, $perPage));
    }

    private function dispatchSafely(string $generationId): void
    {
        try {
            $this->dispatcher->dispatch($generationId);
        } catch (Throwable) {
        }
    }
}
