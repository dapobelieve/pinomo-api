<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ActivateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('account');
        return $account && $account->status === Account::STATUS_INACTIVE;
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
            'remarks' => ['sometimes', 'string', 'max:255'],
        ];
    }
}