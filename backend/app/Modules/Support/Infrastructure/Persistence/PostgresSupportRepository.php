<?php

declare(strict_types=1);

namespace App\Modules\Support\Infrastructure\Persistence;

use App\Modules\Support\Application\Contracts\SupportRepositoryInterface;
use App\Modules\Support\Domain\SupportOperationException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;

final readonly class PostgresSupportRepository implements SupportRepositoryInterface
{
    private SupportRecordMapper $mapper;

    public function __construct(
        private Connection $connection,
        private int $retentionDays = 90,
        private int $deletionGraceHours = 24,
    ) {
        $this->mapper = new SupportRecordMapper($connection);
    }

    public function createConversation(string $workspaceId, string $userId, ?string $title, string $idempotencyKey, string $payloadHash): array
    {
        return $this->connection->transaction(function () use ($workspaceId, $userId, $title, $idempotencyKey, $payloadHash): array {
            $this->lockScope("idempotency:{$workspaceId}:{$userId}:create-conversation:{$idempotencyKey}");
            $stored = $this->idempotentResult($workspaceId, $userId, 'create_conversation', 'root', $idempotencyKey, $payloadHash);
            if ($stored !== null) {
                return $stored['conversation'];
            }

            $now = CarbonImmutable::now('UTC');
            $conversation = [
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspaceId,
                'owner_user_id' => $userId,
                'title' => trim((string) $title) === '' ? 'Новый диалог' : trim((string) $title),
                'next_position' => 1,
                'last_activity_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $this->connection->table('support_conversations')->insert($conversation);
            $response = $this->mapper->conversation((object) $conversation);
            $this->storeIdempotency($workspaceId, $userId, 'create_conversation', 'root', $idempotencyKey, $payloadHash, ['conversation' => $response]);

            return $response;
        });
    }

    public function listConversations(string $workspaceId, string $userId, ?string $cursor, int $perPage): array
    {
        $query = $this->connection->table('support_conversations')
            ->where('workspace_id', $workspaceId)
            ->where('owner_user_id', $userId)
            ->whereNull('deleted_at')
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id');
        $decoded = $this->decodeCursor($cursor);

        if ($decoded !== null) {
            $query->where(function ($nested) use ($decoded): void {
                $nested->where('last_activity_at', '<', $decoded['value'])
                    ->orWhere(function ($same) use ($decoded): void {
                        $same->where('last_activity_at', $decoded['value'])->where('id', '<', $decoded['id']);
                    });
            });
        }

        $rows = $query->limit($perPage + 1)->get()->all();
        $hasNext = count($rows) > $perPage;
        $rows = array_slice($rows, 0, $perPage);
        $items = array_map(fn (object $row): array => $this->mapper->conversation($row), $rows);
        $last = end($rows);

        return [
            'items' => $items,
            'next_cursor' => $hasNext && is_object($last) ? $this->encodeCursor((string) $last->last_activity_at, (string) $last->id) : null,
        ];
    }

    public function getConversation(string $workspaceId, string $userId, string $conversationId): array
    {
        return $this->mapper->conversation($this->ownedConversation($workspaceId, $userId, $conversationId));
    }

    public function deleteConversation(string $workspaceId, string $userId, string $conversationId): void
    {
        $this->connection->transaction(function () use ($workspaceId, $userId, $conversationId): void {
            $conversation = $this->ownedConversation($workspaceId, $userId, $conversationId, true);
            $now = CarbonImmutable::now('UTC');
            $this->connection->table('support_conversations')->where('id', $conversation->id)->update(['deleted_at' => $now, 'updated_at' => $now]);
            $this->connection->table('support_generations')
                ->where('conversation_id', $conversation->id)
                ->whereIn('status', ['queued', 'running'])
                ->update(['status' => 'failed', 'error_code' => 'CONVERSATION_DELETED', 'retryable' => false, 'sequence' => $this->connection->raw('sequence + 1'), 'claim_token' => null, 'lease_expires_at' => null, 'completed_at' => $now, 'updated_at' => $now]);
        });
    }

    /**
     * @param  array<string, int>  $limits
     * @return array<string, string>
     */
    public function admitMessage(string $workspaceId, string $userId, string $conversationId, string $clientMessageId, string $content, string $idempotencyKey, string $payloadHash, array $limits): array
    {
        return $this->connection->transaction(function () use ($workspaceId, $userId, $conversationId, $clientMessageId, $content, $idempotencyKey, $payloadHash, $limits): array {
            $conversation = $this->ownedConversation($workspaceId, $userId, $conversationId, true);
            $this->lockScope("idempotency:{$workspaceId}:{$userId}:send:{$conversationId}:{$idempotencyKey}");
            $stored = $this->idempotentResult($workspaceId, $userId, 'send_message', $conversationId, $idempotencyKey, $payloadHash);
            if ($stored !== null) {
                return $stored;
            }

            $existing = $this->connection->table('support_messages')->where('conversation_id', $conversationId)->where('client_message_id', $clientMessageId)->first();
            if ($existing !== null) {
                return $this->existingClientMessageResult($existing, $payloadHash);
            }

            $this->assertAdmission($workspaceId, $userId, $conversationId, $limits);
            $now = CarbonImmutable::now('UTC');
            $userMessageId = (string) Str::uuid();
            $assistantMessageId = (string) Str::uuid();
            $generationId = (string) Str::uuid();
            $position = (int) $conversation->next_position;

            $this->connection->table('support_messages')->insert([
                ['id' => $userMessageId, 'conversation_id' => $conversationId, 'role' => 'user', 'content' => trim($content), 'position' => $position, 'client_message_id' => $clientMessageId, 'payload_hash' => $payloadHash, 'created_at' => $now, 'updated_at' => $now],
                ['id' => $assistantMessageId, 'conversation_id' => $conversationId, 'role' => 'assistant', 'content' => '', 'position' => $position + 1, 'client_message_id' => null, 'payload_hash' => null, 'created_at' => $now, 'updated_at' => $now],
            ]);
            $this->connection->table('support_generations')->insert([
                'id' => $generationId,
                'conversation_id' => $conversationId,
                'user_message_id' => $userMessageId,
                'assistant_message_id' => $assistantMessageId,
                'attempt' => 1,
                'status' => 'queued',
                'sequence' => 0,
                'text' => '',
                'prompt_version' => 'support-v1',
                'usage_status' => 'unknown',
                'retryable' => false,
                'queued_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->reserveBudget($generationId, $workspaceId, $limits['generation_reservation_tokens']);
            $this->connection->table('support_conversations')->where('id', $conversationId)->update(['next_position' => $position + 2, 'last_activity_at' => $now, 'updated_at' => $now]);

            $result = ['message_id' => $userMessageId, 'assistant_message_id' => $assistantMessageId, 'generation_id' => $generationId];
            $this->storeIdempotency($workspaceId, $userId, 'send_message', $conversationId, $idempotencyKey, $payloadHash, $result);

            return $result;
        });
    }

    public function listMessages(string $workspaceId, string $userId, string $conversationId, ?string $cursor, int $perPage): array
    {
        $this->ownedConversation($workspaceId, $userId, $conversationId);
        $query = $this->connection->table('support_messages')->where('conversation_id', $conversationId)->orderBy('position');
        $position = $this->decodePositionCursor($cursor);
        if ($position !== null) {
            $query->where('position', '>', $position);
        }

        $rows = $query->limit($perPage + 1)->get()->all();
        $hasNext = count($rows) > $perPage;
        $rows = array_slice($rows, 0, $perPage);
        $items = array_map(fn (object $row): array => $this->mapper->message($row), $rows);
        $last = end($rows);

        return [
            'items' => $items,
            'next_cursor' => $hasNext && is_object($last) ? base64_encode((string) $last->position) : null,
        ];
    }

    public function getGeneration(string $workspaceId, string $userId, string $generationId): array
    {
        $row = $this->connection->table('support_generations as g')
            ->join('support_conversations as c', 'c.id', '=', 'g.conversation_id')
            ->where('g.id', $generationId)
            ->where('c.workspace_id', $workspaceId)
            ->where('c.owner_user_id', $userId)
            ->whereNull('c.deleted_at')
            ->select('g.*')
            ->first();
        if ($row === null) {
            throw SupportOperationException::notFound();
        }

        return $this->mapper->generation($row);
    }

    /**
     * @param  array<string, int>  $limits
     * @return array<string, string>
     */
    public function retryGeneration(string $workspaceId, string $userId, string $generationId, string $retryRequestId, string $idempotencyKey, string $payloadHash, array $limits): array
    {
        return $this->connection->transaction(function () use ($workspaceId, $userId, $generationId, $retryRequestId, $idempotencyKey, $payloadHash, $limits): array {
            $generation = $this->ownedGeneration($workspaceId, $userId, $generationId, true);
            $this->ownedConversation($workspaceId, $userId, (string) $generation->conversation_id, true);
            $this->lockScope("idempotency:{$workspaceId}:{$userId}:retry:{$generationId}:{$idempotencyKey}");
            $stored = $this->idempotentResult($workspaceId, $userId, 'retry_generation', $generationId, $idempotencyKey, $payloadHash);
            if ($stored !== null) {
                return $stored;
            }

            $existingRetry = $this->connection->table('support_retry_requests')->where('retry_request_id', $retryRequestId)->first();
            if ($existingRetry !== null) {
                return $this->existingRetryResult($existingRetry, $payloadHash);
            }

            $latest = $this->connection->table('support_generations')->where('conversation_id', $generation->conversation_id)->orderByDesc('created_at')->orderByDesc('id')->lockForUpdate()->first();
            $lastUser = $this->connection->table('support_messages')->where('conversation_id', $generation->conversation_id)->where('role', 'user')->orderByDesc('position')->first();
            if ($generation->status !== 'failed' || $latest?->id !== $generationId || $lastUser?->id !== $generation->user_message_id) {
                throw SupportOperationException::conflict('RETRY_SUPERSEDED', 'Only the latest failed generation can be retried');
            }

            $this->assertAdmission($workspaceId, $userId, (string) $generation->conversation_id, $limits);
            $conversation = $this->connection->table('support_conversations')->where('id', $generation->conversation_id)->lockForUpdate()->first();
            $now = CarbonImmutable::now('UTC');
            $assistantMessageId = (string) Str::uuid();
            $nextGenerationId = (string) Str::uuid();
            $position = (int) $conversation->next_position;
            $this->connection->table('support_messages')->insert(['id' => $assistantMessageId, 'conversation_id' => $generation->conversation_id, 'role' => 'assistant', 'content' => '', 'position' => $position, 'created_at' => $now, 'updated_at' => $now]);
            $this->connection->table('support_generations')->insert([
                'id' => $nextGenerationId, 'conversation_id' => $generation->conversation_id, 'user_message_id' => $generation->user_message_id,
                'assistant_message_id' => $assistantMessageId, 'attempt' => ((int) $generation->attempt) + 1, 'status' => 'queued', 'sequence' => 0,
                'text' => '', 'prompt_version' => 'support-v1', 'usage_status' => 'unknown', 'retryable' => false, 'queued_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->reserveBudget($nextGenerationId, $workspaceId, $limits['generation_reservation_tokens']);
            $this->connection->table('support_conversations')->where('id', $generation->conversation_id)->update(['next_position' => $position + 1, 'last_activity_at' => $now, 'updated_at' => $now]);
            $this->connection->table('support_retry_requests')->insert(['retry_request_id' => $retryRequestId, 'generation_id' => $generationId, 'payload_hash' => $payloadHash, 'result_generation_id' => $nextGenerationId, 'created_at' => $now, 'updated_at' => $now]);

            $result = ['message_id' => (string) $generation->user_message_id, 'assistant_message_id' => $assistantMessageId, 'generation_id' => $nextGenerationId];
            $this->storeIdempotency($workspaceId, $userId, 'retry_generation', $generationId, $idempotencyKey, $payloadHash, $result);

            return $result;
        });
    }

    public function upsertFeedback(string $workspaceId, string $userId, string $messageId, string $rating): array
    {
        $message = $this->connection->table('support_messages as m')
            ->join('support_conversations as c', 'c.id', '=', 'm.conversation_id')
            ->join('support_generations as g', 'g.assistant_message_id', '=', 'm.id')
            ->where('m.id', $messageId)->where('m.role', 'assistant')->where('g.status', 'completed')
            ->where('c.workspace_id', $workspaceId)->where('c.owner_user_id', $userId)->whereNull('c.deleted_at')
            ->select('m.id')->first();
        if ($message === null) {
            throw SupportOperationException::notFound();
        }

        $now = CarbonImmutable::now('UTC');
        $this->connection->table('support_feedback')->upsert(
            [['id' => (string) Str::uuid(), 'message_id' => $messageId, 'user_id' => $userId, 'rating' => $rating, 'created_at' => $now, 'updated_at' => $now]],
            ['message_id', 'user_id'],
            ['rating', 'updated_at'],
        );

        return ['message_id' => $messageId, 'rating' => $rating, 'updated_at' => $now->toIso8601String()];
    }

    public function claim(string $generationId, string $claimToken, int $queueDeadlineSeconds, int $leaseSeconds): bool
    {
        return $this->connection->transaction(function () use ($generationId, $claimToken, $queueDeadlineSeconds, $leaseSeconds): bool {
            $generation = $this->connection->table('support_generations')->where('id', $generationId)->lockForUpdate()->first();
            if ($generation === null || $generation->status !== 'queued') {
                return false;
            }

            $now = CarbonImmutable::now('UTC');
            if (CarbonImmutable::parse($generation->queued_at)->addSeconds($queueDeadlineSeconds)->isBefore($now)) {
                $this->connection->table('support_generations')->where('id', $generationId)->update(['status' => 'failed', 'error_code' => 'QUEUE_DEADLINE_EXCEEDED', 'retryable' => true, 'usage_status' => 'known', 'sequence' => ((int) $generation->sequence) + 1, 'completed_at' => $now, 'updated_at' => $now]);
                $this->connection->table('support_budget_reservations')->where('generation_id', $generationId)->update(['actual_tokens' => 0, 'status' => 'settled', 'updated_at' => $now]);

                return false;
            }

            return $this->connection->table('support_generations')->where('id', $generationId)->where('status', 'queued')->update([
                'status' => 'running', 'claim_token' => $claimToken, 'lease_expires_at' => $now->addSeconds($leaseSeconds),
                'started_at' => $now, 'sequence' => ((int) $generation->sequence) + 1, 'updated_at' => $now,
            ]) === 1;
        });
    }

    public function generationContext(string $generationId, string $claimToken): array
    {
        $row = $this->connection->table('support_generations as g')
            ->join('support_conversations as c', 'c.id', '=', 'g.conversation_id')
            ->join('support_messages as m', 'm.id', '=', 'g.user_message_id')
            ->where('g.id', $generationId)->where('g.claim_token', $claimToken)->where('g.status', 'running')->whereNull('c.deleted_at')
            ->select('c.workspace_id', 'c.owner_user_id', 'm.content as question')->first();
        if ($row === null) {
            throw SupportOperationException::notFound();
        }

        return ['workspace_id' => (string) $row->workspace_id, 'owner_user_id' => (string) $row->owner_user_id, 'question' => (string) $row->question];
    }

    public function recordRetrieval(string $generationId, string $claimToken, ?string $buildId, array $trace, string $provider, string $model): bool
    {
        return $this->runningClaim($generationId, $claimToken)->update([
            'corpus_build_id' => $buildId,
            'retrieval_trace' => json_encode($trace, JSON_THROW_ON_ERROR),
            'provider' => $provider,
            'model' => $model,
            'updated_at' => CarbonImmutable::now('UTC'),
        ]) === 1;
    }

    public function heartbeat(string $generationId, string $claimToken, int $leaseSeconds): bool
    {
        $now = CarbonImmutable::now('UTC');

        return $this->runningClaim($generationId, $claimToken)->update(['lease_expires_at' => $now->addSeconds($leaseSeconds), 'updated_at' => $now]) === 1;
    }

    public function appendText(string $generationId, string $claimToken, string $text): bool
    {
        return $this->connection->transaction(function () use ($generationId, $claimToken, $text): bool {
            $generation = $this->runningClaim($generationId, $claimToken)->lockForUpdate()->first();
            if ($generation === null) {
                return false;
            }

            $now = CarbonImmutable::now('UTC');
            $updated = $this->runningClaim($generationId, $claimToken)->update(['text' => $text, 'sequence' => ((int) $generation->sequence) + 1, 'updated_at' => $now]) === 1;
            if ($updated) {
                $this->connection->table('support_messages')->where('id', $generation->assistant_message_id)->update(['content' => $text, 'updated_at' => $now]);
            }

            return $updated;
        });
    }

    public function completeNoContext(string $generationId, string $claimToken, string $text, int $promptTokens): bool
    {
        return $this->complete($generationId, $claimToken, $text, 'no_context', [], $promptTokens, 0);
    }

    public function completeAnswered(string $generationId, string $claimToken, string $text, array $citations, int $promptTokens, int $completionTokens): bool
    {
        if ($citations === []) {
            return false;
        }

        return $this->complete($generationId, $claimToken, $text, 'answered', $citations, $promptTokens, $completionTokens);
    }

    public function fail(string $generationId, string $claimToken, string $errorCode, bool $retryable, bool $usageKnown): bool
    {
        return $this->connection->transaction(function () use ($generationId, $claimToken, $errorCode, $retryable, $usageKnown): bool {
            $generation = $this->runningClaim($generationId, $claimToken)->lockForUpdate()->first();
            if ($generation === null) {
                return false;
            }

            $now = CarbonImmutable::now('UTC');
            $updated = $this->runningClaim($generationId, $claimToken)->update([
                'status' => 'failed', 'error_code' => $errorCode, 'retryable' => $retryable, 'usage_status' => $usageKnown ? 'known' : 'unknown',
                'sequence' => ((int) $generation->sequence) + 1, 'claim_token' => null, 'lease_expires_at' => null, 'completed_at' => $now, 'updated_at' => $now,
            ]) === 1;
            if ($updated && $usageKnown) {
                $this->connection->table('support_budget_reservations')->where('generation_id', $generationId)->update(['actual_tokens' => 0, 'status' => 'settled', 'updated_at' => $now]);
            }

            return $updated;
        });
    }

    public function queuedGenerationIds(int $limit): array
    {
        return $this->connection->table('support_generations')->where('status', 'queued')->orderBy('queued_at')->limit($limit)->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all();
    }

    public function failExpiredLeases(): int
    {
        $now = CarbonImmutable::now('UTC');

        return $this->connection->table('support_generations')->where('status', 'running')->where('lease_expires_at', '<', $now)->update([
            'status' => 'failed', 'error_code' => 'GENERATION_INTERRUPTED', 'retryable' => true, 'sequence' => $this->connection->raw('sequence + 1'), 'claim_token' => null,
            'lease_expires_at' => null, 'completed_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function cleanupExpired(): int
    {
        $now = CarbonImmutable::now('UTC');
        $this->connection->table('support_conversations')->whereNull('deleted_at')->where('last_activity_at', '<=', $now->subDays($this->retentionDays))->update(['deleted_at' => $now, 'updated_at' => $now]);
        $this->connection->table('support_idempotency_keys')->where('expires_at', '<=', $now)->delete();
        $cutoff = $now->subHours($this->deletionGraceHours);
        $conversationIds = $this->connection->table('support_conversations')->whereNotNull('deleted_at')->where('deleted_at', '<=', $cutoff)->pluck('id')->all();
        if ($conversationIds === []) {
            return 0;
        }

        return $this->connection->transaction(function () use ($conversationIds): int {
            $messageIds = $this->connection->table('support_messages')->whereIn('conversation_id', $conversationIds)->pluck('id')->all();
            $generationIds = $this->connection->table('support_generations')->whereIn('conversation_id', $conversationIds)->pluck('id')->all();
            if ($messageIds !== []) {
                $this->connection->table('support_feedback')->whereIn('message_id', $messageIds)->delete();
            }
            if ($generationIds !== []) {
                $this->connection->table('support_citations')->whereIn('generation_id', $generationIds)->delete();
                $this->connection->table('support_budget_reservations')->whereIn('generation_id', $generationIds)->delete();
            }
            $this->connection->table('support_retry_requests')->whereIn('generation_id', $generationIds)->delete();
            $this->connection->table('support_generations')->whereIn('conversation_id', $conversationIds)->delete();
            $this->connection->table('support_messages')->whereIn('conversation_id', $conversationIds)->delete();
            $this->connection->table('support_idempotency_keys')->whereIn('resource_id', [...$conversationIds, ...$generationIds])->delete();

            return $this->connection->table('support_conversations')->whereIn('id', $conversationIds)->delete();
        });
    }

    /** @param array<string, int> $limits */
    private function assertAdmission(string $workspaceId, string $userId, string $conversationId, array $limits): void
    {
        $activeStatuses = ['queued', 'running'];
        $activeConversation = $this->connection->table('support_generations')->where('conversation_id', $conversationId)->whereIn('status', $activeStatuses)->count();
        if ($activeConversation >= 1) {
            throw SupportOperationException::conflict('GENERATION_IN_PROGRESS', 'Conversation already has an active generation');
        }

        $oneMinuteAgo = CarbonImmutable::now('UTC')->subMinute();
        $userQuery = $this->connection->table('support_generations as g')->join('support_conversations as c', 'c.id', '=', 'g.conversation_id')->where('c.owner_user_id', $userId);
        if ((clone $userQuery)->where('g.created_at', '>=', $oneMinuteAgo)->count() >= $limits['user_rate_per_minute']) {
            throw SupportOperationException::limited('USER_RATE_LIMITED', 60);
        }
        if ($this->connection->table('support_generations as g')->join('support_conversations as c', 'c.id', '=', 'g.conversation_id')->where('c.workspace_id', $workspaceId)->where('g.created_at', '>=', $oneMinuteAgo)->count() >= $limits['workspace_rate_per_minute']) {
            throw SupportOperationException::limited('WORKSPACE_RATE_LIMITED', 60);
        }
        if ((clone $userQuery)->whereIn('g.status', $activeStatuses)->count() >= $limits['active_per_user']) {
            throw SupportOperationException::limited('USER_CONCURRENCY_LIMITED', 5);
        }
        if ($this->connection->table('support_generations as g')->join('support_conversations as c', 'c.id', '=', 'g.conversation_id')->where('c.workspace_id', $workspaceId)->whereIn('g.status', $activeStatuses)->count() >= $limits['active_per_workspace']) {
            throw SupportOperationException::limited('WORKSPACE_CONCURRENCY_LIMITED', 5);
        }

        $this->lockScope('support-budget:'.CarbonImmutable::now('UTC')->toDateString());
        $reserved = $limits['generation_reservation_tokens'];
        $effectiveTokens = "CASE WHEN status = 'settled' THEN COALESCE(actual_tokens, reserved_tokens) ELSE reserved_tokens END";
        $workspaceUsed = (int) $this->connection->table('support_budget_reservations')->where('workspace_id', $workspaceId)->where('budget_date', CarbonImmutable::now('UTC')->toDateString())->sum($this->connection->raw($effectiveTokens));
        $environmentUsed = (int) $this->connection->table('support_budget_reservations')->where('budget_date', CarbonImmutable::now('UTC')->toDateString())->sum($this->connection->raw($effectiveTokens));
        if ($workspaceUsed + $reserved > $limits['workspace_daily_token_budget']) {
            throw SupportOperationException::limited('WORKSPACE_BUDGET_EXCEEDED', 3600);
        }
        if ($environmentUsed + $reserved > $limits['environment_daily_token_budget']) {
            throw SupportOperationException::limited('ENVIRONMENT_BUDGET_EXCEEDED', 3600);
        }
    }

    private function reserveBudget(string $generationId, string $workspaceId, int $tokens): void
    {
        $now = CarbonImmutable::now('UTC');
        $this->connection->table('support_budget_reservations')->insert([
            'id' => (string) Str::uuid(), 'generation_id' => $generationId, 'workspace_id' => $workspaceId,
            'budget_date' => $now->toDateString(), 'reserved_tokens' => $tokens, 'status' => 'reserved', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    /** @param list<array<string, mixed>> $citations */
    private function complete(string $generationId, string $claimToken, string $text, string $outcome, array $citations, int $promptTokens, int $completionTokens): bool
    {
        return $this->connection->transaction(function () use ($generationId, $claimToken, $text, $outcome, $citations, $promptTokens, $completionTokens): bool {
            $generation = $this->runningClaim($generationId, $claimToken)->lockForUpdate()->first();
            if ($generation === null) {
                return false;
            }

            $now = CarbonImmutable::now('UTC');
            foreach ($citations as $ordinal => $citation) {
                $this->connection->table('support_citations')->insert([
                    'id' => (string) Str::uuid(), 'generation_id' => $generationId, 'message_id' => $generation->assistant_message_id,
                    'document_id' => $citation['document_id'], 'document_revision' => $citation['revision'], 'chunk_id' => $citation['chunk_id'],
                    'title' => $citation['title'], 'url' => $citation['url'], 'anchor' => $citation['anchor'], 'ordinal' => $ordinal + 1,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $updated = $this->runningClaim($generationId, $claimToken)->update([
                'status' => 'completed', 'outcome' => $outcome, 'text' => $text, 'sequence' => ((int) $generation->sequence) + 1,
                'prompt_tokens' => $promptTokens, 'completion_tokens' => $completionTokens, 'usage_status' => 'known', 'retryable' => false,
                'claim_token' => null, 'lease_expires_at' => null, 'completed_at' => $now, 'updated_at' => $now,
            ]) === 1;
            if (! $updated) {
                return false;
            }

            $this->connection->table('support_messages')->where('id', $generation->assistant_message_id)->update(['content' => $text, 'updated_at' => $now]);
            $actualTokens = $promptTokens + $completionTokens;
            $this->connection->table('support_budget_reservations')->where('generation_id', $generationId)->update(['actual_tokens' => $actualTokens, 'status' => 'settled', 'updated_at' => $now]);

            return true;
        });
    }

    private function ownedConversation(string $workspaceId, string $userId, string $conversationId, bool $lock = false): object
    {
        $query = $this->connection->table('support_conversations')->where('id', $conversationId)->where('workspace_id', $workspaceId)->where('owner_user_id', $userId)->whereNull('deleted_at');
        if ($lock) {
            $query->lockForUpdate();
        }
        $conversation = $query->first();
        if ($conversation === null) {
            throw SupportOperationException::notFound();
        }

        return $conversation;
    }

    private function ownedGeneration(string $workspaceId, string $userId, string $generationId, bool $lock = false): object
    {
        $query = $this->connection->table('support_generations as g')->join('support_conversations as c', 'c.id', '=', 'g.conversation_id')
            ->where('g.id', $generationId)->where('c.workspace_id', $workspaceId)->where('c.owner_user_id', $userId)->whereNull('c.deleted_at')->select('g.*');
        if ($lock) {
            $query->lockForUpdate();
        }
        $generation = $query->first();
        if ($generation === null) {
            throw SupportOperationException::notFound();
        }

        return $generation;
    }

    private function runningClaim(string $generationId, string $claimToken): mixed
    {
        return $this->connection->table('support_generations')->where('id', $generationId)->where('status', 'running')->where('claim_token', $claimToken);
    }

    /** @return array<string, string> */
    private function existingClientMessageResult(object $message, string $payloadHash): array
    {
        if (! hash_equals((string) $message->payload_hash, $payloadHash)) {
            throw SupportOperationException::conflict('IDEMPOTENCY_CONFLICT', 'client_message_id was already used with a different payload');
        }
        $generation = $this->connection->table('support_generations')->where('user_message_id', $message->id)->orderBy('attempt')->first();

        return ['message_id' => (string) $message->id, 'assistant_message_id' => (string) $generation->assistant_message_id, 'generation_id' => (string) $generation->id];
    }

    /** @return array<string, string> */
    private function existingRetryResult(object $retry, string $payloadHash): array
    {
        if (! hash_equals((string) $retry->payload_hash, $payloadHash)) {
            throw SupportOperationException::conflict('IDEMPOTENCY_CONFLICT', 'retry_request_id was already used with a different payload');
        }
        $generation = $this->connection->table('support_generations')->where('id', $retry->result_generation_id)->first();

        return ['message_id' => (string) $generation->user_message_id, 'assistant_message_id' => (string) $generation->assistant_message_id, 'generation_id' => (string) $generation->id];
    }

    /** @return array<string, mixed>|null */
    private function idempotentResult(string $workspaceId, string $userId, string $operation, string $resourceId, string $key, string $payloadHash): ?array
    {
        $row = $this->connection->table('support_idempotency_keys')->where([
            'workspace_id' => $workspaceId, 'user_id' => $userId, 'operation' => $operation, 'resource_id' => $resourceId, 'idempotency_key' => $key,
        ])->first();
        if ($row === null) {
            return null;
        }
        if (! hash_equals((string) $row->payload_hash, $payloadHash)) {
            throw SupportOperationException::conflict('IDEMPOTENCY_CONFLICT', 'Idempotency-Key was already used with a different payload');
        }

        return $this->decodeJson($row->result);
    }

    /** @param array<string, mixed> $result */
    private function storeIdempotency(string $workspaceId, string $userId, string $operation, string $resourceId, string $key, string $payloadHash, array $result): void
    {
        $now = CarbonImmutable::now('UTC');
        $this->connection->table('support_idempotency_keys')->insert([
            'id' => (string) Str::uuid(), 'workspace_id' => $workspaceId, 'user_id' => $userId, 'operation' => $operation,
            'resource_id' => $resourceId, 'idempotency_key' => $key, 'payload_hash' => $payloadHash,
            'result' => json_encode($result, JSON_THROW_ON_ERROR), 'expires_at' => $now->addDay(), 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    /** @return array<string, mixed> */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return json_decode((string) $value, true, flags: JSON_THROW_ON_ERROR);
    }

    private function lockScope(string $scope): void
    {
        if ($this->connection->getDriverName() === 'pgsql') {
            $this->connection->select('SELECT pg_advisory_xact_lock(hashtext(?))', [$scope]);
        }
    }

    private function encodeCursor(string $value, string $id): string
    {
        return base64_encode(json_encode(['value' => $value, 'id' => $id], JSON_THROW_ON_ERROR));
    }

    /** @return array{value: string, id: string}|null */
    private function decodeCursor(?string $cursor): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }
        $decoded = base64_decode($cursor, true);
        if ($decoded === false) {
            throw SupportOperationException::invalid('INVALID_CURSOR', 'Cursor is invalid');
        }
        $value = json_decode($decoded, true);
        if (! is_array($value) || ! isset($value['value'], $value['id'])) {
            throw SupportOperationException::invalid('INVALID_CURSOR', 'Cursor is invalid');
        }

        return ['value' => (string) $value['value'], 'id' => (string) $value['id']];
    }

    private function decodePositionCursor(?string $cursor): ?int
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }
        $decoded = base64_decode($cursor, true);
        if ($decoded === false || ! ctype_digit($decoded)) {
            throw SupportOperationException::invalid('INVALID_CURSOR', 'Cursor is invalid');
        }

        return (int) $decoded;
    }
}
