<?php

namespace App\Http\Requests;

class AnalyticsTopRequest extends AnalyticsRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'limit' => 'required|int',
        ];
    }
}
