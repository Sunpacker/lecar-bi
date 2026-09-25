<?php

declare(strict_types=1);

namespace NotificationService\Notification\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $source_event_id
 * @property string $workspace_id
 * @property string $alert_id
 * @property ?string $rule_id
 * @property string $severity
 * @property string $title
 * @property string $body
 * @property array<string, mixed> $analytical_context
 * @property CarbonImmutable $occurred_at
 * @property CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property ?CarbonImmutable $read_at
 */
final class NotificationModel extends Model
{
    protected $table = 'notifications';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = true;

    protected $fillable = [
        'id',
        'source_event_id',
        'workspace_id',
        'alert_id',
        'rule_id',
        'severity',
        'title',
        'body',
        'analytical_context',
        'occurred_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'analytical_context' => 'array',
        'occurred_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}
