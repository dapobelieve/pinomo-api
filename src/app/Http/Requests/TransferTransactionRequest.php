<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class TransferTransactionRequest extends FormRequest
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
            'receiver_id' => ['required', 'string', 'exists:accounts,account_number'],
            'amount' => ['required', 'gt:0', 'numeric'],
            'description' => ['nullable', 'string'],
            'transaction_reference' => ['required', 'string', 'unique:transactions,external_reference'],
        ];
    }

    public function messages(): array
    {
        return [
            'receiver_id.exists' => 'The receiver account does not exist.',
            'amount.gt' => 'The amount must be greater than zero.',
            'transaction_reference.unique' => 'This transaction reference has already been used.',
        ];
    }
}
