<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class InvitationModel extends Model
{
    protected $table = 'invitations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
        'email',
        'role',
        'token_hash',
        'status',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'expires_at' => 'immutable_datetime',
        'accepted_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}
