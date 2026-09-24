<?php

declare(strict_types=1);

namespace App\Modules\Support\Application\Contracts;

interface SupportRepositoryInterface
{
    /** @return array<string, mixed> */
    public function createConversation(string $workspaceId, string $userId, ?string $title, string $idempotencyKey, string $payloadHash): array;

    /** @return array{items: list<array<string, mixed>>, next_cursor: ?string} */
    public function listConversations(string $workspaceId, string $userId, ?string $cursor, int $perPage): array;

    /** @return array<string, mixed> */
    public function getConversation(string $workspaceId, string $userId, string $conversationId): array;

    public function deleteConversation(string $workspaceId, string $userId, string $conversationId): void;

    /**
     * @param  array<string, int>  $limits
     * @return array<string, string>
     */
    public function admitMessage(string $workspaceId, string $userId, string $conversationId, string $clientMessageId, string $content, string $idempotencyKey, string $payloadHash, array $limits): array;

    /** @return array{items: list<array<string, mixed>>, next_cursor: ?string} */
    public function listMessages(string $workspaceId, string $userId, string $conversationId, ?string $cursor, int $perPage): array;

    /** @return array<string, mixed> */
    public function getGeneration(string $workspaceId, string $userId, string $generationId): array;

    /**
     * @param  array<string, int>  $limits
     * @return array<string, string>
     */
    public function retryGeneration(string $workspaceId, string $userId, string $generationId, string $retryRequestId, string $idempotencyKey, string $payloadHash, array $limits): array;

    /** @return array<string, mixed> */
    public function upsertFeedback(string $workspaceId, string $userId, string $messageId, string $rating): array;

    public function claim(string $generationId, string $claimToken, int $queueDeadlineSeconds, int $leaseSeconds): bool;

    /** @return array{workspace_id: string, owner_user_id: string, question: string} */
    public function generationContext(string $generationId, string $claimToken): array;

    /** @param array<string, mixed> $trace */
    public function recordRetrieval(string $generationId, string $claimToken, ?string $buildId, array $trace, string $provider, string $model): bool;

    public function heartbeat(string $generationId, string $claimToken, int $leaseSeconds): bool;

    public function appendText(string $generationId, string $claimToken, string $text): bool;

    public function completeNoContext(string $generationId, string $claimToken, string $text, int $promptTokens): bool;

    /** @param list<array<string, mixed>> $citations */
    public function completeAnswered(string $generationId, string $claimToken, string $text, array $citations, int $promptTokens, int $completionTokens): bool;

    public function fail(string $generationId, string $claimToken, string $errorCode, bool $retryable, bool $usageKnown): bool;

    /** @return list<string> */
    public function queuedGenerationIds(int $limit): array;

    public function failExpiredLeases(): int;

    public function cleanupExpired(): int;
}
