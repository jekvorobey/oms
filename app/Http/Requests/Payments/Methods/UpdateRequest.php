<?php

namespace App\Http\Requests\Payments\Methods;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'is_apply_discounts' => 'required|boolean',
            'active' => 'required|boolean',
            'settings' => 'nullable|array',
            'button_text' => 'nullable|string',
            'min_available_price' => 'nullable|numeric',
            'max_available_price' => 'nullable|numeric',
        ];
    }
}
