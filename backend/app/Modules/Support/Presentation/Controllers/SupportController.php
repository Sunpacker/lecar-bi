<?php

declare(strict_types=1);

namespace App\Modules\Support\Presentation\Controllers;

use App\Modules\Support\Application\SupportService;
use App\Modules\Support\Domain\SupportOperationException;
use App\Modules\Support\Presentation\Requests\CreateConversationRequest;
use App\Modules\Support\Presentation\Requests\FeedbackRequest;
use App\Modules\Support\Presentation\Requests\RetryGenerationRequest;
use App\Modules\Support\Presentation\Requests\SendMessageRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class SupportController
{
    public function createConversation(CreateConversationRequest $request, SupportService $service): JsonResponse
    {
        return response()->json($service->createConversation(
            $this->workspaceId($request),
            $this->userId($request),
            $request->input('title'),
            $this->idempotencyKey($request),
        ), 201);
    }

    public function listConversations(Request $request, SupportService $service): JsonResponse
    {
        return response()->json($service->listConversations(
            $this->workspaceId($request),
            $this->userId($request),
            $request->query('cursor'),
            (int) $request->query('per_page', 20),
        ));
    }

    public function getConversation(Request $request, string $id, SupportService $service): JsonResponse
    {
        return response()->json($service->getConversation($this->workspaceId($request), $this->userId($request), $id));
    }

    public function deleteConversation(Request $request, string $id, SupportService $service): Response
    {
        $service->deleteConversation($this->workspaceId($request), $this->userId($request), $id);

        return response()->noContent();
    }

    public function sendMessage(SendMessageRequest $request, string $id, SupportService $service): JsonResponse
    {
        return response()->json($service->sendMessage(
            $this->workspaceId($request),
            $this->userId($request),
            $id,
            (string) $request->input('client_message_id'),
            (string) $request->input('content'),
            $this->idempotencyKey($request),
        ), 202);
    }

    public function listMessages(Request $request, string $id, SupportService $service): JsonResponse
    {
        return response()->json($service->listMessages(
            $this->workspaceId($request),
            $this->userId($request),
            $id,
            $request->query('cursor'),
            (int) $request->query('per_page', 20),
        ));
    }

    public function getGeneration(Request $request, string $id, SupportService $service): JsonResponse
    {
        return response()->json($service->getGeneration($this->workspaceId($request), $this->userId($request), $id));
    }

    public function streamGeneration(Request $request, string $id, SupportService $service): StreamedResponse
    {
        $workspaceId = $this->workspaceId($request);
        $userId = $this->userId($request);
        $initial = $service->getGeneration($workspaceId, $userId, $id)['generation'];

        return response()->stream(function () use ($service, $workspaceId, $userId, $id, $initial): void {
            $this->streamPersistedGeneration($service, $workspaceId, $userId, $id, $initial);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-store',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    public function retryGeneration(RetryGenerationRequest $request, string $id, SupportService $service): JsonResponse
    {
        return response()->json($service->retryGeneration(
            $this->workspaceId($request),
            $this->userId($request),
            $id,
            (string) $request->input('retry_request_id'),
            $this->idempotencyKey($request),
        ), 202);
    }

    public function feedback(FeedbackRequest $request, string $id, SupportService $service): JsonResponse
    {
        return response()->json($service->upsertFeedback(
            $this->workspaceId($request),
            $this->userId($request),
            $id,
            (string) $request->input('rating'),
        ));
    }

    /** @param array<string, mixed> $initial */
    private function streamPersistedGeneration(SupportService $service, string $workspaceId, string $userId, string $generationId, array $initial): void
    {
        $snapshot = $initial;
        $lastSequence = -1;
        $heartbeatAt = microtime(true);
        $deadline = microtime(true) + 95;

        while (microtime(true) < $deadline) {
            if ((int) $snapshot['sequence'] > $lastSequence) {
                $this->emit($snapshot);
                $lastSequence = (int) $snapshot['sequence'];
            }
            if (in_array($snapshot['status'], ['completed', 'failed'], true)) {
                return;
            }

            if (microtime(true) - $heartbeatAt >= 15) {
                echo ": heartbeat\n\n";
                flush();
                $heartbeatAt = microtime(true);
            }

            usleep(250000);
            try {
                $snapshot = $service->getGeneration($workspaceId, $userId, $generationId)['generation'];
            } catch (Throwable) {
                return;
            }
        }
    }

    /** @param array<string, mixed> $generation */
    private function emit(array $generation): void
    {
        $event = match ($generation['status']) {
            'completed' => 'completed',
            'failed' => 'failed',
            default => 'snapshot',
        };
        $payload = [
            'generation_id' => $generation['id'],
            'sequence' => $generation['sequence'],
            'status' => $generation['status'],
            'text' => $generation['text'],
        ];
        if ($event === 'completed') {
            $payload += ['outcome' => $generation['outcome'], 'citations' => $generation['citations']];
        }
        if ($event === 'failed') {
            $payload += ['error_code' => $generation['error_code'], 'retryable' => $generation['retryable']];
        }

        echo 'id: '.$generation['sequence']."\n";
        echo 'event: '.$event."\n";
        echo 'data: '.json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n\n";
        flush();
    }

    private function workspaceId(Request $request): string
    {
        return (string) $request->attributes->get('current_workspace_id');
    }

    private function userId(Request $request): string
    {
        return (string) $request->attributes->get('authenticated_user_id');
    }

    private function idempotencyKey(Request $request): string
    {
        $key = trim((string) $request->header('Idempotency-Key'));
        if (mb_strlen($key) < 16 || mb_strlen($key) > 128) {
            throw SupportOperationException::invalid('INVALID_IDEMPOTENCY_KEY', 'Idempotency-Key must contain 16 to 128 characters');
        }

        return $key;
    }
}
