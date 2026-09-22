<?php

namespace App\Modules\Workspace\Domain;

enum MembershipRole: string
{
    case OWNER = 'owner';
    case MEMBER = 'member';
}
