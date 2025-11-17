<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'account_id' => Account::factory(),
            'transaction_type' => $this->faker->randomElement(['deposit', 'withdrawal']),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'currency' => 'USD',
            'status' => 'completed',
            'description' => $this->faker->sentence(),
            'reference' => Str::random(10)
        ];
    }
}