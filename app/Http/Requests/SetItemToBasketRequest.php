<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetItemToBasketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'referrer_id' => 'nullable|integer',
            'qty' => 'integer',
            'product' => 'array',
            'product.store_id' => 'nullable|integer',
            'product.bundle_id' => 'nullable|integer',
        ];
    }
}
