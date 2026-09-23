<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the outbox_messages table.
 * All business logic is in the application layer; this model is a thin persistence vehicle.
 */
final class OutboxMessage extends Model
{
    protected $table = 'outbox_messages';

    /** Disable auto-increment; id = event_id (string). */
    public $incrementing = false;

    protected $keyType = 'string';

    /** Disable Laravel timestamps — we manage them explicitly. */
    public $timestamps = false;

    protected $fillable = [
        'id',
        'event_type',
        'event_version',
        'producer',
        'workspace_id',
        'aggregate_type',
        'aggregate_id',
        'envelope',
        'occurred_at',
        'status',
        'attempt_count',
        'next_attempt_at',
        'locked_at',
        'published_at',
        'redis_message_id',
        'last_error',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'envelope' => 'array',
        'event_version' => 'integer',
        'attempt_count' => 'integer',
        'occurred_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'locked_at' => 'datetime',
        'published_at' => 'datetime',
    ];
}
