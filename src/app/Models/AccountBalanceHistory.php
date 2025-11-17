<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountBalanceHistory extends Model
{
    use HasUuids;

    protected $table = 'account_balance_history';

    protected $fillable = [
        'available_balance',
        'actual_balance',
        'locked_amount',
        'journal_entry_id',
        'balance_date',
    ];

    protected $casts = [
        'available_balance' => 'decimal:4',
        'actual_balance' => 'decimal:4',
        'locked_amount' => 'decimal:4',
        'balance_date' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}