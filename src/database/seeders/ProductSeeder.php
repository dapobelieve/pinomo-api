<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $this->createDefaultUSDWallet();
        $this->createDefaultAEDWallet();
    }

    private function createDefaultUSDWallet(): void
    {
        $existingWallet = Product::where('product_type', 'wallet')
            ->where('product_name', 'Default Wallet')
            ->where('currency', 'USD')
            ->first();

        if ($existingWallet) {
            Log::info('Default USD Wallet product already exists', ['id' => $existingWallet->id]);
            $this->command->info('✓ Default USD Wallet product already exists');
            return;
        }

        $walletProduct = Product::create([
            'product_name' => 'Default Wallet',
            'product_type' => 'wallet',
            'currency' => 'USD',
            'minimum_amount' => 0.00,
            'maximum_amount' => null,
            'interest_rate' => null,
            'interest_rate_type' => null,
            'interest_calculation_frequency' => null,
            'interest_posting_frequency' => null,
            'repayment_frequency' => null,
            'amortization_type' => null,
            'grace_period_days' => null,
            'late_payment_penalty_rate' => null,
            'description' => 'Default digital wallet product for basic transactions and payments. No interest earned, no minimum balance required.',
            'is_active' => true,
        ]);

        Log::info('Default USD Wallet product created', ['id' => $walletProduct->id]);
        $this->command->info('✓ Default USD Wallet product created');
    }

    private function createDefaultAEDWallet(): void
    {
        $existingWallet = Product::where('product_type', 'wallet')
            ->where('product_name', 'Default AED Wallet')
            ->where('currency', 'AED')
            ->first();

        if ($existingWallet) {
            Log::info('Default AED Wallet product already exists', ['id' => $existingWallet->id]);
            $this->command->info('✓ Default AED Wallet product already exists');
            return;
        }

        $walletProduct = Product::create([
            'product_name' => 'Default AED Wallet',
            'product_type' => 'wallet',
            'currency' => 'AED',
            'minimum_amount' => 0.00,
            'maximum_amount' => null,
            'interest_rate' => null,
            'interest_rate_type' => null,
            'interest_calculation_frequency' => null,
            'interest_posting_frequency' => null,
            'repayment_frequency' => null,
            'amortization_type' => null,
            'grace_period_days' => null,
            'late_payment_penalty_rate' => null,
            'description' => 'Default digital wallet product for basic transactions and payments in UAE Dirham. No interest earned, no minimum balance required.',
            'is_active' => true,
        ]);

        Log::info('Default AED Wallet product created', ['id' => $walletProduct->id]);
        $this->command->info('✓ Default AED Wallet product created');
    }
}
