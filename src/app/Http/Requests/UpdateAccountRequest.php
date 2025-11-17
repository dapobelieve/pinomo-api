<?php

namespace App\Http\Requests;

use App\Models\Account;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateAccountRequest extends FormRequest
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
            'account_name' => ['sometimes', 'string', 'max:255'],
            'status' => [
                'sometimes',
                'string',
                'in:' . implode(',', [
                    Account::STATUS_ACTIVE,
                    Account::STATUS_SUSPENDED,
                    Account::STATUS_DORMANT
                ])
            ],
        ];
    }
}