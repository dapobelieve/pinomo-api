<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Events\TransactionJobStarted;
use App\Events\LienReleaseCompleted;
use App\Events\TransactionJobFailed;
use App\Jobs\WebhookJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReleaseLienJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [30, 60, 120];

    protected Transaction $transaction;
    protected ?string $webhookUrl;
    protected string $jobId;

    public function __construct(Transaction $transaction, ?string $webhookUrl = null)
    {
        $this->transaction = $transaction;
        $this->webhookUrl = $webhookUrl;
        $this->jobId = Str::uuid()->toString();
    }

    public function handle(): void
    {

        Log::info('Processing lien release job', [
            'job_id' => $this->jobId,
            'transaction_id' => $this->transaction->id
        ]);

        try {
            DB::beginTransaction();

            // Process the lien release - pure business logic
            $releaseData = [
                'transaction_reference' => 'REL-' . $this->jobId,
                'processing_type' => 'intra'
            ];
            
            $release = $this->transaction->createLienRelease($releaseData);

            DB::commit();

            Log::info('Lien release completed successfully', [
                'job_id' => $this->jobId,
                'transaction_id' => $this->transaction->id,
                'release_transaction_id' => $release->id
            ]);

            if ($this->webhookUrl) {
                WebhookJob::dispatch($this->webhookUrl, [
                    'event' => 'lien_release_completed',
                    'job_id' => $this->jobId,
                    'transaction_id' => $this->transaction->id,
                    'release_transaction_id' => $release->id,
                    'status' => 'completed',
                    'completed_at' => now()->toISOString()
                ]);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Lien release job failed', [
                'job_id' => $this->jobId,
                'transaction_id' => $this->transaction->id,
                'error' => $e->getMessage()
            ]);

            if ($this->webhookUrl) {
                WebhookJob::dispatch($this->webhookUrl, [
                    'event' => 'transaction_job_failed',
                    'job_id' => $this->jobId,
                    'job_type' => 'lien_release',
                    'transaction_id' => $this->transaction->id,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toISOString()
                ]);
            }

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Lien release job permanently failed', [
            'job_id' => $this->jobId,
            'transaction_id' => $this->transaction->id,
            'error' => $exception->getMessage()
        ]);


        // Dispatch webhook job for permanent failure if webhook URL provided
        if ($this->webhookUrl) {
            WebhookJob::dispatch($this->webhookUrl, [
                'event' => 'transaction_job_failed',
                'job_id' => $this->jobId,
                'job_type' => 'lien_release',
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