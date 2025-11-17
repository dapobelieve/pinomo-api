<?php

namespace App\Jobs;

use App\Models\TransactionAggregate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ProcessTransactionAggregate implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private string $accountId,
        private float $amount,
        private readonly string $transactionType
    ) {
    }

    public function handle(): void
    {
        // Skip lien transactions from aggregate calculations
        if ($this->transactionType === 'lien' || $this->transactionType === 'lien_release') {
            return;
        }


        $today = now()->format('Y-m-d');

        try {
            DB::transaction(
                function () use ($today) {
                    $aggregate = TransactionAggregate::firstOrCreate(
                        [
                        'account_id' => $this->accountId,
                        'date' => $today
                        ],
                        [
                        'aggregated_daily_amount' => 0,
                        'collections_amount' => 0,
                        'disbursements_amount' => 0
                        ]
                    );

                    // Track collections and disbursements separately
                    if ($this->transactionType === 'deposit') {
                        $aggregate->increment('collections_amount', $this->amount);
                    } elseif (in_array($this->transactionType, ['withdrawal', 'charge'])) {
                        $aggregate->increment('disbursements_amount', $this->amount);
                    }

                    // Keep existing aggregated_daily_amount logic for backward compatibility
                    $aggregate->increment('aggregated_daily_amount', $this->amount);
                }
            );

            // Only update Redis after successful DB transaction
            $aggregate = TransactionAggregate::where('account_id', $this->accountId)
                ->where('date', $today)
                ->first();

            if ($aggregate) {
                // Cache total aggregated amount
                $totalKey = "daily_aggregate:$this->accountId:{$today}";
                Redis::set($totalKey, $aggregate->aggregated_daily_amount);
                Redis::expire($totalKey, 86400);

                // Cache collections amount
                $collectionsKey = "daily_collections:{$this->accountId}:{$today}";
                Redis::set($collectionsKey, $aggregate->collections_amount);
                Redis::expire($collectionsKey, 86400);

                // Cache disbursements amount
                $disbursementsKey = "daily_disbursements:{$this->accountId}:{$today}";
                Redis::set($disbursementsKey, $aggregate->disbursements_amount);
                Redis::expire($disbursementsKey, 86400);
            }
        } catch (Throwable $e) {
            // Handle or log the error
            throw $e;
        }
    }
}
