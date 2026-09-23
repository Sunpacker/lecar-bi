<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Domain;

enum SupplierReliabilityTier: string
{
    case EXCELLENT = 'excellent';
    case GOOD = 'good';
    case ACCEPTABLE = 'acceptable';
    case POOR = 'poor';
}
