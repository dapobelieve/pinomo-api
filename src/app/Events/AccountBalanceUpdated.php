<?php

namespace App\Events;

use App\Models\Account;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AccountBalanceUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Account $account,
        public string $balanceBefore,
        public string $reason = 'Transaction'
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->account->user_id),
            new PrivateChannel('account.' . $this->account->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'account_id' => $this->account->id,
            'account_number' => $this->account->account_number,
            'balance_before' => $this->balanceBefore,
            'balance_after' => $this->account->available_balance,
            'actual_balance' => $this->account->actual_balance,
            'currency' => $this->account->currency,
            'reason' => $this->reason,
            'updated_at' => $this->account->updated_at?->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'account.balance.updated';
    }
}
