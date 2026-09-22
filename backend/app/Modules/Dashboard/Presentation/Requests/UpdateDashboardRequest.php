<?php

namespace App\Modules\Dashboard\Presentation\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

final class UpdateDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:1', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'widgets' => ['present', 'array'],
            'widgets.*.id' => ['nullable', 'string'],
            'widgets.*.title' => ['required', 'string', 'min:1', 'max:100'],
            'widgets.*.type' => ['required', 'string', 'in:kpi_card,line_chart,bar_chart,donut_chart,table'],
            'widgets.*.query_config' => ['required', 'array'],
            'widgets.*.query_config.dataset' => ['required', 'string', 'in:sales,inventory'],
            'widgets.*.query_config.metric' => ['required', 'string', 'in:revenue,order_count,average_order_value,gross_profit,margin_rate,stock_quantity,stock_value,out_of_stock_count,overstock_count'],
            'widgets.*.query_config.dimension' => ['nullable', 'string', 'in:date,category,region,warehouse,abc_class,xyz_class,supplier'],
            'widgets.*.query_config.date_range' => ['nullable', 'string', 'in:30d,90d,180d,365d,all'],
            'widgets.*.position' => ['required', 'array'],
            'widgets.*.position.x' => ['required', 'integer', 'between:0,11'],
            'widgets.*.position.y' => ['required', 'integer', 'min:0'],
            'widgets.*.position.w' => ['required', 'integer', 'between:1,12'],
            'widgets.*.position.h' => ['required', 'integer', 'between:1,24'],
            'widgets.*.options' => ['nullable', 'array'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation error: '.implode(' ', $validator->errors()->all()),
            'code' => 'VALIDATION_ERROR',
        ], 422));
    }
}
