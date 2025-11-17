<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\Account;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientObserver
{
    public function created(Client $client): void
    {
        if (!$client->isIndividualClient()) {
            return;
        }

        $this->createWalletAccountForClient($client);
    }

    private function createWalletAccountForClient(Client $client): void
    {
        try {
            DB::transaction(function () use ($client) {
                $product = $this->findAedWalletProduct();

                if (!$product) {
                    Log::warning('AED wallet product not found', ['client_id' => $client->id]);
                    return;
                }

                $accountNumber = $this->generateAccountNumber();
                $accountName = "AED Wallet - {$client->getDisplayName()}";

                Account::create([
                    'account_number' => $accountNumber,
                    'client_id' => $client->id,
                    'product_id' => $product->id,
                    'account_name' => $accountName,
                    'currency' => 'AED',
                    'account_type' => 'main',
                    'status' => Account::STATUS_ACTIVE,
                    'available_balance' => 0,
                    'actual_balance' => 0,
                    'locked_amount' => 0,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Failed to create wallet account for client', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function findAedWalletProduct(): ?Product
    {
        return Product::where('product_type', 'wallet')
            ->where('currency', 'AED')
            ->where('is_active', true)
            ->first();
    }

    private function generateAccountNumber(): string
    {
        do {
            $number = date('Y') . str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Account::where('account_number', $number)->exists());

        return $number;
    }
}
