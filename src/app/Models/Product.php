<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasUuids;

    protected $fillable = [
        'product_name',
        'product_type',
        'currency',
        'minimum_amount',
        'maximum_amount',
        'interest_rate',
        'interest_rate_type',
        'interest_calculation_frequency',
        'interest_posting_frequency',
        'repayment_frequency',
        'amortization_type',
        'grace_period_days',
        'late_payment_penalty_rate',
        'description',
        'is_active',
    ];

    protected $casts = [
        'minimum_amount' => 'decimal:4',
        'maximum_amount' => 'decimal:4',
        'interest_rate' => 'decimal:4',
        'late_payment_penalty_rate' => 'decimal:4',
        'grace_period_days' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * The charges associated with the product.
     */
    public function charges(): BelongsToMany
    {
        return $this->belongsToMany(Charge::class, 'product_charges')
            ->withTimestamps();
    }
}