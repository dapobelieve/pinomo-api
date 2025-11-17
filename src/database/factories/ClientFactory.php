<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'type' => $this->faker->randomElement(['individual', 'business']),
            'status' => 'active'
        ];
    }
}