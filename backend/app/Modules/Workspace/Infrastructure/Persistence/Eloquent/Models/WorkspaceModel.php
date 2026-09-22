<?php

namespace App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WorkspaceModel extends Model
{
    protected $table = 'workspaces';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'slug',
    ];

    /** @return HasMany<WorkspaceMemberModel, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMemberModel::class, 'workspace_id', 'id');
    }
}
