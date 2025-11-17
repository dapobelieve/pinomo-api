<?php

namespace App\Jobs;

use App\Models\Charge;
use App\Models\JournalEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessChargeAccounting implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $charge;
    protected $accountId;
    protected $amount;

    public function __construct(Charge $charge, string $accountId, float $amount)
    {
        $this->charge = $charge;
        $this->accountId = $accountId;
        $this->amount = $amount;
    }

    public function handle()
    {
        DB::transaction(function () {
            $entry = new JournalEntry([
                'entry_date' => now(),
                'reference_type' => 'charge',
                'reference_id' => $this->charge->id,
                'currency' => $this->charge->currency,
                'description' => 'Charge: ' . $this->charge->name,
                'status' => JournalEntry::STATUS_DRAFT
            ]);
            $entry->save();

            // Create journal entry items
            $entry->items()->create([
                'gl_account_id' => $this->charge->gl_receivable_account_id,
                'debit_amount' => $this->amount,
                'credit_amount' => 0,
                'description' => 'Charge receivable'
            ]);

            $entry->items()->create([
                'gl_account_id' => $this->charge->gl_income_account_id,
                'debit_amount' => 0,
                'credit_amount' => $this->amount,
                'description' => 'Fee income'
            ]);

            if ($entry->isBalanced()) {
                $entry->post($this->charge->created_by_user_id);
            }
        });
    }
}