<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class ProductChargeAttachRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'charge_ids' => ['required', 'array'],
            'charge_ids.*' => ['required', 'uuid', 'exists:charges,id']
        ];
    }

    public function messages(): array
    {
        return [
            'charge_ids.required' => 'Please provide at least one charge to attach.',
            'charge_ids.*.exists' => 'One or more selected charges do not exist.'
        ];
    }
}