<?php

namespace App\Events;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionProcessed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Account $account,
        public Transaction $transaction,
        public string $type
    ) {
        \Illuminate\Support\Facades\Log::info('TransactionProcessed event created', [
            'account_id' => $account->id,
            'transaction_id' => $transaction->id,
            'type' => $type,
            'client_id' => $account->client->external_id,
            'broadcast_driver' => config('broadcasting.default'),
        ]);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->account->client->external_id),
        ];
    }

    public function broadcastWith(): array
    {
        $isDebit = $this->type === 'debit';

        $this->transaction->loadMissing(['sourceAccount', 'destinationAccount']);

        $otherAccount = $isDebit
            ? $this->transaction->destinationAccount
            : $this->transaction->sourceAccount;

        return [
            'transaction' => [
                'id' => $this->transaction->id,
                'type' => $isDebit ? 'outgoing' : 'incoming',
                'transaction_type' => $this->transaction->transaction_type,
                'amount' => $this->transaction->amount,
                'currency' => $this->transaction->currency,
                'status' => $this->transaction->status,
                'description' => $this->transaction->description,
                'reference' => $this->transaction->external_reference,
                'internal_reference' => $this->transaction->internal_reference,
                'external_reference' => $this->transaction->external_reference,
                'other_account' => $otherAccount?->account_number,
                'source_wallet_id' => $this->transaction->source_account_id,
                'destination_wallet_id' => $this->transaction->destination_account_id,
                'direction' => $isDebit ? 'debit' : 'credit',
                'created_at' => $this->transaction->created_at->toISOString(),
            ],
            'wallet_id' => $this->account->id,
            'balance' => [
                'available_balance' => $this->account->fresh()->available_balance,
                'actual_balance' => $this->account->fresh()->actual_balance,
                'locked_amount' => $this->account->fresh()->locked_amount,
                'currency' => $this->account->currency,
            ]
        ];
    }

    public function broadcastAs(): string
    {
        return 'transaction.processed';
    }
}
