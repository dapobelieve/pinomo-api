<?php

namespace App\Console\Commands;

use App\Models\TransactionAggregate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AggregatesResetCommand extends Command
{
    protected $signature = 'aggregates:reset';
    protected $description = 'Reset transaction aggregates for the new day';

    public function handle()
    {
        $this->info('Starting transaction aggregates reset...');

        try {
            // Delete aggregates older than 30 days
            $deleted = TransactionAggregate::where('date', '<', now()->subDays(30)->format('Y-m-d'))
                ->delete();

            $this->info("Deleted {$deleted} old aggregate records.");
            Log::info("Successfully deleted {$deleted} old transaction aggregate records.");
        } catch (\Exception $e) {
            Log::error('Failed to reset transaction aggregates: ' . $e->getMessage());
            $this->error('Failed to reset transaction aggregates: ' . $e->getMessage());
        }
    }
}