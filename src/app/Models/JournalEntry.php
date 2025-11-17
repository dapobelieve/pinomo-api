<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'entry_number',
        'entry_date',
        'reference_type',
        'reference_id',
        'currency',
        'description',
        'status',
        'created_by_user_id',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'posted_at' => 'datetime',
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_POSTED = 'posted';
    const STATUS_VOIDED = 'voided';

    public function items()
    {
        return $this->hasMany(JournalEntryItem::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function postedByUser()
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    public function isBalanced()
    {
        $totalDebits = $this->items()->sum('debit_amount');
        $totalCredits = $this->items()->sum('credit_amount');
        return bccomp($totalDebits, $totalCredits, 4) === 0;
    }

    public function canBePosted()
    {
        return $this->status === self::STATUS_DRAFT && $this->isBalanced();
    }

    public function post($userId)
    {
        if (!$this->canBePosted()) {
            throw new \Exception('Journal entry cannot be posted');
        }

        $this->status = self::STATUS_POSTED;
        $this->posted_by_user_id = $userId;
        $this->posted_at = now();
        $this->save();
    }

    public function void($reason)
    {
        if ($this->status !== self::STATUS_POSTED) {
            throw new \Exception('Only posted entries can be voided');
        }

        $this->status = self::STATUS_VOIDED;
        $this->description = $reason;
        $this->save();
    }
}