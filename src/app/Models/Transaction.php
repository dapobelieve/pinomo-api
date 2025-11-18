<?php

namespace App\Models;

use App\Exceptions\InsufficientFundsException;
use App\Jobs\ProcessChargeAccounting;
use App\Jobs\ProcessTransactionAggregate;
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
        'destination_ledger_balance_before',
        'destination_locked_balance_before',
        'destination_available_balance_before',
        'destination_ledger_balance_after',
        'destination_locked_balance_after',
        'destination_available_balance_after',
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
        'destination_ledger_balance_before' => 'decimal:4',
        'destination_locked_balance_before' => 'decimal:4',
        'destination_available_balance_before' => 'decimal:4',
        'destination_ledger_balance_after' => 'decimal:4',
        'destination_locked_balance_after' => 'decimal:4',
        'destination_available_balance_after' => 'decimal:4',
        'metadata' => 'json',
        'approved_at' => 'datetime'
    ];

    // Transaction Types
    public const TYPE_DEPOSIT = 'deposit';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_CHARGE = 'charge';
    public const TYPE_REVERSAL = 'reversal';

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
            'created_by_user_id' => $data['created_by_user_id'] ?? auth()->id()
            ]
        );


        $transaction->processAndSave($account);
        return $transaction;
    }

    public static function createTransfer(Account $sourceAccount, Account $destinationAccount, array $data): self
    {
        self::validateTransferAccounts($sourceAccount, $destinationAccount);

        $transferAmount = $data['amount'];

        self::validateSufficientBalance($sourceAccount, $transferAmount);

        $commissionAmount = self::calculateTransferCommission($sourceAccount, $transferAmount);
        $transaction = self::buildTransferTransaction($sourceAccount, $destinationAccount, $data, $transferAmount, $commissionAmount);

        $transaction->processTransferAndSave($sourceAccount, $destinationAccount);

        return $transaction;
    }

    private static function validateTransferAccounts(Account $sourceAccount, Account $destinationAccount): void
    {
        if ($sourceAccount->id === $destinationAccount->id) {
            throw new InvalidArgumentException('Cannot transfer to the same account');
        }

        if ($sourceAccount->currency !== $destinationAccount->currency) {
            throw new InvalidArgumentException('Cross-currency transfers are not supported');
        }

        if ($sourceAccount->status !== Account::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Source account is not active');
        }

        if ($destinationAccount->status !== Account::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Destination account is not active');
        }
    }

    private static function calculateTransferCommission(Account $account, float $amount): float
    {
        $charges = $account->getCharges();
        $totalCommission = 0;

        foreach ($charges as $charge) {
            if ($charge->transaction_type === self::TYPE_TRANSFER) {
                $totalCommission += $charge->calculateCharge($amount);
            }
        }

        return $totalCommission;
    }

    private static function validateSufficientBalance(Account $account, float $requiredAmount): void
    {
        if ($account->available_balance < $requiredAmount) {
            throw new InsufficientFundsException(
                $account,
                $requiredAmount,
                "Insufficient balance for transfer. Required: {$requiredAmount}, Available: {$account->available_balance}"
            );
        }
    }

    private static function buildTransferTransaction(Account $sourceAccount, Account $destinationAccount, array $data, float $amount, float $commission): self
    {
        return new self([
            'transaction_type' => self::TYPE_TRANSFER,
            'processing_type' => $data['processing_type'] ?? self::PROCESSING_INTERNAL,
            'processing_channel' => $data['processing_channel'] ?? 'api',
            'source_account_id' => $sourceAccount->id,
            'destination_account_id' => $destinationAccount->id,
            'external_reference' => $data['transaction_reference'],
            'currency' => $sourceAccount->currency,
            'amount' => $amount,
            'description' => $data['description'] ?? 'Wallet transfer',
            'metadata' => array_merge(
                $data['metadata'] ?? [],
                [
                    'commission_amount' => $commission,
                    'total_debit' => $amount,
                    'transfer_channel' => $data['processing_channel'] ?? 'api'
                ]
            ),
            'status' => self::STATUS_PENDING,
            'created_by_user_id' => $data['created_by_user_id'] ?? auth()->id()
        ]);
    }

    protected function processTransferAndSave(Account $sourceAccount, Account $destinationAccount)
    {
        try {
            DB::beginTransaction();

            $this->setSourceBalanceSnapshots($sourceAccount);
            $this->setDestinationBalanceSnapshots($destinationAccount);

            $this->updateTransferBalances($sourceAccount, $destinationAccount);

            $this->setSourceBalanceSnapshotsAfter($sourceAccount);
            $this->setDestinationBalanceSnapshotsAfter($destinationAccount);

            $this->generateInternalReference();
            $this->status = self::STATUS_PROCESSING;
            $this->save();

            if (!$sourceAccount->save() || !$destinationAccount->save()) {
                throw new \RuntimeException('Concurrent modification detected');
            }

            $this->processJournalEntriesAsync();
            $this->processChargesAsync();
            $this->publishStatusUpdate();

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

    private function setSourceBalanceSnapshots(Account $account): void
    {
        $this->source_ledger_balance_before = $account->actual_balance;
        $this->source_locked_balance_before = $account->locked_amount;
        $this->source_available_balance_before = $account->available_balance;
    }

    private function setDestinationBalanceSnapshots(Account $account): void
    {
        $this->destination_ledger_balance_before = $account->actual_balance;
        $this->destination_locked_balance_before = $account->locked_amount;
        $this->destination_available_balance_before = $account->available_balance;
    }

    private function setSourceBalanceSnapshotsAfter(Account $account): void
    {
        $this->source_ledger_balance_after = $account->actual_balance;
        $this->source_locked_balance_after = $account->locked_amount;
        $this->source_available_balance_after = $account->available_balance;
    }

    private function setDestinationBalanceSnapshotsAfter(Account $account): void
    {
        $this->destination_ledger_balance_after = $account->actual_balance;
        $this->destination_locked_balance_after = $account->locked_amount;
        $this->destination_available_balance_after = $account->available_balance;
    }

    private function updateTransferBalances(Account $sourceAccount, Account $destinationAccount): void
    {
        $sourceAccount->actual_balance -= $this->amount;
        $sourceAccount->available_balance -= $this->amount;

        $destinationAccount->actual_balance += $this->amount;
        $destinationAccount->available_balance += $this->amount;
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
            if (in_array($this->transaction_type, [self::TYPE_DEPOSIT, self::TYPE_TRANSFER])) {
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
            self::TYPE_TRANSFER => "Transfer from {$this->sourceAccount->account_number} to {$this->destinationAccount->account_number}",
            self::TYPE_CHARGE => "Charge on account {$this->sourceAccount->account_number} - {$this->description}",
            self::TYPE_REVERSAL => "Reversal of transaction {$this->originalTransaction->internal_reference}",
            default => $this->description ?? 'Transaction'
        };
    }

    protected function createJournalEntryItems(JournalEntry $journalEntry): void
    {
        switch ($this->transaction_type) {
            case self::TYPE_DEPOSIT:
                $this->createDepositJournalItems($journalEntry);
                break;
            case self::TYPE_TRANSFER:
                $this->createTransferJournalItems($journalEntry);
                break;
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

    protected function createTransferJournalItems(JournalEntry $journalEntry): void
    {
        $sourceAccountNumber = $this->sourceAccount->account_number;
        $destinationAccountNumber = $this->destinationAccount->account_number;

        $journalEntry->items()->create([
            'gl_account_id' => $this->getCustomerAccountGLId(),
            'debit_amount' => $this->amount,
            'credit_amount' => 0,
            'description' => "Transfer from account {$sourceAccountNumber}"
        ]);

        $journalEntry->items()->create([
            'gl_account_id' => $this->getCustomerAccountGLId(),
            'debit_amount' => 0,
            'credit_amount' => $this->amount,
            'description' => "Transfer to account {$destinationAccountNumber}"
        ]);
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
            'description' => "Charge: {$this->internal_reference}",
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
