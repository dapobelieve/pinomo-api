<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateAccountTransactionLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('account'));
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
            'single_transaction_limit' => ['required', 'numeric', 'min:0', 'decimal:0,4']
        ];
    }
}