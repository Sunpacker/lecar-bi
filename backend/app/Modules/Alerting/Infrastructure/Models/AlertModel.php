<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

final class AlertModel extends Model
{
    protected $table = 'alerts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'workspace_id',
        'rule_id',
        'rule_name',
        'severity',
        'status',
        'dedup_fingerprint',
        'product_id',
        'product_name',
        'product_sku',
        'warehouse_id',
        'warehouse_name',
        'current_value',
        'threshold_value',
        'context_data',
        'triggered_at',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
        'resolved_by',
        'resolution_note',
    ];

    protected $casts = [
        'current_value' => 'float',
        'threshold_value' => 'float',
        'context_data' => 'array',
        'triggered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}
