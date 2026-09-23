<?php

namespace App\Modules\DataIngestion\Domain;

enum DatasetType: string
{
    case SALES = 'sales';
    case INVENTORY = 'inventory';
}
