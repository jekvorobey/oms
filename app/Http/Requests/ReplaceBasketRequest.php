<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceBasketRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'customerId' => 'required|int',
        ];
    }
}
