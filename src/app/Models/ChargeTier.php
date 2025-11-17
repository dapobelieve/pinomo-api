<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargeTier extends Model
{
    use HasUuids;

    protected $fillable = [
        'charge_id',
        'from_amount',
        'to_amount',
        'fixed_amount',
        'percentage'
    ];

    protected $casts = [
        'from_amount' => 'decimal:4',
        'to_amount' => 'decimal:4',
        'fixed_amount' => 'decimal:4',
        'percentage' => 'decimal:4'
    ];

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    public function isApplicable(float $amount): bool
    {
        return $amount >= $this->from_amount && 
            ($this->to_amount === null || $amount < $this->to_amount);
    }

    public function calculateAmount(float $amount): float
    {
        if (!$this->isApplicable($amount)) {
            return 0;
        }

        return $this->fixed_amount ?? ($amount * ($this->percentage / 100));
    }
}