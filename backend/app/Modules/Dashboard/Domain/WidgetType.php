<?php

namespace App\Modules\Dashboard\Domain;

enum WidgetType: string
{
    case KPI_CARD = 'kpi_card';
    case LINE_CHART = 'line_chart';
    case BAR_CHART = 'bar_chart';
    case DONUT_CHART = 'donut_chart';
    case TABLE = 'table';
}
