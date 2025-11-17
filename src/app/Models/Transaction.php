<?php

namespace App\Models;

use App\Exceptions\InsufficientFundsException;
use App\Jobs\ProcessChargeAccounting;
use App\Jobs\ProcessTransactionAggregate;
use App\Jobs\ReleaseAndWithdrawJob;
use App\Jobs\ReleaseLienJob;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;
use App\Models\JournalEntry;
use App\Models\JournalEntryItem;
use App\Models\GLAccount;

class Transaction extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'internal_reference',
        'external_reference',
        'processor_reference',
        'transaction_type',
        'processing_type',
        'processing_channel',
        'source_account_id',
        'destination_account_id',
        'currency',
        'amount',
        'source_ledger_balance_before',
        'source_locked_balance_before',
        'source_available_balance_before',
        'source_ledger_balance_after',
        'source_locked_balance_after',
        'source_available_balance_after',
        'status',
        'description',
        'metadata',
        'original_transaction_id',
        'created_by_user_id',
        'approved_by_user_id',
        'approved_at'
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'source_ledger_balance_before' => 'decimal:4',
        'source_locked_balance_before' => 'decimal:4',
        'source_available_balance_before' => 'decimal:4',
        'source_ledger_balance_after' => 'decimal:4',
        'source_locked_balance_after' => 'decimal:4',
        'source_available_balance_after' => 'decimal:4',
        'metadata' => 'json',
        'approved_at' => 'datetime'
    ];

    // Transaction Types
    public const TYPE_DEPOSIT = 'deposit';
    public const TYPE_WITHDRAWAL = 'withdrawal';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_CHARGE = 'charge';
    public const TYPE_REVERSAL = 'reversal';
    public const TYPE_LIEN = 'lien';
    public const TYPE_LIEN_RELEASE = 'lien_release';

    // Processing Types
    public const PROCESSING_INTERNAL = 'intra';
    public const PROCESSING_EXTERNAL = 'inter';

    // Statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REVERSED = 'reversed';
    public const STATUS_AWAITING_COMPLIANCE = 'awaiting_compliance';

    // Relationships
    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'destination_account_id');
    }

    public function originalTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'original_transaction_id');
    }

    public function reversalTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'original_transaction_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    // Helper Methods
    public function isReversible(): bool
    {
        return $this->status === self::STATUS_COMPLETED
            && $this->transaction_type !== self::TYPE_REVERSAL
            && !$this->reversalTransaction()->exists();
    }

    public function isLien(): bool
    {
        return $this->transaction_type === self::TYPE_LIEN;
    }

    public function canReleaseLien(): bool
    {
        return $this->isLien()
            && $this->status === self::STATUS_COMPLETED
            && !$this->reversalTransaction()->exists();
    }

    public function processLienRelease(?string $webhookUrl = null): void
    {
        if (!$this->canReleaseLien()) {
            throw new \Exception('Lien cannot be released');
        }

        ReleaseLienJob::dispatch($this, $webhookUrl);
    }

    public function processReleaseAndWithdraw(array $withdrawalData, ?string $webhookUrl = null): void
    {
        if (!$this->canReleaseLien()) {
            throw new \Exception('Lien & withdraw operation cannot be performed');
        }

        ReleaseAndWithdrawJob::dispatch($this, $withdrawalData, $webhookUrl);
    }

    public function needsCompliance(): bool
    {
        return $this->status === self::STATUS_AWAITING_COMPLIANCE;
    }

    public function generateInternalReference(): void
    {
        $this->internal_reference = 'TXN-' . strtoupper(uniqid());
    }

    // Static factory methods
    public static function createDeposit(Account $account, array $data): self
    {
        $transaction = new self(
            [
            'transaction_type' => self::TYPE_DEPOSIT,
            'processing_type' => $data['processing_type'],
            'processing_channel' => $data['processing_channel'],
            'source_account_id' => $account->id,
            'external_reference' => $data['external_reference'],
            'currency' => $account->currency,
            'amount' => $data['amount'],
            'description' => $data['description'],
            'metadata' => $data['metadata'] ?? null,
            'created_by_user_id' => 1
            ]
        );


        $transaction->processAndSave($account);
        return $transaction;
    }

    public static function createWithdrawal(Account $account, array $data): self
    {
        $withdrawalAmount = $data['amount'];
        $availableBalance = $account->available_balance;
        $actualBalance = $account->actual_balance; // FIXED: was ledger_balance

        if ($account->product->minimum_withdrawal_amount && $withdrawalAmount < $account->product->minimum_withdrawal_amount) {
            throw new \InvalidArgumentException(
                "Withdrawal amount {$withdrawalAmount} is below minimum withdrawal limit {$account->product->minimum_withdrawal_amount}"
            );
        }

        if ($account->single_transaction_limit && $withdrawalAmount > $account->single_transaction_limit) {
            throw new \InvalidArgumentException(
                "Withdrawal amount {$withdrawalAmount} exceeds single transaction limit {$account->single_transaction_limit}"
            );
        }

        $today = now()->format('Y-m-d');
        $dailyAggregate = TransactionAggregate::where('account_id', $account->id)
            ->where('date', $today)
            ->first();

        $dailyLimit = $account->product->daily_transaction_limit ?? PHP_FLOAT_MAX;
        if ($dailyAggregate) {
            $newDailyTotal = bcadd($dailyAggregate->aggregated_daily_amount, $withdrawalAmount, 4);
            if ($newDailyTotal > $dailyLimit) {
                throw new \InvalidArgumentException(
                    "This withdrawal would exceed your daily transaction limit of {$dailyLimit}. Current daily total: {$dailyAggregate->aggregated_daily_amount}"
                );
            }
        }

        // Calculate applicable charges
        $accountCharges = $account->getCharges();
        $totalChargeAmount = 0;
        $chargeBreakdown = [];

        foreach ($accountCharges as $charge) {
            $chargeAmount = $charge->calculateCharge($withdrawalAmount);
            if ($chargeAmount > 0) {
                $totalChargeAmount += $chargeAmount;
                $chargeBreakdown[] = [
                    'charge_name' => $charge->charge_name,
                    'charge_amount' => $chargeAmount,
                    'charge_type' => $charge->charge_type
                ];
            }
        }

        $totalAmount = $withdrawalAmount + $totalChargeAmount;

        if ($availableBalance < $totalAmount) {
            if (!$account->hasOverdraftFacility()) {
                throw new InsufficientFundsException(
                    $account,
                    $totalAmount,
                    "Insufficient available balance. Required: {$totalAmount}, Available: {$availableBalance}"
                );
            }

            $availableOverdraft = $account->getAvailableOverdraft();
            $overdraftRequired = $totalAmount - $availableBalance;

            if ($overdraftRequired > $availableOverdraft) {
                throw new InsufficientFundsException(
                    $account,
                    $totalAmount,
                    "Total amount (withdrawal + charges) exceeds available balance and overdraft limit. Required overdraft: {$overdraftRequired}, Available overdraft: {$availableOverdraft}"
                );
            }
        }

        if ($actualBalance < $totalAmount && !$account->hasOverdraftFacility()) {
            throw new InsufficientFundsException(
                $account,
                $totalAmount,
                "Insufficient actual balance for withdrawal"
            );
        }

        $preTransactionActualBalance = $account->actual_balance; // FIXED: was ledger_balance
        $preTransactionAvailableBalance = $account->available_balance;
        $preTransactionLockedAmount = $account->locked_amount; // FIXED: was locked_balance

        // Create the withdrawal transaction
        $transaction = new self(
            [
            'transaction_type' => self::TYPE_WITHDRAWAL,
            'processing_type' => $data['processing_type'],
            'processing_channel' => $data['processing_channel'],
            'source_account_id' => $account->id,
            'external_reference' => $data['transaction_reference'],
            'currency' => $account->currency,
            'amount' => $withdrawalAmount,
            'description' => $data['description'],
            'source_ledger_balance_before' => $preTransactionActualBalance, // Maps actual_balance to ledger_balance field
            'source_available_balance_before' => $preTransactionAvailableBalance,
            'source_locked_balance_before' => $preTransactionLockedAmount, // Maps locked_amount to locked_balance field
            'metadata' => array_merge(
                $data['metadata'] ?? [],
                [
                'is_overdraft' => $availableBalance < $totalAmount,
                'overdraft_amount' => $availableBalance < $totalAmount ? ($totalAmount - $availableBalance) : 0,
                'total_charges' => $totalChargeAmount,
                'charge_breakdown' => $chargeBreakdown,
                'withdrawal_channel' => $data['processing_channel']
                ]
            ),
            'status' => self::STATUS_PENDING,
            'created_by_user_id' => auth()->id() ?? 1
            ]
        );

        $transaction->processAndSave($account);

        return $transaction;
    }


    public static function createLien(Account $account, array $data): self
    {
        if ($account->available_balance < $data['amount']) {
            throw new \Exception('Insufficient available balance to place account on lien');
        }

        $transaction = new self(
            [
            'transaction_type' => self::TYPE_LIEN,
            'processing_type' => $data['processing_type'],
            'processing_channel' => $data['processing_channel'],
            'source_account_id' => $account->id,
            'external_reference' => $data['transaction_reference'],
            'currency' => $account->currency,
            'status' => self::STATUS_PENDING,
            'amount' => $data['amount'],
            'description' => $data['description'],
            'metadata' => $data['metadata'] ?? null,
            'created_by_user_id' => 1,
            ]
        );

         $transaction->processAndSave($account);
        return $transaction;
    }

    public function createReversal(): self
    {
        if (!$this->isReversible()) {
            throw new \Exception('Transaction cannot be reversed');
        }

        $reversal = new self(
            [
            'transaction_type' => self::TYPE_REVERSAL,
            'processing_type' => $this->processing_type,
            'processing_channel' => $this->processing_channel,
            'source_account_id' => $this->source_account_id,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'description' => 'Reversal for transaction ' . $this->internal_reference,
            'original_transaction_id' => $this->id,
            'created_by_user_id' => auth()->id()
            ]
        );

        return $reversal->processAndSave($this->sourceAccount);
    }

    public function createLienRelease($data): self
    {
        if (!$this->canReleaseLien()) {
            throw new \Exception('Lien cannot be released');
        }

        $release = new self(
            [
            'transaction_type' => self::TYPE_LIEN_RELEASE,
            'processing_type' => self::PROCESSING_INTERNAL,
            'source_account_id' => $this->source_account_id,
            'external_reference' => $data['transaction_reference'],
            'processing_channel' => 'system',
            'currency' => $this->currency,
            'amount' => $this->amount,
            'description' => 'Release lien for transaction ' . $this->internal_reference,
            'original_transaction_id' => $this->id,
            'created_by_user_id' => 1
            ]
        );

        $release->processAndSave($this->sourceAccount);

        return $release;
    }

    public function createReleaseAndWithdraw(Account $account, array $data): array
    {
        if (!$this->canReleaseLien()) {
            throw new \Exception('Lien cannot be released');
        }

        try {
            // Create both transactions first
            $withdrawal = new self(
                [
                'transaction_type' => self::TYPE_WITHDRAWAL,
                'processing_type' => $data['processing_type'],
                'processing_channel' => $data['processing_channel'],
                'source_account_id' => $this->source_account_id,
                'external_reference' => $data['external_reference'],
                'currency' => $this->currency,
                'amount' => $this->amount,
                'description' => $data['description'] ?? 'Withdrawal',
                'metadata' => array_merge(
                    $data['metadata'] ?? [],
                    [
                    'lien_transaction_id' => $this->id,
                    'operation_type' => 'release_and_withdraw'
                    ]
                ),
                'status' => self::STATUS_PENDING,
                'created_by_user_id' => auth()->id() ?? 1
                ]
            );

            $release = new self(
                [
                'transaction_type' => self::TYPE_LIEN_RELEASE,
                'processing_type' => self::PROCESSING_INTERNAL,
                'processing_channel' => 'system',
                'source_account_id' => $this->source_account_id,
                'external_reference' => $data['external_reference'],
                'currency' => $this->currency,
                'amount' => $this->amount,
                'description' => 'Release lien for transaction ' . $this->internal_reference,
                'original_transaction_id' => $this->id,
                'metadata' => [
                    'operation_type' => 'release_and_withdraw'
                ],
                'status' => self::STATUS_PENDING,
                'created_by_user_id' => auth()->id() ?? 1
                ]
            );

            // Process both operations atomically
            $withdrawal->processAndSave($account);

            // Reload account to get updated balances
            //            $account->refresh();

            // Process release with updated account
            $release->processAndSave($account);

            return [
                'withdrawal' => $withdrawal,
                'release' => $release
            ];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function processReleaseAndWithdrawAtomic(Account $account, Transaction $withdrawal, Transaction $release): void
    {
        // Capture initial state
        $initialBalance = $account->actual_balance;
        $initialLocked = $account->locked_amount;
        $initialAvailable = $account->available_balance;

        // Step 1: Process withdrawal only
        $account->actual_balance -= $this->amount;
        $account->available_balance -= $this->amount;

        // Capture intermediate state for withdrawal
        $withdrawalFinalBalance = $account->actual_balance;
        $withdrawalFinalAvailable = $account->available_balance;
        $withdrawalFinalLocked = $account->locked_amount;

        // Step 2: Process release
        $account->locked_amount -= $this->amount;
        $account->available_balance += $this->amount;

        // Set withdrawal snapshots
        $withdrawal->source_ledger_balance_before = $initialBalance;
        $withdrawal->source_locked_balance_before = $initialLocked;
        $withdrawal->source_available_balance_before = $initialAvailable;
        $withdrawal->source_ledger_balance_after = $withdrawalFinalBalance;
        $withdrawal->source_locked_balance_after = $withdrawalFinalLocked;
        $withdrawal->source_available_balance_after = $withdrawalFinalAvailable;

        // Set release snapshots
        $release->source_ledger_balance_before = $withdrawalFinalBalance;
        $release->source_locked_balance_before = $withdrawalFinalLocked;
        $release->source_available_balance_before = $withdrawalFinalAvailable;
        $release->source_ledger_balance_after = $account->actual_balance;
        $release->source_locked_balance_after = $account->locked_amount;
        $release->source_available_balance_after = $account->available_balance;

        // Save everything
        $withdrawal->generateInternalReference();
        $withdrawal->status = self::STATUS_PROCESSING;
        $release->generateInternalReference();
        $release->status = self::STATUS_PROCESSING;

        $withdrawal->save();
        $release->save();
        $account->save();

        $withdrawal->processJournalEntriesAsync();
        $release->processJournalEntriesAsync();
    }

    //    for every txn after in previous txn become the before in the subsequent txn
    // after of subsequent txn = before + amount

    // Process and save transaction
    public function processAndSave(Account $account)
    {
        try {
            DB::beginTransaction();

            // Set balance snapshots
            $this->source_ledger_balance_before = $account->actual_balance;
            $this->source_locked_balance_before = $account->locked_amount;
            $this->source_available_balance_before = $account->available_balance;

            // Update account balances based on transaction type
            $updatedAccount = $this->updateBalances($account);

            // Set after balance snapshots
            $this->source_ledger_balance_after = $updatedAccount->actual_balance;
            $this->source_locked_balance_after = $updatedAccount->locked_amount;
            $this->source_available_balance_after = $updatedAccount->available_balance;

            $this->generateInternalReference();
            $this->status = self::STATUS_PROCESSING;
            $this->save();

            // Save account changes with version check
            if (!$account->save()) {
                throw new \RuntimeException('Concurrent modification detected');
            }

            // Process journal entries asynchronously
            $this->processJournalEntriesAsync();

            // Apply charges asynchronously if applicable
            if (in_array($this->transaction_type, [self::TYPE_DEPOSIT, self::TYPE_WITHDRAWAL])) {
                $this->processChargesAsync();
            }

            // Publish to Redis for real-time updates
            $this->publishStatusUpdate();

            // Dispatch job to process transaction aggregate
            ProcessTransactionAggregate::dispatch(
                $this->source_account_id,
                $this->amount,
                $this->transaction_type
            );

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    protected function updateBalances(Account $account)
    {
        switch ($this->transaction_type) {
            case self::TYPE_WITHDRAWAL:
                $potentialBalance = $account->actual_balance - $this->amount;
                if ($potentialBalance < 0 && !$account->hasOverdraftFacility()) {
                    throw new InsufficientFundsException($account, $this->amount);
                }
                $account->actual_balance -= $this->amount;
                $account->available_balance -= $this->amount;
                break;

            case self::TYPE_CHARGE:
                $potentialBal = $account->actual_balance - $this->amount;
                if ($potentialBal < 0 && !$account->hasOverdraftFacility()) {
                    throw new InsufficientFundsException($account, $this->amount);
                }
                $account->actual_balance -= $this->amount;
                $account->available_balance -= $this->amount;
                break;

            case self::TYPE_DEPOSIT:
                $account->actual_balance += $this->amount;
                $account->available_balance += $this->amount;
                break;

            case self::TYPE_LIEN:
                $account->locked_amount += $this->amount;
                $account->available_balance -= $this->amount;
                break;

            case self::TYPE_LIEN_RELEASE:
                if ($account->locked_amount < $this->amount) {
                    throw new \InvalidArgumentException(
                        "Insufficient locked amount. Required: {$this->amount}, Available: {$account->locked_amount}"
                    );
                }
                $account->locked_amount -= $this->amount;
                $account->available_balance += $this->amount;
                break;

            case self::TYPE_REVERSAL:
                $this->processReversal($account);
                break;
        }

        return $account;
    }

    //Todo: Catch if txn type is not TYPE_DEPOSIT, TYPE_WITHDRAWAL
    protected function processReversal(Account $account): void
    {
        if ($this->originalTransaction->transaction_type === self::TYPE_DEPOSIT) {
            $account->ledger_balance -= $this->amount;
            $account->available_balance -= $this->amount;
        } else {
            $account->ledger_balance += $this->amount;
            $account->available_balance += $this->amount;
        }
    }

    protected function processJournalEntriesAsync(): void
    {
        try {
            $this->createJournalEntry();
            $this->status = self::STATUS_COMPLETED;
            $this->save();
            $this->publishStatusUpdate();
        } catch (Throwable $e) {
            Log::error(
                'Journal entry processing failed',
                [
                'transaction_id' => $this->id,
                'error' => $e->getMessage()
                ]
            );
            $this->status = self::STATUS_FAILED;
            $this->save();
            $this->publishStatusUpdate();
            throw $e;
        }
    }

    protected function generateJournalEntryNumber(): string
    {
        return 'JE-' . (string) Str::uuid();
    }

    protected function createJournalEntry(): void
    {
        $journalEntry = JournalEntry::create(
            [
            'entry_number' => $this->generateJournalEntryNumber(),
            'entry_date' => now()->toDateString(),
            'reference_type' => $this->transaction_type,
            'reference_id' => $this->id,
            'currency' => $this->currency,
            'description' => $this->getJournalDescription(),
            'status' => JournalEntry::STATUS_POSTED,
            'created_by_user_id' => $this->created_by_user_id ?? 1,
            'posted_by_user_id' => $this->created_by_user_id ?? 1,
            'posted_at' => now()
            ]
        );

        // Create journal entry items based on transaction type
        $this->createJournalEntryItems($journalEntry);

        $this->updateGLAccountBalances($journalEntry);
    }

    protected function updateGLAccountBalances(JournalEntry $journalEntry): void
    {
        foreach ($journalEntry->items as $item) {
            $glAccount = $item->glAccount;

            // Update current_balance based on account type and debit/credit
            if (in_array($glAccount->account_type, ['asset', 'expense'])) {
                // Assets and Expenses: Debit increases, Credit decreases
                $glAccount->current_balance += ($item->debit_amount - $item->credit_amount);
            } else {
                // Liabilities, Equity, Income: Credit increases, Debit decreases
                $glAccount->current_balance += ($item->credit_amount - $item->debit_amount);
            }

            $glAccount->save();
        }
    }

    protected function getJournalDescription(): string
    {
        return match ($this->transaction_type) {
            self::TYPE_DEPOSIT => "Deposit to account {$this->sourceAccount->account_number} - {$this->description}",
            self::TYPE_WITHDRAWAL => "Withdrawal from account {$this->sourceAccount->account_number} - {$this->description}",
            self::TYPE_TRANSFER => "Transfer from {$this->sourceAccount->account_number} to {$this->destinationAccount->account_number}",
            self::TYPE_CHARGE => "Charge on account {$this->sourceAccount->account_number} - {$this->description}",
            self::TYPE_REVERSAL => "Reversal of transaction {$this->originalTransaction->internal_reference}",
            self::TYPE_LIEN => "Lien placed on account {$this->sourceAccount->account_number}",
            self::TYPE_LIEN_RELEASE => "Lien released on account {$this->sourceAccount->account_number}",
            default => $this->description ?? 'Transaction'
        };
    }

    protected function createJournalEntryItems(JournalEntry $journalEntry): void
    {
        switch ($this->transaction_type) {
            case self::TYPE_DEPOSIT:
                $this->createDepositJournalItems($journalEntry);
                break;
            case self::TYPE_WITHDRAWAL:
                $this->createWithdrawalJournalItems($journalEntry);
                break;
        //            case self::TYPE_TRANSFER:
        //                $this->createTransferJournalItems($journalEntry);
        //                break;
        //            case self::TYPE_CHARGE:
        //                $this->createChargeJournalItems($journalEntry);
        //                break;
        //            case self::TYPE_REVERSAL:
        //                $this->createReversalJournalItems($journalEntry);
        //                break;
        //            case self::TYPE_LIEN:
        //            case self::TYPE_LIEN_RELEASE:
        //                // Liens don't create GL entries, only memo entries
        //                break;
        }
    }

    /**
     * Fixed deposit journal entries with correct accounting logic
     */
    protected function createDepositJournalItems(JournalEntry $journalEntry): void
    {
        // Debit: Cash Account (Asset - cash coming into bank)
        $journalEntry->items()->create(
            [
            'gl_account_id' => $this->getCashAccountGLId(),
            'debit_amount' => $this->amount,
            'credit_amount' => 0,
            'description' => "Cash received for deposit"
            ]
        );

        // Credit: Customer Deposits (Liability - increases what bank owes customer)
        $journalEntry->items()->create(
            [
            'gl_account_id' => $this->getCustomerAccountGLId(),
            'debit_amount' => 0,
            'credit_amount' => $this->amount,
            'description' => "Deposit to {$this->sourceAccount->account_number}"
            ]
        );
    }



    /**
     * Get Customer Deposits GL Account (201100) - LIABILITY
     * Tracks money the bank owes to customers (their account balances)
     * INCREASES when customers deposit money
     * DECREASES when customers withdraw money or pay charges
     */
    protected function getCustomerAccountGLId(string $accountId = null): int
    {
        $glAccount = GLAccount::where('account_code', '201100')->first();

        if (!$glAccount) {
            throw new \RuntimeException('Customer Deposits GL account (2001) not found');
        }

        return $glAccount->id;
    }

    /**
     * Get Cash Account GL Account (101200) - ASSET
     * Tracks the bank's operational cash holdings
     * INCREASES when customers withdraw (cash goes out to customers)
     * DECREASES when customers deposit (cash comes in from external sources)
     */
    protected function getCashAccountGLId(): int
    {
        $glAccount = GLAccount::where('account_code', '101200')->first();

        if (!$glAccount) {
            throw new \RuntimeException('Cash and Cash Equivalents GL account (1001) not found');
        }

        return $glAccount->id;
    }

    /**
     * Get Fee Income GL Account (401100) - INCOME/REVENUE
     * Tracks revenue earned from customer fees and charges
     * INCREASES when charges are applied to customer accounts
     * This is profit/income for the bank
     */
    protected function getChargeIncomeGLId(?string $chargeId = null): int
    {
        // Use seeded Transaction Fees (401100)
        $glAccount = GLAccount::where('account_code', '401100')->first();

        if (!$glAccount) {
            throw new \RuntimeException('Transaction Fees GL account (401100) not found');
        }

        return $glAccount->id;
    }

    protected function createWithdrawalJournalItems(JournalEntry $journalEntry): void
    {
        // Debit: Cash Account (Asset - cash going out to customer)
        $journalEntry->items()->create(
            [
            'gl_account_id' => $this->getCashAccountGLId(),
            'debit_amount' => $this->amount,
            'credit_amount' => 0,
            'description' => "Cash paid for withdrawal from {$this->sourceAccount->account_number}"
            ]
        );

        // Credit: Customer Deposits (Liability - reduces what bank owes customer)
        $journalEntry->items()->create(
            [
            'gl_account_id' => $this->getCustomerAccountGLId(),
            'debit_amount' => 0,
            'credit_amount' => $this->amount,
            'description' => "Withdrawal from customer account"
            ]
        );
    }

    //    protected function createReversalJournalItems(JournalEntry $journalEntry): void
    //    {
    //        // Get original transaction's journal entries and reverse them
    //        $originalJournal = JournalEntry::where('reference_type', $this->originalTransaction->transaction_type)
    //            ->where('reference_id', $this->originalTransaction->id)
    //            ->first();
    //
    //        if ($originalJournal) {
    //            foreach ($originalJournal->items as $originalItem) {
    //                $journalEntry->items()->create([
    //                    'gl_account_id' => $originalItem->gl_account_id,
    //                    'debit_amount' => $originalItem->credit_amount, // Reverse the amounts
    //                    'credit_amount' => $originalItem->debit_amount,
    //                    'description' => "Reversal: {$originalItem->description}"
    //                ]);
    //            }
    //        }
    //    }

    protected function processChargesAsync(): void
    {
        try {
            $txnType = $this->getChargeTxnType();
            if ($txnType) {
                $this->applyCharges($txnType);
            }
        } catch (Throwable $e) {
            Log::error(
                'Charge processing failed',
                [
                'transaction_id' => $this->id,
                'error' => $e->getMessage()
                ]
            );
            // Don't throw here as charges are optional
        }
    }

    protected function getChargeTxnType(): ?string
    {
        return match ($this->transaction_type) {
            self::TYPE_DEPOSIT => 'deposit',
            self::TYPE_WITHDRAWAL => 'withdrawal',
            self::TYPE_TRANSFER => 'transfer',
            default => null
        };
    }

    protected function applyCharges(string $txnType): void
    {
        cache()->forget('active_charges');

        $accountCharges = $this->sourceAccount->getCharges()
            ->where('txn_type', $txnType);

        foreach ($accountCharges as $charge) {
            $chargeAmount = $charge->calculateCharge($this->amount);
            if ($chargeAmount > 0) {
                $this->createChargeTransaction($charge, $chargeAmount);
            }
        }
    }

    /**
     * Create a charge transaction
     */
    protected function createChargeTransaction(Charge $charge, float $chargeAmount): void
    {
        // Reload account to get fresh balance
        $account = Account::lockForUpdate()->find($this->source_account_id);

        // Check if account has sufficient funds for the charge
        if ($account->available_balance < $chargeAmount && !$account->hasOverdraftFacility()) {
            Log::warning(
                'Insufficient funds for charge',
                [
                'transaction_id' => $this->id,
                'charge_id' => $charge->id,
                'charge_amount' => $chargeAmount,
                'available_balance' => $account->available_balance
                ]
            );
            return; // Skip this charge
        }

        // Create charge transaction
        $chargeTransaction = new self(
            [
            'transaction_type' => self::TYPE_CHARGE,
            'processing_type' => 'intra',
            'processing_channel' => 'system',
            'source_account_id' => $this->source_account_id,
            'currency' => $this->currency,
            'amount' => $chargeAmount,
            'description' => "Charge: {$charge->name} for transaction {$this->internal_reference}",
            'metadata' => [
                'charge_id' => $charge->id,
                'original_transaction_id' => $this->id,
                'charge_type' => $charge->charge_type,
                'original_amount' => $this->amount
            ],
            'created_by_user_id' => $this->created_by_user_id ?? 1
            ]
        );


        // Process the charge transaction separately
        // Dispatch job to process charge accounting
        $chargeTransaction->processChargeTransaction($account, $charge);

        ProcessChargeAccounting::dispatch(
            $charge,                // Charge model
            $this->source_account_id, // string accountId
            $chargeAmount           // float amount
        );
    }

    /**
     * Process a charge transaction (simplified to avoid recursion)
     */
    protected function processChargeTransaction(Account $account, Charge $charge): void
    {
        try {
            // Set balance snapshots
            $this->source_ledger_balance_before = $account->actual_balance;
            $this->source_locked_balance_before = $account->locked_amount;
            $this->source_available_balance_before = $account->available_balance;

            // Deduct charge amount
            $account->actual_balance -= $this->amount;
            $account->available_balance -= $this->amount;

            // Set after balance snapshots
            $this->source_ledger_balance_after = $account->actual_balance;
            $this->source_locked_balance_after = $account->locked_amount;
            $this->source_available_balance_after = $account->available_balance;

            $this->generateInternalReference();
            $this->status = self::STATUS_COMPLETED;
            $this->save();

            // Save account changes
            $account->save();

            // Create journal entry for the charge
            $this->createChargeJournalEntry($charge);

            Log::info(
                'Charge transaction processed successfully',
                [
                'transaction_id' => $this->id,
                'charge_id' => $charge->id,
                'amount' => $this->amount
                ]
            );
        } catch (\Exception $e) {
            $this->status = self::STATUS_FAILED;
            $this->save();

            Log::error(
                'Failed to process charge transaction',
                [
                'transaction_id' => $this->id,
                'charge_id' => $charge->id,
                'error' => $e->getMessage()
                ]
            );

            throw $e;
        }
    }

    /**
     * Create journal entry specifically for charges
     */
    protected function createChargeJournalEntry(Charge $charge): void
    {
        $journalEntry = JournalEntry::create(
            [
            'entry_number' => $this->generateJournalEntryNumber(),
            'entry_date' => now()->toDateString(),
            'reference_type' => 'charge',
            'reference_id' => $this->id,
            'currency' => $this->currency,
            'description' => "Charge: {$charge->name} - {$this->description}",
            'status' => JournalEntry::STATUS_POSTED,
            'created_by_user_id' => $this->created_by_user_id,
            'posted_by_user_id' => $this->created_by_user_id,
            'posted_at' => now()
            ]
        );

        // Create journal entry items
        $this->createChargeJournalItems($journalEntry, $charge);

        // Update GL account balances
        $this->updateGLAccountBalances($journalEntry);
    }

    /**
     * Create journal entry items for charge
     */
    protected function createChargeJournalItems(JournalEntry $journalEntry, Charge $charge): void
    {
        // Get Customer Deposits GL account (201100)
        $customerDepositsGl = GLAccount::where('account_code', '201100')->first();
        if (!$customerDepositsGl) {
            throw new \RuntimeException('Customer Deposits GL account (201100) not found');
        }

        // Get charge income GL account - use specific or default to Transaction Fees
        $chargeIncomeGl = $charge->glAccount ?? GLAccount::where('account_code', '401100')->first();
        if (!$chargeIncomeGl) {
            throw new \RuntimeException('Charge income GL account not found');
        }

        // Debit: Customer Deposits (reduces liability - bank owes customer less)
        $journalEntry->items()->create(
            [
            'gl_account_id' => $customerDepositsGl->id,
            'debit_amount' => $this->amount,
            'credit_amount' => 0,
            'description' => "Charge deduction from account {$this->sourceAccount->account_number}"
            ]
        );

        // Credit: Charge Income (increases revenue)
        $journalEntry->items()->create(
            [
            'gl_account_id' => $chargeIncomeGl->id,
            'debit_amount' => 0,
            'credit_amount' => $this->amount,
            'description' => "Charge income: {$charge->name}"
            ]
        );
    }


    protected function publishStatusUpdate(): void
    {
        Redis::publish(
            'transactions.status',
            json_encode(
                [
                'id' => $this->id,
                'type' => $this->transaction_type,
                'status' => $this->status,
                'amount' => $this->amount,
                'currency' => $this->currency,
                'source_account_id' => $this->source_account_id,
                'destination_account_id' => $this->destination_account_id,
                'updated_at' => $this->updated_at
                ]
            )
        );
    }
}
