<?php

namespace App\Http\Requests;

use App\Services\AnalyticsService\AnalyticsDateInterval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AnalyticsRequest extends FormRequest
{
    private array $allowedIntervalTypes;

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
            'intervalType' => ['required', 'string', Rule::in(array_keys($this->allowedIntervalTypes))],
        ];
    }

    protected function prepareForValidation()
    {
        $isTopRoute = Str::endsWith($this->route()->getPrefix(), '/top');
        $this->allowedIntervalTypes = AnalyticsDateInterval::TYPES;
        if (!$isTopRoute) {
            unset($this->allowedIntervalTypes[AnalyticsDateInterval::TYPE_DAY]);
        }
    }
}
