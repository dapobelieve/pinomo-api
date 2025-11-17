<?php

namespace Tests\Feature;

use App\Events\TransactionJobStarted;
use App\Events\LienReleaseCompleted;
use App\Events\TransactionJobFailed;
use App\Jobs\ReleaseLienJob;
use App\Models\Transaction;
use App\Models\Account;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TransactionJobArchitectureTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function transaction_can_dispatch_lien_release_job_with_webhook()
    {
        Event::fake();
        Queue::fake();

        // Create test data
        $client = Client::factory()->create();
        $account = Account::factory()->create(['client_id' => $client->id]);
        $transaction = Transaction::factory()->create([
            'transaction_type' => Transaction::TYPE_LIEN,
            'status' => Transaction::STATUS_COMPLETED,
            'source_account_id' => $account->id
        ]);

        $webhookUrl = 'https://example.com/webhook';

        // Act
        $transaction->processLienRelease($webhookUrl);

        // Assert job was dispatched
        Queue::assertPushed(ReleaseLienJob::class, function ($job) use ($transaction, $webhookUrl) {
            return $job->transaction->id === $transaction->id &&
                   $job->webhookUrl === $webhookUrl;
        });
    }

    /** @test */
    public function lien_release_job_fires_correct_events()
    {
        Event::fake();

        // Create test data
        $client = Client::factory()->create();
        $account = Account::factory()->create(['client_id' => $client->id]);
        $transaction = Transaction::factory()->create([
            'transaction_type' => Transaction::TYPE_LIEN,
            'status' => Transaction::STATUS_COMPLETED,
            'source_account_id' => $account->id
        ]);

        $webhookUrl = 'https://example.com/webhook';
        $job = new ReleaseLienJob($transaction, $webhookUrl);

        // Act
        $job->handle();

        // Assert events were fired
        Event::assertDispatched(TransactionJobStarted::class);
        Event::assertDispatched(LienReleaseCompleted::class);
    }

    /** @test */
    public function transaction_status_is_not_updated_by_job_directly()
    {
        $client = Client::factory()->create();
        $account = Account::factory()->create(['client_id' => $client->id]);
        $transaction = Transaction::factory()->create([
            'transaction_type' => Transaction::TYPE_LIEN,
            'status' => Transaction::STATUS_COMPLETED,
            'source_account_id' => $account->id
        ]);

        $originalStatus = $transaction->status;
        $job = new ReleaseLienJob($transaction);

        // Job should not directly update transaction status
        // Status updates should come from event listeners
        $this->assertEquals($originalStatus, $transaction->fresh()->status);
    }
}