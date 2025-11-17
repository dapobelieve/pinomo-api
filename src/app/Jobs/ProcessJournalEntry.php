<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\JournalEntry;
use App\Models\GLAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ProcessJournalEntry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $transaction;

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function handle()
    {
        DB::transaction(function () {
            $entry = new JournalEntry([
                'entry_date' => $this->transaction->created_at,
                'reference_type' => 'transaction',
                'reference_id' => $this->transaction->id,
                'currency' => $this->transaction->currency,
                'description' => $this->transaction->description,
                'status' => JournalEntry::STATUS_DRAFT,
                'created_by_user_id' => $this->transaction->created_by_user_id,
            ]);
            $entry->save();

            match($this->transaction->transaction_type) {
                Transaction::TYPE_DEPOSIT => $this->processDeposit($entry),
                Transaction::TYPE_WITHDRAWAL => $this->processWithdrawal($entry),
                Transaction::TYPE_CHARGE => $this->processCharge($entry),
                Transaction::TYPE_TRANSFER => $this->processTransfer($entry),
                default => null
            };

            if ($entry->isBalanced()) {
                $entry->post($this->transaction->created_by_user_id);
                Redis::publish('journal_entries.created', json_encode([
                    'id' => $entry->id,
                    'transaction_id' => $this->transaction->id,
                    'type' => $this->transaction->transaction_type,
                    'amount' => $this->transaction->amount,
                    'currency' => $this->transaction->currency
                ]));
            }
        });
    }

    protected function processDeposit(JournalEntry $entry)
    {
        $entry->items()->create([
            'gl_account_id' => $this->transaction->sourceAccount->gl_account_id,
            'debit_amount' => $this->transaction->amount,
            'credit_amount' => 0,
            'description' => 'Deposit to ' . $this->transaction->sourceAccount->account_number
        ]);

        $entry->items()->create([
            'gl_account_id' => GLAccount::where('account_code', 'CUSTOMER_DEPOSITS')->first()->id,
            'debit_amount' => 0,
            'credit_amount' => $this->transaction->amount,
            'description' => 'Customer deposit'
        ]);
    }

    protected function processWithdrawal(JournalEntry $entry)
    {
        $entry->items()->create([
            'gl_account_id' => GLAccount::where('account_code', 'CUSTOMER_DEPOSITS')->first()->id,
            'debit_amount' => $this->transaction->amount,
            'credit_amount' => 0,
            'description' => 'Customer withdrawal'
        ]);

        $entry->items()->create([
            'gl_account_id' => $this->transaction->sourceAccount->gl_account_id,
            'debit_amount' => 0,
            'credit_amount' => $this->transaction->amount,
            'description' => 'Withdrawal from ' . $this->transaction->sourceAccount->account_number
        ]);
    }

    protected function processCharge(JournalEntry $entry)
    {
        $entry->items()->create([
            'gl_account_id' => $this->transaction->sourceAccount->gl_account_id,
            'debit_amount' => $this->transaction->amount,
            'credit_amount' => 0,
            'description' => 'Charge from ' . $this->transaction->sourceAccount->account_number
        ]);

        $entry->items()->create([
            'gl_account_id' => GLAccount::where('account_code', 'FEE_INCOME')->first()->id,
            'debit_amount' => 0,
            'credit_amount' => $this->transaction->amount,
            'description' => 'Fee income'
        ]);
    }

    protected function processTransfer(JournalEntry $entry)
    {
        $entry->items()->create([
            'gl_account_id' => $this->transaction->sourceAccount->gl_account_id,
            'debit_amount' => $this->transaction->amount,
            'credit_amount' => 0,
            'description' => 'Transfer from ' . $this->transaction->sourceAccount->account_number
        ]);

        $entry->items()->create([
            'gl_account_id' => $this->transaction->destinationAccount->gl_account_id,
            'debit_amount' => 0,
            'credit_amount' => $this->transaction->amount,
            'description' => 'Transfer to ' . $this->transaction->destinationAccount->account_number
        ]);
    }
}