<?php

namespace App\Modules\Dashboard\Domain;

enum MetricType: string
{
    case REVENUE = 'revenue';
    case ORDER_COUNT = 'order_count';
    case AVERAGE_ORDER_VALUE = 'average_order_value';
    case GROSS_PROFIT = 'gross_profit';
    case MARGIN_RATE = 'margin_rate';
    case STOCK_QUANTITY = 'stock_quantity';
    case STOCK_VALUE = 'stock_value';
    case OUT_OF_STOCK_COUNT = 'out_of_stock_count';
    case OVERSTOCK_COUNT = 'overstock_count';
}
