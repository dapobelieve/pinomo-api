<?php
namespace App\Exceptions;

use Exception;

class InsufficientFundsException extends Exception
{
    protected $account;
    protected $requiredAmount;
    protected $availableBalance;
    protected $overdraftLimit;

    public function __construct(
        $account,
        $requiredAmount,
        $message = null,
        $code = 422
    ) {
        $this->account = $account;
        $this->requiredAmount = $requiredAmount;
        $this->availableBalance = $account->available_balance;
        $this->overdraftLimit = $account->hasOverdraftFacility() ? $account->getAvailableOverdraft() : 0;

        $message = $message ?? $this->buildMessage();
        parent::__construct($message, $code);
    }

    protected function buildMessage(): string
    {
        if (!$this->account->hasOverdraftFacility()) {
            return sprintf(
                'Insufficient funds. Required amount: %s, Available balance: %s',
                number_format($this->requiredAmount, 2),
                number_format($this->availableBalance, 2)
            );
        }

        return sprintf(
            'Insufficient funds. Required amount: %s, Available balance: %s, Available overdraft: %s',
            number_format($this->requiredAmount, 2),
            number_format($this->availableBalance, 2),
            number_format($this->overdraftLimit, 2)
        );
    }

    public function getAvailableBalance(): float
    {
        return $this->availableBalance;
    }

    public function getRequestedAmount(): float
    {
        return $this->requiredAmount;
    }

    public function getRequiredAmount(): float
    {
        return $this->requiredAmount;
    }

    public function getOverdraftLimit(): float
    {
        return $this->overdraftLimit;
    }

    public function getAccount()
    {
        return $this->account;
    }

    public function render($request)
    {
        return response()->json([
            'error' => 'insufficient_funds',
            'message' => $this->getMessage(),
            'details' => [
                'required_amount' => $this->requiredAmount,
                'available_balance' => $this->availableBalance,
                'overdraft_limit' => $this->overdraftLimit,
                'account_number' => $this->account->account_number
            ]
        ], $this->code);
    }
}