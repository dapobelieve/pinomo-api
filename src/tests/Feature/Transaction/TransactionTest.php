<?php

namespace Tests\Feature\Transaction;

use Tests\TestCase;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionAggregate;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    protected $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();
        $this->account = Account::factory()->create([
            'balance' => 10000,
            'single_transaction_limit' => 5000
        ]);
    }

    public function test_can_make_deposit()
    {
        $response = $this->postJson('/api/v1/transactions/deposits', [
            'account_id' => $this->account->id,
            'amount' => 1000,
            'currency' => 'USD',
            'description' => 'Test deposit'
        ]);

        $response->assertStatus(201);
        $this->assertEquals(11000, $this->account->fresh()->balance);
    }

    public function test_can_make_withdrawal_within_limits()
    {
        $response = $this->postJson('/api/v1/transactions/withdrawals', [
            'account_id' => $this->account->id,
            'amount' => 1000,
            'currency' => 'USD',
            'description' => 'Test withdrawal'
        ]);

        $response->assertStatus(201);
        $this->assertEquals(9000, $this->account->fresh()->balance);
    }

    public function test_cannot_exceed_single_transaction_limit()
    {
        $response = $this->postJson('/api/v1/transactions/withdrawals', [
            'account_id' => $this->account->id,
            'amount' => 6000,
            'currency' => 'USD',
            'description' => 'Exceeding limit'
        ]);

        $response->assertStatus(422);
        $this->assertEquals(10000, $this->account->fresh()->balance);
    }

    public function test_cannot_exceed_daily_transaction_limit()
    {
        TransactionAggregate::factory()->create([
            'account_id' => $this->account->id,
            'aggregated_daily_amount' => 9000,
            'date' => now()->toDateString()
        ]);

        $response = $this->postJson('/api/v1/transactions/withdrawals', [
            'account_id' => $this->account->id,
            'amount' => 2000,
            'currency' => 'USD',
            'description' => 'Exceeding daily limit'
        ]);

        $response->assertStatus(422);
        $this->assertEquals(10000, $this->account->fresh()->balance);
    }

    public function test_can_view_transaction_history()
    {
        Transaction::factory()->count(5)->create([
            'account_id' => $this->account->id
        ]);

        $response = $this->getJson("/api/v1/transactions?account_id={$this->account->id}");

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }
}