<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class LockAccountFundsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('account');
        return $account && $account->isActive();
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
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . $this->route('account')->available_balance],
        ];
    }
}