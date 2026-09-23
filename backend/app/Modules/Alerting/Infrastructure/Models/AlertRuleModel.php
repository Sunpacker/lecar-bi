<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

final class AlertRuleModel extends Model
{
    protected $table = 'alert_rules';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'workspace_id',
        'name',
        'description',
        'rule_type',
        'severity',
        'metric',
        'comparator',
        'threshold_value',
        'warehouse_id',
        'category_id',
        'product_id',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'threshold_value' => 'float',
    ];
}
