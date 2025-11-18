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
            'sender_account_id' => ['required', 'uuid', 'exists:accounts,id'],
            'receiver_id' => ['required', 'uuid', 'exists:accounts,id'],
            'amount' => ['required', 'gt:0', 'numeric'],
            'description' => ['nullable', 'string'],
            'date' => ['required', 'date_format:d F Y'],
            'transaction_reference' => ['required', 'string'],
            'session_id' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'sender_account_id.exists' => 'The sender account does not exist.',
            'receiver_id.exists' => 'The receiver account does not exist.',
            'amount.gt' => 'The amount must be greater than zero.',
        ];
    }
}
