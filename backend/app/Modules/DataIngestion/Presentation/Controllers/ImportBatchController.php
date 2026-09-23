<?php

namespace App\Modules\DataIngestion\Presentation\Controllers;

use App\Modules\DataIngestion\Application\Commands\RetryImportBatchCommand;
use App\Modules\DataIngestion\Application\Commands\RetryImportBatchHandler;
use App\Modules\DataIngestion\Application\Commands\UploadImportBatchCommand;
use App\Modules\DataIngestion\Application\Commands\UploadImportBatchHandler;
use App\Modules\DataIngestion\Application\Dtos\ImportBatchDto;
use App\Modules\DataIngestion\Application\Dtos\ImportFailureDto;
use App\Modules\DataIngestion\Application\Queries\GetImportBatchByIdHandler;
use App\Modules\DataIngestion\Application\Queries\GetImportBatchByIdQuery;
use App\Modules\DataIngestion\Application\Queries\GetImportBatchesHandler;
use App\Modules\DataIngestion\Application\Queries\GetImportBatchesQuery;
use App\Modules\DataIngestion\Application\Queries\GetImportFailuresHandler;
use App\Modules\DataIngestion\Application\Queries\GetImportFailuresQuery;
use App\Modules\DataIngestion\Domain\Exceptions\CannotRetryImportException;
use App\Modules\DataIngestion\Domain\Exceptions\ImportBatchNotFoundException;
use App\Modules\DataIngestion\Presentation\Requests\UploadImportRequest;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceHandler;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceQuery;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class ImportBatchController
{
    public function index(
        Request $request,
        GetImportBatchesHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $status = $request->query('status');
            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

            $result = $handler->handle(new GetImportBatchesQuery(
                workspaceId: $workspaceId,
                status: is_string($status) && $status !== '' ? $status : null,
                page: $page,
                perPage: $perPage,
            ));

            return response()->json([
                'items' => array_map(static fn (ImportBatchDto $b) => $b->toArray(), $result->items),
                'total' => $result->total,
                'page' => $result->page,
                'per_page' => $result->perPage,
                'total_pages' => $result->totalPages,
            ]);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        }
    }

    public function store(
        UploadImportRequest $request,
        UploadImportBatchHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            /** @var UploadedFile $file */
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $ext = strtolower($file->getClientOriginalExtension());

            if (! in_array($ext, ['csv', 'json', 'jsonl'], true)) {
                return response()->json([
                    'message' => "Unsupported file format '.{$ext}'. Only CSV and JSON/JSONL files are supported.",
                    'code' => 'UNSUPPORTED_FORMAT',
                ], 422);
            }

            $sourceFormat = $ext === 'csv' ? 'csv' : 'json';
            $datasetType = (string) $request->input('dataset_type');

            $storedName = Str::uuid()->toString().'.'.$ext;
            $destinationDir = storage_path("app/imports/{$workspaceId}");
            if (! is_dir($destinationDir)) {
                mkdir($destinationDir, 0755, true);
            }
            $file->move($destinationDir, $storedName);
            $storedPath = "{$destinationDir}/{$storedName}";

            $batchDto = $handler->handle(new UploadImportBatchCommand(
                workspaceId: $workspaceId,
                datasetType: $datasetType,
                sourceFormat: $sourceFormat,
                originalFilename: $originalName,
                storedFilePath: $storedPath,
            ));

            return response()->json([
                'batch' => $batchDto->toArray(),
            ], 202);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        }
    }

    public function show(
        string $id,
        Request $request,
        GetImportBatchByIdHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $batchDto = $handler->handle(new GetImportBatchByIdQuery($workspaceId, $id));

            return response()->json([
                'batch' => $batchDto->toArray(),
            ]);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException|ImportBatchNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        }
    }

    public function failures(
        string $id,
        Request $request,
        GetImportFailuresHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $page = max(1, (int) $request->query('page', 1));
            $perPage = max(1, min(100, (int) $request->query('per_page', 50)));

            $result = $handler->handle(new GetImportFailuresQuery($workspaceId, $id, $page, $perPage));

            return response()->json([
                'items' => array_map(static fn (ImportFailureDto $f) => $f->toArray(), $result->items),
                'total' => $result->total,
                'page' => $result->page,
                'per_page' => $result->perPage,
                'total_pages' => $result->totalPages,
            ]);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException|ImportBatchNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        }
    }

    public function retry(
        string $id,
        Request $request,
        RetryImportBatchHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $batchDto = $handler->handle(new RetryImportBatchCommand($workspaceId, $id));

            return response()->json([
                'batch' => $batchDto->toArray(),
            ], 202);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException|ImportBatchNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        } catch (CannotRetryImportException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'CONFLICT'], 409);
        }
    }
}
