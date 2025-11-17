<?php

namespace Tests\Feature\Account;

use Tests\TestCase;
use App\Models\Account;
use App\Models\Client;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();
    }

    public function test_can_create_account()
    {
        $client = Client::factory()->create();
        $product = Product::factory()->create();

        $response = $this->postJson('/api/v1/accounts', [
            'client_id' => $client->id,
            'product_id' => $product->id,
            'currency' => 'USD'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'account_number', 'balance']
            ]);
    }

    public function test_can_activate_account()
    {
        $account = Account::factory()->create(['status' => 'inactive']);

        $response = $this->postJson("/api/v1/accounts/{$account->id}/activate");

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'active']);
    }

    public function test_can_suspend_account()
    {
        $account = Account::factory()->create(['status' => 'active']);

        $response = $this->postJson("/api/v1/accounts/{$account->id}/suspend", [
            'reason' => 'Suspicious activity'
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'suspended']);
    }

    public function test_can_update_transaction_limit()
    {
        $account = Account::factory()->create();

        $response = $this->patchJson("/api/v1/accounts/{$account->id}/transaction-limit", [
            'single_transaction_limit' => 5000
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['single_transaction_limit' => '5000.00']);
    }
}