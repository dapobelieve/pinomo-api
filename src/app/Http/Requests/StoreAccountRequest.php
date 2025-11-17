<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422)
        );
    }

    public function rules(): array
    {
        return [
            'id' => ['sometimes', 'uuid'],
            'external_id' => ['required', 'uuid', 'exists:clients,external_id'],
            'product_id' => [
                'required',
                'uuid',
                'exists:products,id',
                function ($attribute, $value, $fail) {
                    $product = \App\Models\Product::find($value);
                    if ($product && !$product->is_active) {
                        $fail('The selected product is not active.');
                    }
                }
            ],
            'account_name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', 'string', 'in:main,reserve'],
            'status' => ['sometimes', 'string', 'in:' . Account::STATUS_ACTIVE . ',' . Account::STATUS_INACTIVE],
        ];
    }
}