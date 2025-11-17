<?php

namespace Database\Seeders;

use App\Models\GLAccount;
use Illuminate\Database\Seeder;

class GLAccountSeeder extends Seeder
{
    public function run(): void
    {
        // Asset Accounts (1xxxxx)
        $assets = GLAccount::firstOrCreate(
            ['account_code' => '100000'],
            [
                'account_name' => 'Assets',
                'account_type' => GLAccount::TYPE_ASSET,
                'description' => 'Asset accounts',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );

        // Cash and Bank Accounts
        $cashAndBank = GLAccount::firstOrCreate(
            ['account_code' => '101000'],
            [
                'account_name' => 'Cash and Bank',
                'account_type' => GLAccount::TYPE_ASSET,
                'parent_account_id' => $assets->id,
                'description' => 'Cash and bank accounts',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );

        GLAccount::firstOrCreate(
            ['account_code' => '101100'],
            [
                'account_name' => 'Cash in Hand',
                'account_type' => GLAccount::TYPE_ASSET,
                'parent_account_id' => $cashAndBank->id,
                'description' => 'Physical cash',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );

        GLAccount::firstOrCreate(
            ['account_code' => '101200'],
            [
                'account_name' => 'Bank Account - Operations',
                'account_type' => GLAccount::TYPE_ASSET,
                'parent_account_id' => $cashAndBank->id,
                'description' => 'Main operational bank account',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );

        // Customer Accounts
        $customerAccounts = GLAccount::firstOrCreate(
            ['account_code' => '102000'],
            [
                'account_name' => 'Customer Accounts',
                'account_type' => GLAccount::TYPE_ASSET,
                'parent_account_id' => $assets->id,
                'description' => 'Customer account balances',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );

        GLAccount::firstOrCreate(
            ['account_code' => '102100'],
            [
                'account_name' => 'Customer Deposits',
                'account_type' => GLAccount::TYPE_ASSET,
                'parent_account_id' => $customerAccounts->id,
                'description' => 'Customer deposit accounts',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );

        GLAccount::firstOrCreate(
            ['account_code' => '102200'],
            [
                'account_name' => 'Customer Overdrafts',
                'account_type' => GLAccount::TYPE_ASSET,
                'parent_account_id' => $customerAccounts->id,
                'description' => 'Customer overdraft balances',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );

        // Receivables
        $receivables = GLAccount::firstOrCreate(
            ['account_code' => '103000'],
            [
                'account_name' => 'Receivables',
                'account_type' => GLAccount::TYPE_ASSET,
                'parent_account_id' => $assets->id,
                'description' => 'Amounts receivable',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );

        GLAccount::firstOrCreate(
            ['account_code' => '103100'],
            [
                'account_name' => 'Fee Receivables',
                'account_type' => GLAccount::TYPE_ASSET,
                'parent_account_id' => $receivables->id,
                'description' => 'Fees receivable from customers',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );

        // Liability Accounts (2xxxxx)
        $liabilities = GLAccount::firstOrCreate(
            ['account_code' => '200000'],
            [
                'account_name' => 'Liabilities',
                'account_type' => GLAccount::TYPE_LIABILITY,
                'currency' => 'AED',
                'description' => 'Liability accounts',
                'is_active' => true,
            ]
        );

        // Customer Deposits
        $customerDeposits = GLAccount::firstOrCreate(
            ['account_code' => '201000'],
            [
                'account_name' => 'Customer Deposits',
                'account_type' => GLAccount::TYPE_LIABILITY,
                'parent_account_id' => $liabilities->id,
                'description' => 'Customer deposit liabilities',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );

        GLAccount::firstOrCreate(
            ['account_code' => '201100'],
            [
                'account_name' => 'Current Accounts',
                'account_type' => GLAccount::TYPE_LIABILITY,
                'parent_account_id' => $customerDeposits->id,
                'description' => 'Customer current account deposits',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );

        GLAccount::firstOrCreate(
            ['account_code' => '201200'],
            [
                'account_name' => 'Savings Accounts',
                'account_type' => GLAccount::TYPE_LIABILITY,
                'parent_account_id' => $customerDeposits->id,
                'description' => 'Customer savings account deposits',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );

        // Income Accounts (4xxxxx)
        $income = GLAccount::firstOrCreate(
            ['account_code' => '400000'],
            [
                'account_name' => 'Income',
                'account_type' => GLAccount::TYPE_INCOME,
                'description' => 'Income accounts',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );

        // Fee Income
        $feeIncome = GLAccount::firstOrCreate(
            ['account_code' => '401000'],
            [
                'account_name' => 'Fee Income',
                'account_type' => GLAccount::TYPE_INCOME,
                'parent_account_id' => $income->id,
                'description' => 'Income from fees',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );

        GLAccount::firstOrCreate(
            ['account_code' => '401100'],
            [
                'account_name' => 'Transaction Fees',
                'account_type' => GLAccount::TYPE_INCOME,
                'parent_account_id' => $feeIncome->id,
                'description' => 'Income from transaction fees',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );

        GLAccount::firstOrCreate(
            ['account_code' => '401200'],
            [
                'account_name' => 'Service Charges',
                'account_type' => GLAccount::TYPE_INCOME,
                'parent_account_id' => $feeIncome->id,
                'description' => 'Income from service charges',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );

        GLAccount::firstOrCreate(
            ['account_code' => '401300'],
            [
                'account_name' => 'Overdraft Fees',
                'account_type' => GLAccount::TYPE_INCOME,
                'parent_account_id' => $feeIncome->id,
                'description' => 'Income from overdraft fees',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );

        // Interest Income
        $interestIncome = GLAccount::firstOrCreate(
            ['account_code' => '402000'],
            [
                'account_name' => 'Interest Income',
                'account_type' => GLAccount::TYPE_INCOME,
                'parent_account_id' => $income->id,
                'description' => 'Income from interest',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );

        GLAccount::firstOrCreate(
            ['account_code' => '402100'],
            [
                'account_name' => 'Overdraft Interest',
                'account_type' => GLAccount::TYPE_INCOME,
                'parent_account_id' => $interestIncome->id,
                'description' => 'Interest income from overdrafts',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );

        // Expense Accounts (5xxxxx)
        $expenses = GLAccount::firstOrCreate(
            ['account_code' => '500000'],
            [
                'account_name' => 'Expenses',
                'account_type' => GLAccount::TYPE_EXPENSE,
                'description' => 'Expense accounts',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );

        // Transaction Costs
        $transactionCosts = GLAccount::firstOrCreate(
            ['account_code' => '501000'],
            [
                'account_name' => 'Transaction Costs',
                'account_type' => GLAccount::TYPE_EXPENSE,
                'parent_account_id' => $expenses->id,
                'description' => 'Costs related to transactions',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );

        GLAccount::firstOrCreate(
            ['account_code' => '501100'],
            [
                'account_name' => 'Payment Processing Fees',
                'account_type' => GLAccount::TYPE_EXPENSE,
                'parent_account_id' => $transactionCosts->id,
                'description' => 'External payment processing fees',
                'currency' => 'AED',
                'is_active' => true,
            ]
        );
    }
}