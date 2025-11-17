<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;

abstract class TestCase extends BaseTestCase
{
    use WithFaker;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling();
    }

    protected function createAuthenticatedUser(array $attributes = [])
    {
        $user = User::factory()->create($attributes);
        $this->actingAs($user);
        return $user;
    }
}
