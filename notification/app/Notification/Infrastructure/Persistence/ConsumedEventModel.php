<?php

declare(strict_types=1);

namespace NotificationService\Notification\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $event_id
 * @property string $stream_message_id
 * @property string $event_type
 * @property int $event_version
 * @property string $producer
 * @property string $workspace_id
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable $processed_at
 */
final class ConsumedEventModel extends Model
{
    protected $table = 'consumed_events';

    protected $primaryKey = 'event_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'stream_message_id',
        'event_type',
        'event_version',
        'producer',
        'workspace_id',
        'occurred_at',
        'processed_at',
    ];

    protected $casts = [
        'event_version' => 'integer',
        'occurred_at' => 'immutable_datetime',
        'processed_at' => 'immutable_datetime',
    ];
}
