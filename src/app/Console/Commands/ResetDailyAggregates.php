<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\TransactionAggregate;
use Illuminate\Console\Command;

class ResetDailyAggregates extends Command
{
    protected $signature = 'aggregates:reset';
    protected $description = 'Create new aggregate records for all accounts for the new day';

    public function handle()
    {
        $today = now()->format('Y-m-d');

        // Create new records for all active accounts
        Account::chunk(1000, function ($accounts) use ($today) {
            foreach ($accounts as $account) {
                TransactionAggregate::create([
                    'account_id' => $account->id,
                    'aggregated_daily_amount' => 0,
                    'date' => $today
                ]);
            }
        });

        $this->info('Daily aggregates have been reset successfully.');
    }
}