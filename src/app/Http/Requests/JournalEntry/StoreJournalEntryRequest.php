<?php

namespace App\Http\Requests\JournalEntry;

use Illuminate\Foundation\Http\FormRequest;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'entry_date' => ['required', 'date'],
            'reference_type' => ['nullable', 'string', 'max:255'],
            'reference_id' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'description' => ['nullable', 'string', 'max:1000'],
            
            'items' => ['required', 'array', 'min:2'],
            'items.*.gl_account_id' => ['required', 'uuid', 'exists:gl_accounts,id'],
            'items.*.debit_amount' => ['required_without:items.*.credit_amount', 'numeric', 'min:0', 'max:999999999999999.9999'],
            'items.*.credit_amount' => ['required_without:items.*.debit_amount', 'numeric', 'min:0', 'max:999999999999999.9999'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Check that each item has either debit or credit amount, but not both
            foreach ($this->items ?? [] as $index => $item) {
                if (isset($item['debit_amount']) && isset($item['credit_amount']) 
                    && $item['debit_amount'] > 0 && $item['credit_amount'] > 0) {
                    $validator->errors()->add(
                        "items.{$index}",
                        'An item cannot have both debit and credit amounts'
                    );
                }
            }

            // Check that total debits equals total credits
            $totalDebits = collect($this->items ?? [])
                ->sum(function ($item) {
                    return $item['debit_amount'] ?? 0;
                });

            $totalCredits = collect($this->items ?? [])
                ->sum(function ($item) {
                    return $item['credit_amount'] ?? 0;
                });

            if (bccomp($totalDebits, $totalCredits, 4) !== 0) {
                $validator->errors()->add(
                    'items',
                    'Total debits must equal total credits'
                );
            }
        });
    }
}