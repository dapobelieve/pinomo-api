<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChargeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', Rule::unique('charges', 'name')->ignore($this->charge)],
            'charge_type' => ['sometimes', 'string', 'in:fixed,percentage,tiered'],
            'amount' => ['required_if:charge_type,fixed', 'nullable', 'numeric', 'min:0'],
            'percentage' => ['required_if:charge_type,percentage', 'nullable', 'numeric', 'min:0', 'max:100'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'gl_income_account_id' => ['sometimes', 'uuid', 'exists:gl_accounts,id'],
            'calculation_base' => ['sometimes', 'string', 'in:transaction_amount,loan_amount,account_balance'],
            'charge_frequency' => ['sometimes', 'string', 'in:once,monthly,annually,on_transaction'],
            'applies_to' => ['sometimes', 'string', 'in:account_opening,loan_disbursement,withdrawal,transfer']
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'This charge name is already in use.',
            'amount.required_if' => 'The amount field is required when charge type is fixed.',
            'percentage.required_if' => 'The percentage field is required when charge type is percentage.',
            'percentage.max' => 'The percentage cannot exceed 100%.',
            'gl_income_account_id.exists' => 'The selected GL income account does not exist.'
        ];
    }
}