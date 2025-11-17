<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CloseAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('account');
        return $account && !$account->isClosed();
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
            'closure_reason' => ['required', 'string', 'max:255'],
        ];
    }
}