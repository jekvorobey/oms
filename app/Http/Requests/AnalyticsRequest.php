<?php

namespace App\Http\Requests;

use App\Services\AnalyticsService\AnalyticsDateInterval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merchantId' => 'required|int',
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
            'intervalType' => ['required', 'string', Rule::in(array_keys(AnalyticsDateInterval::TYPES))],
            'limit' => 'int',
        ];
    }
}
