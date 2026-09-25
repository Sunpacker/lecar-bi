<?php

declare(strict_types=1);

namespace NotificationService\Notification\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

final class NotificationPreferenceModel extends Model
{
    protected $table = 'notification_preferences';

    protected $fillable = [
        'user_id',
        'workspace_id',
        'level',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}
