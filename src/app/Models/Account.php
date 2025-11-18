<?php

namespace App\Models;

use App\Exceptions\InsufficientFundsException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Ramp\Logger\Facades\Log;

class Account extends Model
{
    use HasFactory;
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'id', // for middleman to create ids for specifi accounts
        'account_number',
        'client_id',
        'product_id',
        'account_name',
        'currency',
        'status',
        'closure_reason',
        'account_type',
        'allow_overdraft',
        'overdraft_limit',
        'overdraft_interest_rate',
        'single_transaction_limit'  // Add this line
    ];

    protected $hidden = [
        'product',
        'client',
        'created_by_user_id',
        'closed_by_user_id',
        'dormant_at',
        'closed_at',
        'closed_by_user_id',
        'created_at',
        'updated_at',
        'deleted_at',
        'product_id',
        'client_id',
        'closure_reason',
        'allow_overdraft',
        'overdraft_limit',
        'overdraft_interest_rate',
        'overdraft_approved_by_user_id',
        'overdraft_approved_at',
        'single_transaction_limit'
    ];

    protected $casts = [
        'available_balance' => 'decimal:4',
        'actual_balance' => 'decimal:4',
        'locked_amount' => 'decimal:4',
        'allow_overdraft' => 'boolean',
        'overdraft_limit' => 'decimal:4',
        'overdraft_interest_rate' => 'decimal:4',
        'single_transaction_limit' => 'decimal:4',  // Add this line
        'last_activity_at' => 'datetime',
        'dormant_at' => 'datetime',
        'closed_at' => 'datetime',
        'overdraft_approved_at' => 'datetime'
    ];

    const STATUS_PENDING_ACTIVATION = 'pending_activation';
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'in-active';
    const STATUS_DORMANT = 'dormant';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_CLOSED = 'closed';

    // Relationships
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'source_account_id');
    }

    public function balanceHistory(): HasMany
    {
        return $this->hasMany(AccountBalanceHistory::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function overdraftApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overdraft_approved_by_user_id');
    }

    public function enableOverdraft(float $limit, float $interestRate): bool
    {
        $this->allow_overdraft = true;
        $this->overdraft_limit = $limit;
        $this->overdraft_interest_rate = $interestRate;
        $this->overdraft_approved_by_user_id = auth()->id();
        $this->overdraft_approved_at = now();
        return $this->save();
    }

    public function disableOverdraft(): bool
    {
        if ($this->actual_balance < 0) {
            return false; // Cannot disable overdraft while account is overdrawn
        }

        $this->allow_overdraft = false;
        $this->overdraft_limit = 0;
        $this->overdraft_interest_rate = 0;
        return $this->save();
    }

    public function getCharges()
    {
        return cache()->remember("account_{$this->id}_charges", now()->addMinutes(30), function () {
            // First check for account-specific charges
            $charges = Charge::getAccountSpecificCharges($this->id);

            // If no account-specific charges, get global active charges
            if ($charges->isEmpty()) {
                $charges = Charge::getActiveCharges();
            }

            return $charges;
        });
    }


    public function getBalanceAsOf($date): float
    {
        if (is_string($date)) {
            $date = \Carbon\Carbon::parse($date);
        }

        // Debug: Check what we're actually querying
        Log::info('Balance query debug', [
            'account_id' => $this->id,
            'date' => $date->toDateTimeString(),
        ]);

        // Primary: Check AccountBalanceHistory first
        $balanceHistory = $this->balanceHistory()
            ->where('balance_date', '<=', $date)
            ->orderBy('balance_date', 'desc')
            ->first();

        if ($balanceHistory) {
            Log::info('Found balance history', [
                'balance' => $balanceHistory->actual_balance,
                'date' => $balanceHistory->balance_date
            ]);
            return (float) $balanceHistory->actual_balance;
        }

        // Fallback: Raw query to ensure we get results
        $lastTransaction = \DB::table('transactions')
            ->where('source_account_id', $this->id)
            ->where('created_at', '<=', $date)
            ->where('status', 'completed')
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->first(['source_ledger_balance_after', 'created_at', 'internal_reference']);

        if ($lastTransaction) {
            Log::info('Found transaction via raw query', [
                'balance' => $lastTransaction->source_ledger_balance_after,
                'transaction_date' => $lastTransaction->created_at,
                'reference' => $lastTransaction->internal_reference
            ]);
            return (float) $lastTransaction->source_ledger_balance_after;
        }

        Log::warning('No balance data found', [
            'account_id' => $this->id,
            'date' => $date->toDateTimeString()
        ]);

        return 0.00;
    }

    public function incomingTransactions()
    {
        return $this->hasMany(Transaction::class, 'destination_account_id');
    }

    public function allTransactions()
    {
        return Transaction::where(function($query) {
            $query->where('source_account_id', $this->id)
                ->orWhere('destination_account_id', $this->id);
        });
    }



    public function hasOverdraftFacility(): bool
    {
        return cache()->remember("account_{$this->id}_overdraft_status", now()->addMinutes(5), function () {
            return $this->allow_overdraft && $this->overdraft_limit > 0;
        });
    }

    public function getAvailableOverdraft(): float
    {
        return cache()->remember("account_{$this->id}_available_overdraft", now()->addMinutes(5), function () {
            if (!$this->hasOverdraftFacility()) {
                return 0;
            }
            return max(0, $this->overdraft_limit + $this->actual_balance);
        });
    }


    // Status management methods
    public function activate(): bool
    {
        if ($this->status !== self::STATUS_PENDING_ACTIVATION) {
            return false;
        }

        $this->status = self::STATUS_ACTIVE;
        $this->last_activity_at = now();
        return $this->save();
    }

    public function suspend(): bool
    {
        if ($this->status === self::STATUS_CLOSED) {
            return false;
        }

        $this->status = self::STATUS_SUSPENDED;
        return $this->save();
    }

    public function close(string $reason): bool
    {
        if ($this->status === self::STATUS_CLOSED) {
            return false;
        }

        $this->status = self::STATUS_CLOSED;
        $this->closure_reason = $reason;
        $this->closed_at = now();
        $this->closed_by_user_id = auth()->id();
        return $this->save();
    }

    // Balance management methods
    public function updateBalances(float $availableBalance, float $actualBalance, float $lockedAmount, ?string $journalEntryId = null): void
    {
        // Update account balances
        $this->available_balance = $availableBalance;
        $this->actual_balance = $actualBalance;
        $this->locked_amount = $lockedAmount;
        $this->last_activity_at = now();
        $this->save();

        // Create balance history record
        $this->balanceHistory()->create([
            'available_balance' => $availableBalance,
            'actual_balance' => $actualBalance,
            'locked_amount' => $lockedAmount,
            'journal_entry_id' => $journalEntryId,
            'balance_date' => now(),
        ]);
    }

    public function lock(float $amount): bool
    {
        if ($amount <= 0 || $amount > $this->available_balance) {
            return false;
        }

        $this->updateBalances(
            $this->available_balance - $amount,
            $this->actual_balance,
            $this->locked_amount + $amount
        );

        return true;
    }

    public function unlock(float $amount): bool
    {
        if ($amount <= 0 || $amount > $this->locked_amount) {
            return false;
        }

        $this->updateBalances(
            $this->available_balance + $amount,
            $this->actual_balance,
            $this->locked_amount - $amount
        );

        return true;
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isDormant(): bool
    {
        return $this->status === self::STATUS_DORMANT;
    }
}
