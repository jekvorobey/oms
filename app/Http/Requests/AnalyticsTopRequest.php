<?php

namespace App\Http\Requests;

/**
 * @property int $limit
 */
class AnalyticsTopRequest extends AnalyticsRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'limit' => 'required|int',
        ]);
    }
}
