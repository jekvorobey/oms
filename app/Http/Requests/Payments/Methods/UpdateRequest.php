<?php

namespace App\Http\Requests\Payments\Methods;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'is_postpaid' => 'required|boolean',
            'active' => 'required|boolean',
        ];
    }
}