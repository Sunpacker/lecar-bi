<?php

declare(strict_types=1);

namespace App\Modules\KnowledgeBase\Domain;

enum KnowledgeScope: string
{
    case GLOBAL = 'global';
    case WORKSPACE = 'workspace';
}
