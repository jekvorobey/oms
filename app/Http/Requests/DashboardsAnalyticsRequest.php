<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property string $start
 * @property string $end
 */
class DashboardsAnalyticsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            //'start' => 'required|date',
            //'end' => 'required|date|after_or_equal:start',
        ];
    }
}
