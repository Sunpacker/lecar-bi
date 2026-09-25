<?php

declare(strict_types=1);

namespace NotificationService\Notification\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

final class NotificationReadModel extends Model
{
    protected $table = 'notification_reads';

    protected $fillable = [
        'user_id',
        'notification_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}
