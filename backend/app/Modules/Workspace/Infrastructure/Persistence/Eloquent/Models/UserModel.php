<?php

namespace App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

final class UserModel extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'users';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $hidden = [
        'password',
    ];

    protected $fillable = [
        'id',
        'email',
        'name',
        'password',
    ];

    /** @return HasMany<WorkspaceMemberModel, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceMemberModel::class, 'user_id', 'id');
    }
}
