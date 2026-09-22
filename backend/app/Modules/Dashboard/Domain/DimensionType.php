<?php

namespace App\Modules\Dashboard\Domain;

enum DimensionType: string
{
    case DATE = 'date';
    case CATEGORY = 'category';
    case REGION = 'region';
    case WAREHOUSE = 'warehouse';
    case ABC_CLASS = 'abc_class';
    case XYZ_CLASS = 'xyz_class';
    case SUPPLIER = 'supplier';
}
