<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class JournalEntryItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'journal_entry_id',
        'gl_account_id',
        'debit_amount',
        'credit_amount',
        'description',
    ];

    protected $casts = [
        'debit_amount' => 'decimal:4',
        'credit_amount' => 'decimal:4',
    ];

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function glAccount()
    {
        return $this->belongsTo(GLAccount::class);
    }

    public function getAmount()
    {
        return $this->debit_amount > 0 ? $this->debit_amount : $this->credit_amount;
    }

    public function isDebit()
    {
        return $this->debit_amount > 0;
    }

    public function isCredit()
    {
        return $this->credit_amount > 0;
    }
}