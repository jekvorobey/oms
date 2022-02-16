<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
