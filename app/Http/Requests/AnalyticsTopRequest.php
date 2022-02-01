<?php

namespace App\Http\Requests;

use App\Services\AnalyticsService\AnalyticsDateInterval;

class AnalyticsTopRequest extends AnalyticsRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'limit' => 'required|int',
        ];
    }

    protected function prepareForValidation()
    {
        parent::prepareForValidation();

        unset($this->allowedIntervalTypes[AnalyticsDateInterval::TYPE_DAY]);
    }
}
