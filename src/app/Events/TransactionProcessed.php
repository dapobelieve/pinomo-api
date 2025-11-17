<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionProcessed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Transaction $transaction
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->transaction->user_id),
            new PrivateChannel('account.' . $this->transaction->account_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->transaction->id,
            'type' => $this->transaction->type,
            'amount' => $this->transaction->amount,
            'currency' => $this->transaction->currency,
            'status' => $this->transaction->status,
            'balance_after' => $this->transaction->balance_after,
            'description' => $this->transaction->description,
            'processed_at' => $this->transaction->updated_at?->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'transaction.processed';
    }
}
