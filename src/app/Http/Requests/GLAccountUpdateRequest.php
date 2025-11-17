<?php

namespace App\Http\Requests;

use App\Models\GLAccount;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class GLAccountUpdateRequest extends FormRequest
{
    public function authorize()
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

    public function rules()
    {
        return [
            'account_code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('gl_accounts', 'account_code')->ignore($this->route('gl_account')),
                'regex:/^[A-Z0-9-]+$/',
            ],
            'account_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'account_type' => [
                'sometimes',
                'required',
                Rule::in([
                    GLAccount::TYPE_ASSET,
                    GLAccount::TYPE_LIABILITY,
                    GLAccount::TYPE_EQUITY,
                    GLAccount::TYPE_INCOME,
                    GLAccount::TYPE_EXPENSE,
                ]),
                function ($attribute, $value, $fail) {
                    $account = $this->route('gl_account');
                    if ($account->hasChildren() && $value !== $account->account_type) {
                        $fail('Cannot change account type when account has children.');
                    }
                },
            ],
            'currency' => [
                'sometimes',
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
            ],
            'parent_account_id' => [
                'nullable',
                'exists:gl_accounts,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        // Prevent self-reference
                        if ($value == $this->route('gl_account')->id) {
                            $fail('Account cannot be its own parent.');
                            return;
                        }

                        // Check account type match
                        $parentAccount = GLAccount::find($value);
                        if ($parentAccount && $parentAccount->account_type !== $this->input('account_type', $this->route('gl_account')->account_type)) {
                            $fail('Parent account must be of the same type.');
                        }

                        // Prevent circular reference
                        $parent = $parentAccount;
                        while ($parent) {
                            if ($parent->id === $this->route('gl_account')->id) {
                                $fail('Circular reference detected in parent account hierarchy.');
                                break;
                            }
                            $parent = $parent->parent;
                        }
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => [
                'boolean',
                function ($attribute, $value, $fail) {
                    if ($value === false && $this->route('gl_account')->hasChildren()) {
                        $fail('Cannot deactivate account with active children.');
                    }
                },
            ],
        ];
    }

    public function messages()
    {
        return [
            'account_code.required' => 'Account code is required.',
            'account_code.unique' => 'This account code is already in use.',
            'account_code.regex' => 'Account code must contain only uppercase letters, numbers, and hyphens.',
            'account_name.required' => 'Account name is required.',
            'account_type.required' => 'Account type is required.',
            'account_type.in' => 'Invalid account type selected.',
            'currency.required' => 'Currency code is required.',
            'currency.size' => 'Currency code must be exactly 3 characters.',
            'currency.regex' => 'Currency code must be in ISO format (e.g., USD).',
            'parent_account_id.exists' => 'Selected parent account does not exist.',
        ];
    }
}