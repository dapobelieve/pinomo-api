<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\Account;
use App\Events\ReleaseAndWithdrawCompleted;
use App\Events\TransactionJobFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ReleaseAndWithdrawJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Transaction $transaction;
    protected array $withdrawalData;
    protected ?string $webhookUrl;
    protected string $jobId;

    public function __construct(Transaction $transaction, array $withdrawalData, ?string $webhookUrl = null)
    {
        $this->transaction = $transaction;
        $this->withdrawalData = $withdrawalData;
        $this->webhookUrl = $webhookUrl;
        $this->jobId = Str::uuid()->toString();
    }

    public function handle(): void
    {

        Log::info('Processing release and withdraw job', [
            'job_id' => $this->jobId,
            'transaction_id' => $this->transaction->id
        ]);

        try {
            DB::beginTransaction();

            $account = Account::lockForUpdate()
                ->where('id', $this->transaction->source_account_id)
                ->first();

            if (!$account) {
                throw new \Exception('Account not found');
            }

            // Process the release and withdraw - pure business logic
            $releaseAndWithdraw = $this->transaction->createReleaseAndWithdraw($account, $this->withdrawalData);

            $withdrawalTransaction = $releaseAndWithdraw['withdrawal'];
            $releaseTransaction = $releaseAndWithdraw['release'];

            DB::commit();

            Log::info('Release and withdraw completed successfully', [
                'job_id' => $this->jobId,
                'transaction_id' => $this->transaction->id,
                'withdrawal_transaction_id' => $withdrawalTransaction->id,
                'release_transaction_id' => $releaseTransaction->id
            ]);

            $responseData = [
                'withdrawal_transaction_id' => $withdrawalTransaction->id,
                'release_transaction_id' => $releaseTransaction->id,
                'withdrawal_status' => $withdrawalTransaction->status,
                'release_status' => $releaseTransaction->status,
                'balances' => [
                    'ledger' => [
                        'before' => $withdrawalTransaction->source_ledger_balance_before,
                        'after' => $withdrawalTransaction->source_ledger_balance_after
                    ],
                    'available' => [
                        'before' => $withdrawalTransaction->source_available_balance_before,
                        'after' => $withdrawalTransaction->source_available_balance_after
                    ],
                    'locked' => [
                        'before' => $withdrawalTransaction->source_locked_balance_before,
                        'after' => $releaseTransaction->source_locked_balance_after
                    ]
                ]
            ];

            if ($this->webhookUrl) {
                Log::info('webhook');
                WebhookJob::dispatch($this->webhookUrl, [
                    'event' => 'release_and_withdraw_completed',
                    'job_id' => $this->jobId,
                    'transaction_id' => $this->transaction->id,
                    'status' => 'completed',
                    'data' => $responseData,
                    'completed_at' => now()->toISOString()
                ]);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Release and withdraw job failed', [
                'job_id' => $this->jobId,
                'transaction_id' => $this->transaction->id,
                'error' => $e->getMessage()
            ]);

            if ($this->webhookUrl) {
                WebhookJob::dispatch($this->webhookUrl, [
                    'event' => 'transaction_job_failed',
                    'job_id' => $this->jobId,
                    'job_type' => 'release_and_withdraw',
                    'transaction_id' => $this->transaction->id,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toISOString()
                ]);
            }

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Release and withdraw job permanently failed', [
            'job_id' => $this->jobId,
            'transaction_id' => $this->transaction->id,
            'error' => $exception->getMessage()
        ]);

        if ($this->webhookUrl) {
            WebhookJob::dispatch($this->webhookUrl, [
                'event' => 'transaction_job_failed',
                'job_id' => $this->jobId,
                'job_type' => 'release_and_withdraw',
                'transaction_id' => $this->transaction->id,
                'status' => 'permanently_failed',
                'error' => $exception->getMessage(),
                'failed_at' => now()->toISOString()
            ]);
        }

    }

    public function uniqueId(): string
    {
        return $this->jobId;
    }
}