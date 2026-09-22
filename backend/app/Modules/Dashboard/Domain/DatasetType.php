<?php

namespace App\Modules\Dashboard\Domain;

enum DatasetType: string
{
    case SALES = 'sales';
    case INVENTORY = 'inventory';
}
