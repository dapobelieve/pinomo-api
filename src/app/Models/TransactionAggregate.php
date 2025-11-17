<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TransactionAggregate extends Model
{
    use HasUuids;

    protected $fillable = [
        'account_id',
        'aggregated_daily_amount',
        'collections_amount',
        'disbursements_amount',
        'date'
    ];

    protected $casts = [
        'aggregated_daily_amount' => 'decimal:4',
        'collections_amount' => 'decimal:4',
        'disbursements_amount' => 'decimal:4',
        'date' => 'date'
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
