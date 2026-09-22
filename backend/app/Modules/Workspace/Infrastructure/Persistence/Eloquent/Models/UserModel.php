<?php

namespace App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class UserModel extends Model
{
    protected $table = 'users';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'email',
        'name',
    ];

    /** @return HasMany<WorkspaceMemberModel, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceMemberModel::class, 'user_id', 'id');
    }
}
