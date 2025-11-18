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