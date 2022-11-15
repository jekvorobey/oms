<?php

namespace App\Http\Requests;

/**
 * @property int $limit
 */
class MerchantAnalyticsTopRequest extends MerchantAnalyticsRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'limit' => 'required|int',
        ]);
    }
}
