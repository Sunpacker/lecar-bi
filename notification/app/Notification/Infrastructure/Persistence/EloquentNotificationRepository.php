<?php

declare(strict_types=1);

namespace NotificationService\Notification\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\QueryException;
use NotificationService\Notification\Application\Contracts\NotificationRepository;
use NotificationService\Notification\Domain\Notification;
use NotificationService\Notification\Domain\NotificationId;
use NotificationService\Notification\Domain\NotificationSeverity;

final class EloquentNotificationRepository implements NotificationRepository
{
    public function save(Notification $notification): void
    {
        try {
            NotificationModel::query()->create([
                'id' => $notification->id()->toString(),
                'source_event_id' => $notification->sourceEventId(),
                'workspace_id' => $notification->workspaceId(),
                'alert_id' => $notification->alertId(),
                'rule_id' => $notification->ruleId(),
                'severity' => $notification->severity()->value,
                'title' => $notification->title(),
                'body' => $notification->body(),
                'analytical_context' => $notification->analyticalContext(),
                'occurred_at' => $notification->occurredAt(),
                'created_at' => $notification->createdAt(),
                'updated_at' => $notification->createdAt(),
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return;
            }

            throw $e;
        }
    }

    public function findBySourceEventId(string $sourceEventId): ?Notification
    {
        /** @var NotificationModel|null $model */
        $model = NotificationModel::query()->where('source_event_id', $sourceEventId)->first();
        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findById(NotificationId $id): ?Notification
    {
        /** @var NotificationModel|null $model */
        $model = NotificationModel::query()->find($id->toString());
        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    private function toDomain(NotificationModel $model): Notification
    {
        return new Notification(
            id: new NotificationId($model->id),
            sourceEventId: $model->source_event_id,
            workspaceId: $model->workspace_id,
            alertId: $model->alert_id,
            ruleId: $model->rule_id,
            severity: NotificationSeverity::fromString($model->severity),
            title: $model->title,
            body: $model->body,
            analyticalContext: $model->analytical_context,
            occurredAt: new DateTimeImmutable($model->occurred_at->toIso8601String()),
            createdAt: new DateTimeImmutable($model->created_at->toIso8601String())
        );
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $code = (string) $e->getCode();
        if ($code === '23505') {
            return true;
        }

        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'unique') || str_contains($msg, 'duplicate');
    }
}
