<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PlaceLienTransactionRequest extends FormRequest {

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
            'external_id'       => ['required', 'uuid'],
            'amount'            => ['required', 'gt:0', 'numeric'],
            'description' => ['nullable', 'string'],
            'transaction_reference' => ['string']
        ];
    }

}