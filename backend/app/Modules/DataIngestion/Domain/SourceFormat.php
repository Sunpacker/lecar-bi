<?php

namespace App\Modules\DataIngestion\Domain;

enum SourceFormat: string
{
    case CSV = 'csv';
    case JSON = 'json';
}
