<?php

namespace Database\Seeders;

use App\Models\Charge;
use App\Models\GLAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class ChargeSeeder extends Seeder
{
    public function run(): void
    {
        $transactionFeesGL = GLAccount::where('account_code', '401100')->first();

        if (!$transactionFeesGL) {
            $this->command->error('✗ Transaction Fees GL account (401100) not found. Please run GLAccountSeeder first.');
            Log::error('ChargeSeeder failed: Transaction Fees GL account not found');
            return;
        }

        $this->createTransferCommissionCharge($transactionFeesGL);
    }

    private function createTransferCommissionCharge(GLAccount $glAccount): void
    {
        $existingCharge = Charge::where('name', 'Transfer Commission - AED')
            ->where('currency', 'AED')
            ->first();

        if ($existingCharge) {
            Log::info('Transfer Commission - AED charge already exists', ['id' => $existingCharge->id]);
            $this->command->info('✓ Transfer Commission - AED charge already exists');
            return;
        }

        $charge = Charge::create([
            'name' => 'Transfer Commission - AED',
            'charge_type' => 'percentage',
            'percentage' => 1.5000,
            'amount' => null,
            'currency' => 'AED',
            'description' => '1.5% commission applied to all wallet transfer transactions in AED.',
            'is_active' => true,
            'gl_income_account_id' => $glAccount->id,
        ]);

        $charge->update(['txn_type' => 'transfer']);

        Log::info('Transfer Commission - AED charge created', ['id' => $charge->id]);
        $this->command->info('✓ Transfer Commission - AED charge created');
    }
}
