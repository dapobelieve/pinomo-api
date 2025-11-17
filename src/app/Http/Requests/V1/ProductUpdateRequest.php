<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $baseRules = [
            'product_name' => ['sometimes', 'required', 'string', Rule::unique('products')->ignore($this->product)],
            'product_type' => ['sometimes', 'required', 'string', 'in:deposit,loan,wallet,escrow'],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean']
        ];

        return array_merge($baseRules, $this->getProductTypeSpecificRules());
    }

    protected function getProductTypeSpecificRules(): array
    {
        // If product_type is not being updated, get it from the existing product
        $productType = $this->input('product_type') ?? $this->product->product_type;

        switch ($productType) {
            case 'deposit':
                return [
                    'minimum_amount' => ['sometimes', 'required', 'numeric', 'min:0', 'lt:maximum_amount'],
                    'maximum_amount' => ['sometimes', 'required', 'numeric', 'min:0'],
                    'interest_rate' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
                    'interest_rate_type' => ['sometimes', 'required', 'string', 'in:fixed,variable'],
                    'interest_calculation_frequency' => ['sometimes', 'required', 'string', 'in:daily,monthly,annually'],
                    'interest_posting_frequency' => ['sometimes', 'required', 'string', 'in:monthly,quarterly,annually'],
                    // Other fields should be null for deposit products
                    'repayment_frequency' => ['prohibited'],
                    'amortization_type' => ['prohibited'],
                    'grace_period_days' => ['prohibited'],
                    'late_payment_penalty_rate' => ['prohibited']
                ];

            case 'loan':
                return [
                    'minimum_amount' => ['sometimes', 'required', 'numeric', 'min:0', 'lt:maximum_amount'],
                    'maximum_amount' => ['sometimes', 'required', 'numeric', 'min:0'],
                    'interest_rate' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
                    'interest_rate_type' => ['sometimes', 'required', 'string', 'in:fixed,variable'],
                    'repayment_frequency' => ['sometimes', 'required', 'string', 'in:daily,weekly,monthly,annually'],
                    'amortization_type' => ['sometimes', 'required', 'string', 'in:flat,declining_balance'],
                    'grace_period_days' => ['sometimes', 'required', 'integer', 'min:0'],
                    'late_payment_penalty_rate' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
                    // Interest calculation/posting not applicable for loans
                    'interest_calculation_frequency' => ['prohibited'],
                    'interest_posting_frequency' => ['prohibited']
                ];

            case 'wallet':
            case 'escrow':
                return [
                    'minimum_amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'lt:maximum_amount'],
                    'maximum_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
                    // Other fields should be null for wallet/escrow products
                    'interest_rate' => ['prohibited'],
                    'interest_rate_type' => ['prohibited'],
                    'interest_calculation_frequency' => ['prohibited'],
                    'interest_posting_frequency' => ['prohibited'],
                    'repayment_frequency' => ['prohibited'],
                    'amortization_type' => ['prohibited'],
                    'grace_period_days' => ['prohibited'],
                    'late_payment_penalty_rate' => ['prohibited']
                ];

            default:
                return [];
        }
    }

    public function messages(): array
    {
        return [
            'product_name.unique' => 'This product name is already in use.',
            'minimum_amount.lt' => 'The minimum amount must be less than the maximum amount.',
            'minimum_amount.required' => 'The minimum amount is required for :input products.',
            'maximum_amount.required' => 'The maximum amount is required for :input products.',
            'interest_rate.required' => 'The interest rate is required for :input products.',
            'interest_rate.max' => 'The interest rate cannot exceed 100%.',
            'interest_rate_type.required' => 'The interest rate type is required for :input products.',
            'interest_calculation_frequency.required' => 'The interest calculation frequency is required for deposit products.',
            'interest_posting_frequency.required' => 'The interest posting frequency is required for deposit products.',
            'repayment_frequency.required' => 'The repayment frequency is required for loan products.',
            'amortization_type.required' => 'The amortization type is required for loan products.',
            'grace_period_days.required' => 'The grace period is required for loan products.',
            'late_payment_penalty_rate.required' => 'The late payment penalty rate is required for loan products.',
            'late_payment_penalty_rate.max' => 'The late payment penalty rate cannot exceed 100%.',
            'prohibited' => 'This field is not applicable for the selected product type.'
        ];
    }
}