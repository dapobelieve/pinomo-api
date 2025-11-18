<?php

namespace App\Events;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionProcessed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Account $account,
        public Transaction $transaction,
        public string $type
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->account->client->external_id),
        ];
    }

    public function broadcastWith(): array
    {
        $isDebit = $this->type === 'debit';

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
                'created_at' => $this->transaction->created_at->toISOString(),
            ],
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
