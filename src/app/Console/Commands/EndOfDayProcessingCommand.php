<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionAggregate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EndOfDayProcessingCommand extends Command
{
    protected $signature = 'banking:end-of-day';
    protected $description = 'Run end-of-day processing including interest calculations and transaction aggregation';

    public function handle()
    {
        $this->info('Starting end-of-day processing...');

        try {
            DB::beginTransaction();

            // 1. Process daily interest for savings accounts
            $this->processInterest();

            // 2. Aggregate daily transactions
            $this->aggregateTransactions();

            // 3. Generate daily balance snapshots
            $this->generateBalanceSnapshots();

            DB::commit();
            $this->info('End-of-day processing completed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('End-of-day processing failed: ' . $e->getMessage());
            $this->error('End-of-day processing failed: ' . $e->getMessage());
        }
    }

    private function processInterest()
    {
        $this->info('Processing daily interest...');
        
        // Get all active savings accounts
        $accounts = Account::where('status', Account::STATUS_ACTIVE)
            ->whereHas('product', function ($query) {
                $query->where('interest_rate', '>', 0);
            })
            ->get();

        foreach ($accounts as $account) {
            $dailyRate = $account->product->interest_rate / 365;
            $interest = $account->current_balance * $dailyRate;

            if ($interest > 0) {
                // Create interest credit transaction
                Transaction::createDeposit(
                    $account,
                    $interest,
                    'Daily Interest Credit',
                    ['type' => 'interest_credit']
                );
            }
        }
    }

    private function aggregateTransactions()
    {
        $this->info('Aggregating daily transactions...');

        $today = now()->format('Y-m-d');
        
        // Get all transactions for the day
        $transactions = Transaction::whereDate('created_at', $today)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->get()
            ->groupBy('source_account_id');

        foreach ($transactions as $accountId => $accountTransactions) {
            TransactionAggregate::create([
                'account_id' => $accountId,
                'date' => $today,
                'total_credits' => $accountTransactions->where('transaction_type', Transaction::TYPE_DEPOSIT)->sum('amount'),
                'total_debits' => $accountTransactions->where('transaction_type', Transaction::TYPE_WITHDRAWAL)->sum('amount'),
                'transaction_count' => $accountTransactions->count()
            ]);
        }
    }

    private function generateBalanceSnapshots()
    {
        $this->info('Generating balance snapshots...');

        $accounts = Account::where('status', Account::STATUS_ACTIVE)->get();

        foreach ($accounts as $account) {
            $account->balanceHistories()->create([
                'date' => now()->format('Y-m-d'),
                'ledger_balance' => $account->current_balance,
                'available_balance' => $account->available_balance,
                'locked_balance' => $account->locked_balance
            ]);
        }
    }
}