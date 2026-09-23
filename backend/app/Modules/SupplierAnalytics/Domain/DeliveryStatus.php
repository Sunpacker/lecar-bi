<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Domain;

enum DeliveryStatus: string
{
    case ON_TIME = 'on_time';
    case DELAYED = 'delayed';
    case PARTIAL = 'partial';
}
