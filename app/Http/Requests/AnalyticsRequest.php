<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property string $createdStart
 * @property string $createdEnd
 * @property string|null $paymentStatus
 * @property string|null $filter
 * @property string|null $orderBy
 * @property bool|null $count
 * @property int|null $top
 * @property int|null $skip
 * @property string $callback
 */
class AnalyticsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
        ];
    }
}
