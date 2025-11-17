<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'product_name' => $this->faker->unique()->words(2, true),
            'product_type' => $this->faker->randomElement(['deposit', 'wallet']),
            'currency' => 'USD',
            'minimum_amount' => 0,
            'maximum_amount' => 1000000,
            'interest_rate' => 0,
            'status' => 'active'
        ];
    }
}