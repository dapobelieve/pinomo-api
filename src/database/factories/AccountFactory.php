<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Client;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'account_number' => $this->faker->unique()->numerify('1########'),
            'client_id' => Client::factory(),
            'product_id' => Product::factory(),
            'account_name' => $this->faker->words(3, true),
            'currency' => 'USD',
            'available_balance' => 0,
            'actual_balance' => 0,
            'locked_amount' => 0,
            'status' => 'active',
            'single_transaction_limit' => 5000
        ];
    }
}