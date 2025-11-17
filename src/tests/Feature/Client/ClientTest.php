<?php

namespace Tests\Feature\Client;

use Tests\TestCase;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();
    }

    public function test_can_list_clients()
    {
        Client::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/clients');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_client()
    {
        $clientData = [
            'name' => 'Test Client',
            'email' => 'client@example.com',
            'phone' => '+1234567890',
            'address' => '123 Test St',
            'type' => 'individual'
        ];

        $response = $this->postJson('/api/v1/clients', $clientData);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Test Client']);
    }

    public function test_can_view_client()
    {
        $client = Client::factory()->create();

        $response = $this->getJson("/api/v1/clients/{$client->id}");

        $response->assertStatus(200)
            ->assertJson(['data' => ['id' => $client->id]]);
    }

    public function test_can_update_client()
    {
        $client = Client::factory()->create();

        $response = $this->putJson("/api/v1/clients/{$client->id}", [
            'name' => 'Updated Name'
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Name']);
    }

    public function test_can_delete_client()
    {
        $client = Client::factory()->create();

        $response = $this->deleteJson("/api/v1/clients/{$client->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($client);
    }
}