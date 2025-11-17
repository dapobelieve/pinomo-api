<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class ChargeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'unique:charges,name'],
            'charge_type' => ['required', 'string', 'in:flat,percentage,tiered'],
            'amount' => ['required_if:charge_type,fixed', 'nullable', 'numeric', 'min:0'],
            'percentage' => ['required_if:charge_type,percentage', 'nullable', 'numeric', 'min:0', 'max:100'],
            'currency' => ['required', 'string', 'size:3'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'gl_income_account_id' => ['required', 'numeric', 'exists:gl_accounts,id'],
            'calculation_base' => ['required', 'string', 'in:transaction_amount,loan_amount,account_balance'],
            'charge_frequency' => ['required', 'string', 'in:once,monthly,annually,on_transaction'],
            'applies_to' => ['required', 'string', 'in:account_opening,loan_disbursement,withdrawal,transfer,deposit,withdrawal,vat,loan_interest']
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