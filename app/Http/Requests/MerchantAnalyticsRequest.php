<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property int $merchantId
 * @property string $start
 * @property string $end
 */
class MerchantAnalyticsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'merchantId' => 'required|int',
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
        ];
    }
}
