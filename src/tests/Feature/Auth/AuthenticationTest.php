<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register()
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email'],
                'token'
            ]);
    }

    public function test_user_can_login()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'password'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token']);
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password'
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_logout()
    {
        $user = $this->createAuthenticatedUser();

        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(200);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id
        ]);
    }
}