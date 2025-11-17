<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DepositTransactionRequest extends FormRequest
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
            'external_id'       => ['required', 'uuid'],
            'amount'            => ['required', 'gt:0', 'numeric'],
            'description'       => ['nullable', 'string'],
            'date'              => ['required', 'date_format:d F Y'],
            'transaction_reference' => ['required', 'string'],
            'session_id'        => ['required', 'string'],
            'payment_id'        =>['sometimes','required', 'nullable']
        ];
    }
}
