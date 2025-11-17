<?php

namespace App\Http\Requests\JournalEntry;

use App\Models\JournalEntry;
use Illuminate\Foundation\Http\FormRequest;

class UpdateJournalEntryRequest extends FormRequest
{
    public function authorize()
    {
        $journalEntry = $this->route('journal_entry');
        return $journalEntry->status === JournalEntry::STATUS_DRAFT;
    }

    public function rules()
    {
        return [
            'entry_date' => ['sometimes', 'date'],
            'reference_type' => ['nullable', 'string', 'max:255'],
            'reference_id' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            
            'items' => ['sometimes', 'array', 'min:2'],
            'items.*.id' => ['sometimes', 'uuid', 'exists:journal_entry_items,id'],
            'items.*.gl_account_id' => ['required_with:items', 'uuid', 'exists:gl_accounts,id'],
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
            if ($this->has('items')) {
                $totalDebits = collect($this->items)
                    ->sum(function ($item) {
                        return $item['debit_amount'] ?? 0;
                    });

                $totalCredits = collect($this->items)
                    ->sum(function ($item) {
                        return $item['credit_amount'] ?? 0;
                    });

                if (bccomp($totalDebits, $totalCredits, 4) !== 0) {
                    $validator->errors()->add(
                        'items',
                        'Total debits must equal total credits'
                    );
                }
            }
        });
    }
}