<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use App\Jobs\ProcessChargeAccounting;

class Charge extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'charge_type', // flat, percentage, tiered
        'amount',
        'percentage',
        'currency',
        'description',
        'is_active',
        'gl_income_account_id'
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'percentage' => 'decimal:4',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(GLAccount::class, 'gl_income_account_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'charge_products')
            ->withPivot(['is_mandatory', 'charge_triggers'])
            ->withTimestamps();
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_charges')
            ->withPivot(['is_active', 'charge_config', 'effective_from', 'effective_until', 'created_by_user_id', 'notes'])
            ->withTimestamps();
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(ChargeTier::class);
    }

    // Helper Methods
    public function calculateCharge(float $amount): float
    {
        return match($this->charge_type) {
            'flat' => $this->amount,
            'percentage' => $amount * ($this->percentage / 100),
            'tiered' => $this->calculateTieredCharge($amount),
            default => 0
        };
    }

    public function calculateTieredCharge(float $amount): float
    {
        $cacheKey = "charge_{$this->id}_tier_{$amount}";
        return cache()->remember($cacheKey, now()->addHours(1), function () use ($amount) {
            $tier = $this->tiers()
                ->where('from_amount', '<=', $amount)
                ->where(function ($query) use ($amount) {
                    $query->where('to_amount', '>', $amount)
                        ->orWhereNull('to_amount');
                })
                ->first();

            if (!$tier) {
                return 0;
            }

            return $tier->fixed_amount ?? ($amount * ($tier->percentage / 100));
        });
    }

    public static function getActiveCharges(): Collection
    {
        return cache()->remember('active_charges', now()->addMinutes(30), function () {
            return static::where('is_active', true)->get();
        });
    }

    public static function getAccountSpecificCharges(string $accountId): Collection
    {
        return cache()->tags(['account_charges'])->remember(
            "account_charges_{$accountId}",
            now()->addMinutes(30),
            function () use ($accountId) {
                return static::whereHas('accounts', function ($query) use ($accountId) {
                    $query->where('account_id', $accountId)
                        ->where('is_active', true);
                })->get();
            }
        );
    }

    protected static function booted()
    {
        static::created(function ($charge) {
            Redis::publish('charges.created', json_encode([
                'id' => $charge->id,
                'name' => $charge->name,
                'charge_type' => $charge->charge_type,
                'amount' => $charge->amount,
                'percentage' => $charge->percentage,
                'currency' => $charge->currency,
                'is_active' => $charge->is_active
            ]));
            
            // Clear related caches
            cache()->forget('active_charges');
            if ($charge->is_active) {
                cache()->tags(['account_charges'])->flush();
            }
        });

        static::updated(function ($charge) {
            Redis::publish('charges.updated', json_encode([
                'id' => $charge->id,
                'name' => $charge->name,
                'charge_type' => $charge->charge_type,
                'amount' => $charge->amount,
                'percentage' => $charge->percentage,
                'currency' => $charge->currency,
                'is_active' => $charge->is_active
            ]));
            
            // Clear related caches
            cache()->forget('active_charges');
            if ($charge->is_active || $charge->getOriginal('is_active')) {
                cache()->tags(['account_charges'])->flush();
            }
        });

        static::deleted(function ($charge) {
            Redis::publish('charges.deleted', json_encode(['id' => $charge->id]));
            
            // Clear related caches
            cache()->forget('active_charges');
            cache()->tags(['account_charges'])->flush();
        });
    }

    public function applyCharge(Account $account, float $amount)
    {
        $charge = $this->calculateCharge($amount);
        
        // Create transaction for the charge
        $transaction = Transaction::createCharge($account, $charge);
        
        // Dispatch job to process accounting entries
        ProcessChargeAccounting::dispatch($this, $account->id, $charge)
            ->onQueue('ledger')
            ->delay(now()->addSeconds(5)); // Small delay to ensure transaction is completed
        
        return $transaction;
    }
}