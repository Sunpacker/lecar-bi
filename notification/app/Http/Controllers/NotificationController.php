<?php

declare(strict_types=1);

namespace NotificationService\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NotificationService\Notification\Infrastructure\Persistence\NotificationModel;
use NotificationService\Notification\Infrastructure\Persistence\NotificationPreferenceModel;
use NotificationService\Notification\Infrastructure\Persistence\NotificationReadModel;

final class NotificationController
{
    public function list(Request $request): JsonResponse
    {
        $userId = (string) $request->attributes->get('auth_user_id');
        $workspaceId = (string) $request->attributes->get('auth_workspace_id');

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $unreadOnly = filter_var($request->query('unread_only', false), FILTER_VALIDATE_BOOLEAN);
        $severity = $request->query('severity');

        $allowedLevels = $this->getAllowedLevels($userId, $workspaceId);

        $query = NotificationModel::query()
            ->from('notifications')
            ->leftJoin('notification_reads', function ($join) use ($userId): void {
                $join->on('notification_reads.notification_id', '=', 'notifications.id')
                    ->where('notification_reads.user_id', '=', $userId);
            })
            ->where('notifications.workspace_id', $workspaceId)
            ->whereIn('notifications.severity', $allowedLevels)
            ->select([
                'notifications.id',
                'notifications.alert_id',
                'notifications.rule_id',
                'notifications.severity',
                'notifications.title',
                'notifications.body',
                'notifications.occurred_at',
                'notification_reads.read_at',
            ]);

        if ($unreadOnly) {
            $query->whereNull('notification_reads.read_at');
        }

        if ($severity && in_array($severity, ['info', 'warning', 'critical'], true)) {
            $query->where('notifications.severity', $severity);
        }

        $total = (clone $query)->count('notifications.id');

        $items = $query->orderBy('notifications.occurred_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $unreadCount = $this->calculateUnreadCount($userId, $workspaceId, $allowedLevels);

        return response()->json([
            'items' => $items->map(fn ($item) => [
                'id' => (string) $item->id,
                'alert_id' => $item->alert_id,
                'rule_id' => $item->rule_id,
                'severity' => $item->severity,
                'title' => $item->title,
                'body' => $item->body,
                'occurred_at' => $item->occurred_at->format(DATE_ATOM),
                'read_at' => $item->read_at?->format(DATE_ATOM),
            ])->all(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'unread_count' => $unreadCount,
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $userId = (string) $request->attributes->get('auth_user_id');
        $workspaceId = (string) $request->attributes->get('auth_workspace_id');

        $allowedLevels = $this->getAllowedLevels($userId, $workspaceId);
        $count = $this->calculateUnreadCount($userId, $workspaceId, $allowedLevels);

        return response()->json([
            'unread_count' => $count,
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $userId = (string) $request->attributes->get('auth_user_id');
        $workspaceId = (string) $request->attributes->get('auth_workspace_id');

        $exists = NotificationModel::where('id', $id)
            ->where('workspace_id', $workspaceId)
            ->exists();

        if (! $exists) {
            return response()->json([
                'message' => 'Notification not found in this workspace',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        NotificationReadModel::updateOrCreate(
            ['user_id' => $userId, 'notification_id' => $id],
            ['read_at' => CarbonImmutable::now()]
        );

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $userId = (string) $request->attributes->get('auth_user_id');
        $workspaceId = (string) $request->attributes->get('auth_workspace_id');
        $upToId = $request->input('up_to_notification_id');

        $query = NotificationModel::query()
            ->from('notifications')
            ->leftJoin('notification_reads', function ($join) use ($userId): void {
                $join->on('notification_reads.notification_id', '=', 'notifications.id')
                    ->where('notification_reads.user_id', '=', $userId);
            })
            ->where('notifications.workspace_id', $workspaceId)
            ->whereNull('notification_reads.read_at');

        if ($upToId) {
            $upToNotification = NotificationModel::find($upToId);
            if ($upToNotification) {
                $query->where('notifications.occurred_at', '<=', $upToNotification->occurred_at);
            }
        }

        $unreadIds = $query->pluck('notifications.id')->all();
        $updatedCount = 0;
        $now = CarbonImmutable::now();

        foreach ($unreadIds as $notificationId) {
            NotificationReadModel::updateOrCreate(
                ['user_id' => $userId, 'notification_id' => $notificationId],
                ['read_at' => $now]
            );
            $updatedCount++;
        }

        return response()->json([
            'updated_count' => $updatedCount,
        ]);
    }

    public function getPreferences(Request $request): JsonResponse
    {
        $userId = (string) $request->attributes->get('auth_user_id');
        $workspaceId = (string) $request->attributes->get('auth_workspace_id');

        return response()->json([
            'preferences' => $this->getPreferencesMap($userId, $workspaceId),
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $userId = (string) $request->attributes->get('auth_user_id');
        $workspaceId = (string) $request->attributes->get('auth_workspace_id');

        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.info' => ['required', 'boolean'],
            'preferences.warning' => ['required', 'boolean'],
            'preferences.critical' => ['required', 'boolean'],
        ]);

        foreach (['info', 'warning', 'critical'] as $level) {
            NotificationPreferenceModel::updateOrCreate(
                ['user_id' => $userId, 'workspace_id' => $workspaceId, 'level' => $level],
                ['enabled' => (bool) $validated['preferences'][$level]]
            );
        }

        return response()->json([
            'preferences' => $this->getPreferencesMap($userId, $workspaceId),
        ]);
    }

    /**
     * @return array<string, bool>
     */
    private function getPreferencesMap(string $userId, string $workspaceId): array
    {
        $records = NotificationPreferenceModel::where('user_id', $userId)
            ->where('workspace_id', $workspaceId)
            ->get()
            ->keyBy('level');

        return [
            'info' => isset($records['info']) ? (bool) $records['info']->enabled : true,
            'warning' => isset($records['warning']) ? (bool) $records['warning']->enabled : true,
            'critical' => isset($records['critical']) ? (bool) $records['critical']->enabled : true,
        ];
    }

    /**
     * @return list<string>
     */
    private function getAllowedLevels(string $userId, string $workspaceId): array
    {
        $map = $this->getPreferencesMap($userId, $workspaceId);
        $levels = [];
        foreach ($map as $level => $enabled) {
            if ($enabled) {
                $levels[] = $level;
            }
        }

        return $levels;
    }

    /**
     * @param  list<string>  $allowedLevels
     */
    private function calculateUnreadCount(string $userId, string $workspaceId, array $allowedLevels): int
    {
        if (empty($allowedLevels)) {
            return 0;
        }

        return (int) NotificationModel::query()
            ->from('notifications')
            ->leftJoin('notification_reads', function ($join) use ($userId): void {
                $join->on('notification_reads.notification_id', '=', 'notifications.id')
                    ->where('notification_reads.user_id', '=', $userId);
            })
            ->where('notifications.workspace_id', $workspaceId)
            ->whereIn('notifications.severity', $allowedLevels)
            ->whereNull('notification_reads.read_at')
            ->count('notifications.id');
    }
}
