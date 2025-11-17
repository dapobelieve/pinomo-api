<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class InitiateAccountRequest extends FormRequest
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
            'client_id' => ['required', 'uuid', 'exists:clients,id'],
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'account_name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'account_number' => ['sometimes', 'string', 'unique:accounts,account_number'],
            'kyc_documents' => ['required', 'array', 'min:1'],
            'kyc_documents.*.type' => ['required', 'string'],
            'kyc_documents.*.file' => ['required', 'file', 'max:10240'], // 10MB max
        ];
    }
}