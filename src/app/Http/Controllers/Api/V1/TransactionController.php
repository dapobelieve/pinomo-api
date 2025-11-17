<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientFundsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DepositTransactionRequest;
use App\Http\Requests\LienReleaseAndWithdrawRequest;
use App\Http\Requests\PlaceLienTransactionRequest;
use App\Http\Requests\WithdrawalTransactionRequest;
use App\Models\Client;
use App\Models\Transaction;
use App\Models\Account;
use App\Traits\HttpResponseTrait;
use App\Utils\ResponseUtils;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;

class TransactionController extends Controller
{
    use HttpResponseTrait;

    public function index(Request $request): JsonResponse
    {
        try {
            $this->validateListRequest($request);

            $query = Transaction::query()
            ->with(
                [
                'sourceAccount:id,account_number,account_name,client_id',
                'sourceAccount.client:id,external_id,email,phone',
                'destinationAccount:id,account_number,account_name,client_id',
                'destinationAccount.client:id,external_id,email,phone'
                ]
            );

            $this->applyFilters($query, $request);

            $transactions = $query->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 15);

            $transformedTransactions = $transactions->getCollection()->map(
                function ($transaction) {
                    return $this->transformTransaction($transaction);
                }
            );

            return ResponseUtils::success(
                [
                'transactions' => $transformedTransactions,
                'pagination' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
                'has_more_pages' => $transactions->hasMorePages(),
                ]
                ]
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseUtils::error('Validation failed: ' . $e->getMessage(), 422);
        } catch (Exception $e) {
            Log::error(
                'Transaction list retrieval failed: ' . $e->getMessage(),
                [
                'error' => $e->getMessage()
                ]
            );
            return ResponseUtils::error('Failed to retrieve transactions', 500);
        }
    }

    private function validateListRequest(Request $request): void
    {
        $request->validate(
            [
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'transaction_type' => 'nullable|string|in:deposit,withdrawal,transfer,charge,reversal,lien,lien_release',
            'status' => 'nullable|string|in:pending,processing,completed,failed,reversed,awaiting_compliance',
            'currency' => 'nullable|string|max:3',
            'external_id' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'source_account_id' => 'nullable|uuid|exists:accounts,id',
            'destination_account_id' => 'nullable|uuid|exists:accounts,id',
            'search' => 'nullable|string|max:255',
            ]
        );
    }

    private function applyFilters($query, Request $request): void
    {
        $query->when($request->transaction_type, fn($q) => $q->where('transaction_type', $request->transaction_type))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->currency, fn($q) => $q->where('currency', $request->currency))
            ->when(
                $request->external_id,
                function ($q) use ($request) {
                    $accountIdsSubquery = DB::table('accounts')
                        ->join('clients', 'accounts.client_id', '=', 'clients.id')
                        ->where('clients.external_id', $request->external_id)
                        ->select('accounts.id');

                    $q->where(
                        function ($subQ) use ($accountIdsSubquery) {
                            $subQ->whereIn('source_account_id', $accountIdsSubquery)
                                ->orWhereIn('destination_account_id', $accountIdsSubquery);
                        }
                    );
                }
            )
            ->when(
                $request->start_date,
                fn($q) => $q->where(
                    'created_at',
                    '>=',
                    Carbon::parse($request->start_date)->startOfDay()
                )
            )
            ->when(
                $request->end_date,
                fn($q) => $q->where(
                    'created_at',
                    '<=',
                    Carbon::parse($request->end_date)->endOfDay()
                )
            )
            ->when($request->source_account_id, fn($q) => $q->where('source_account_id', $request->source_account_id))
            ->when(
                $request->destination_account_id,
                fn($q) => $q->where('destination_account_id', $request->destination_account_id)
            )
            ->when(
                $request->search,
                function ($q) use ($request) {
                    $searchTerm = '%' . $request->search . '%';
                    $q->where(
                        function ($query) use ($searchTerm) {
                            $query->where('description', 'like', $searchTerm)
                                ->orWhere('internal_reference', 'like', $searchTerm)
                                ->orWhere('external_reference', 'like', $searchTerm)
                                ->orWhere('ramp_reference', 'like', $searchTerm);
                        }
                    );
                }
            );
    }

    public function deposit(DepositTransactionRequest $request, string $account_id): JsonResponse
    {
        $data = $request->validated();
        $client = Client::where('external_id', $data['external_id'])->first();

        if (!$client) {
            return ResponseUtils::error('Client record not found', 404);
        }

        try {
            DB::beginTransaction();

            $account = Account::lockForUpdate()->where('client_id', $client->id)->where('id', $account_id)->first();

            if (!$account) {
                throw new ModelNotFoundException('Account not found for this client');
            }

            $data['processing_type'] = 'intra';
            $data['processing_channel'] = 'api';
            $data['external_reference'] = $data['transaction_reference'];

            $transaction = Transaction::createDeposit($account, $data);

            DB::commit();

            return $this->successResponse(
                [
                'transaction_id' => $transaction->id,
                'client_id' =>  $client->id,
                'account_id' => $account_id,
                'status' => $transaction->status
                ],
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(
                'Deposit failed: ' . $e->getMessage(),
                [
                'account_id' => $request->account_id,
                'amount' => $request->amount,
                'error' => $e->getMessage()
                ]
            );
            return ResponseUtils::error('Failed to process deposit: ' . $e->getMessage());
        }
    }

    public function withdraw(WithdrawalTransactionRequest $request, string $account_id): JsonResponse
    {
        $data = $request->validated();
        $client = Client::where('external_id', $data['external_id'])->first();

        if (!$client) {
            return ResponseUtils::error('Client record not found', 404);
        }

        try {
            DB::beginTransaction();

            $account = Account::lockForUpdate()
                ->where('client_id', $client->id)
                ->where('id', $account_id)
                ->first();

            if (!$account) {
                throw new ModelNotFoundException('Account not found for this client');
            }

            // Validate account status
            if ($account->status !== 'active') {
                throw new \InvalidArgumentException('Account is not active for withdrawals');
            }

            // Prepare transaction data following the same format as deposit
            $data['processing_type'] = 'intra';
            $data['processing_channel'] = 'api';
            $data['external_reference'] = $data['transaction_reference'];


            $transaction = Transaction::createWithdrawal($account, $data);

            DB::commit();

            return $this->successResponse(
                [
                'transaction_id' => $transaction->id,
                'status' => $transaction->status,
                'amount' => $transaction->amount,
                'account_balance' => [
                    'previous_available_balance' => $transaction->source_available_balance_before,
                    'current_available_balance' => $transaction->source_available_balance_after,
                    'previous_ledger_balance' => $transaction->source_ledger_balance_before,
                    'current_ledger_balance' => $transaction->source_ledger_balance_after
                ]
                ],
                201
            );
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            Log::error(
                'Account not found for withdrawal',
                [
                'account_id' => $account_id,
                'client_external_id' => $data['external_id'],
                'error' => $e->getMessage()
                ]
            );
            return ResponseUtils::error('Account not found', 404);
        } catch (InsufficientFundsException $e) {
            DB::rollBack();
            Log::warning(
                'Insufficient funds for withdrawal',
                [
                'account_id' => $account_id,
                'amount' => $data['amount'],
                'available_balance' => $e->getAvailableBalance(),
                'requested_amount' => $e->getRequestedAmount(),
                'error' => $e->getMessage()
                ]
            );
            return ResponseUtils::error($e->getMessage(), 422);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();
            Log::warning(
                'Invalid withdrawal request',
                [
                'account_id' => $account_id,
                'amount' => $data['amount'],
                'error' => $e->getMessage()
                ]
            );
            return ResponseUtils::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(
                'Withdrawal failed: ' . $e->getMessage(),
                [
                'account_id' => $account_id,
                'amount' => $data['amount'],
                'client_external_id' => $data['external_id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
                ]
            );
            return ResponseUtils::error('Failed to process withdrawal: ' . $e->getMessage(), 500);
        }
    }

    public function placeLien(PlaceLienTransactionRequest $request, string $account_id): JsonResponse
    {
        $data = $request->validated();
        $client = Client::where('external_id', $data['external_id'])->first();

        if (!$client) {
            return ResponseUtils::error('Client record not found', 404);
        }

        try {
            DB::beginTransaction();

            $account = Account::lockForUpdate()
                ->where('client_id', $client->id)
                ->where('id', $account_id)
                ->first();

            if (!$account) {
                return ResponseUtils::error('Account not found', 404);
            }

            $data['processing_type'] = 'intra';
            $data['processing_channel'] = 'api';
            $data['external_reference'] = $data['transaction_reference'];

            $transaction = Transaction::createLien($account, $data);

            DB::commit();

            return $this->successResponse(
                [
                'transaction_id' => $transaction->id,
                'status' => $transaction->status
                ],
                201
            );
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return ResponseUtils::error('Account not found', 404);
        } catch (InsufficientFundsException $e) {
            DB::rollBack();
            return ResponseUtils::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(
                'Lien placement failed: ' . $e->getMessage(),
                [
                'account_id' => $account_id,
                'amount' => $request->amount,
                'error' => $e->getMessage()
                ]
            );
            return ResponseUtils::error('Failed to place lien: ' . $e->getMessage());
        }
    }

    public function history(Request $request, string $accountId): JsonResponse
    {
        try {
            // Validate the account exists
            $account = Account::findOrFail($accountId);

            $transactions = $this->getAccountTransactions($request, $accountId);

            $transformedTransactions = $transactions->getCollection()->map(
                function ($transaction) use ($accountId) {
                    return $this->transformTransaction($transaction, $accountId);
                }
            );

            return ResponseUtils::success(
                [
                'transactions' => $transformedTransactions,
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                    'last_page' => $transactions->lastPage(),
                    'from' => $transactions->firstItem(),
                    'to' => $transactions->lastItem(),
                    'has_more_pages' => $transactions->hasMorePages(),
                ]
                ]
            );
        } catch (ModelNotFoundException $e) {
            return ResponseUtils::error('Account not found', 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseUtils::error('Validation failed: ' . $e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error(
                'Transaction history retrieval failed: ' . $e->getMessage(),
                [
                'account_id' => $accountId,
                'error' => $e->getMessage()
                ]
            );
            return ResponseUtils::error('Failed to retrieve transaction history', 500);
        }
    }

    public function allHistory(Request $request): JsonResponse
    {
        try {
            $this->validateListRequest($request);

            $query = Transaction::query()
                ->with(
                    [
                    'sourceAccount:id,account_number,account_name,client_id',
                    'sourceAccount.client:id,external_id,email',

                    'destinationAccount:id,account_number,account_name,client_id',
                    'destinationAccount.client:id,external_id,email'
                    ]
                );

            $this->applyFilters($query, $request);

            $transactions = $query->orderBy('created_at', 'desc')
                ->paginate($request->per_page ?? 15);

            $transformedTransactions = $transactions->getCollection()->map(
                function ($transaction) {
                    return $this->transformTransaction($transaction);
                }
            );

            return ResponseUtils::success(
                [
                'transactions' => $transformedTransactions,
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                    'last_page' => $transactions->lastPage(),
                    'from' => $transactions->firstItem(),
                    'to' => $transactions->lastItem(),
                    'has_more_pages' => $transactions->hasMorePages(),
                ]
                ]
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseUtils::error('Validation failed: ' . $e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error(
                'All transaction history retrieval failed: ' . $e->getMessage(),
                [
                'error' => $e->getMessage()
                ]
            );
            return ResponseUtils::error('Failed to retrieve transaction history', 500);
        }
    }

    private function getAccountTransactions(Request $request, string $accountId)
    {
        // Use OR conditions instead of UNION for proper pagination with filters
        $query = Transaction::query()
            ->where(
                function ($q) use ($accountId) {
                    $q->where('source_account_id', $accountId)
                        ->orWhere('destination_account_id', $accountId);
                }
            )
            ->with(
                [
                'sourceAccount:id,account_number,account_name,client_id',
                'sourceAccount.client:id,external_id,email',
                'destinationAccount:id,account_number,account_name,client_id',
                'destinationAccount.client:id,external_id,email'
                ]
            );

        // Apply filters to the unified query
        $this->applyFilters($query, $request);
        return $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);
    }

    private function transformTransaction($transaction, string $accountId = null): array
    {
        $isSource = $accountId ? $transaction->source_account_id === $accountId : false;
        $direction = $this->getTransactionDirection($transaction, $isSource);

        $balances = $this->getTransactionBalances($transaction, $isSource);

        return [
            'id' => $transaction->id,
            'internal_reference' => $transaction->internal_reference,
            'external_reference' => $transaction->external_reference,
            'processor_reference' => $transaction->processor_reference,
            'transaction_type' => $transaction->transaction_type,
            'processing_type' => $transaction->processing_type,
            'processing_channel' => $transaction->processing_channel,
            'direction' => $direction,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'balances' => $balances,
            'status' => $transaction->status,
            'description' => $transaction->description,
            'metadata' => $transaction->metadata,
            'created_at' => $transaction->created_at,
            'approved_at' => $transaction->approved_at,
            'source_account' => $transaction->sourceAccount ? [
                'id' => $transaction->sourceAccount->id,
                'account_number' => $transaction->sourceAccount->account_number,
                'account_name' => $transaction->sourceAccount->account_name,
                'client' => ($transaction->sourceAccount->client ?? null) ? [
                    'id' => $transaction->sourceAccount->client->id,
                    'external_id' => $transaction->sourceAccount->client->external_id,
                    'email' => $transaction->sourceAccount->client->email,
                ] : null,
            ] : null,
            'destination_account' => $transaction->destinationAccount ? [
                'id' => $transaction->destinationAccount->id,
                'account_number' => $transaction->destinationAccount->account_number,
                'account_name' => $transaction->destinationAccount->account_name,
                'client' => ($transaction->destinationAccount->client ?? null) ? [
                    'id' => $transaction->destinationAccount->client->id,
                    'external_id' => $transaction->destinationAccount->client->external_id,
                    'email' => $transaction->destinationAccount->client->email,
                ] : null,
            ] : null,
            'is_reversal' => $transaction->transaction_type === Transaction::TYPE_REVERSAL,
            'is_reversed' => !is_null($transaction->reversal_transaction_id),
            'can_reverse' => $transaction->isReversible(),
            'original_transaction_id' => $transaction->original_transaction_id,
        ];
    }

    private function getTransactionDirection($transaction, bool $isSource): string
    {
        switch ($transaction->transaction_type) {
            case Transaction::TYPE_DEPOSIT:
                return 'credit';

            case Transaction::TYPE_WITHDRAWAL:
                return 'debit';

            case Transaction::TYPE_TRANSFER:
                return $isSource ? 'debit' : 'credit';

            case Transaction::TYPE_LIEN:
            case Transaction::TYPE_CHARGE:
                return 'debit';

            case Transaction::TYPE_REVERSAL:
                if ($transaction->originalTransaction) {
                    $originalDirection = $this->getTransactionDirection($transaction->originalTransaction, $isSource);
                    return $originalDirection === 'debit' ? 'credit' : 'debit';
                }
                return 'credit';

            case Transaction::TYPE_LIEN_RELEASE:
                return 'credit';

            default:
                return $isSource ? 'debit' : 'credit';
        }
    }

    private function getTransactionBalances($transaction, bool $isSource): array
    {
        if ($isSource) {
            return [
                'ledger' => [
                    'before' => $transaction->source_ledger_balance_before,
                    'after' => $transaction->source_ledger_balance_after,
                ],
                'available' => [
                    'before' => $transaction->source_available_balance_before,
                    'after' => $transaction->source_available_balance_after,
                ],
                'locked' => [
                    'before' => $transaction->source_locked_balance_before,
                    'after' => $transaction->source_locked_balance_after,
                ]
            ];
        }




        return [
            'ledger' => [
                'before' => $transaction->destination_ledger_balance_before,
                'after' => $transaction->destination_ledger_balance_after,
            ],
            'available' => [
                'before' => $transaction->destination_available_balance_before,
                'after' => $transaction->destination_available_balance_after,
            ],
            'locked' => [
                'before' => $transaction->destination_locked_balance_before,
                'after' => $transaction->destination_locked_balance_after,
            ]
        ];
    }

    public function releaseLien(Request $request, Transaction $transaction): JsonResponse
    {
        try {
            // Validate that the transaction is actually a lien
            if ($transaction->transaction_type !== Transaction::TYPE_LIEN) {
                return ResponseUtils::error('Transaction is not a lien transaction', 422);
            }

            // Validate webhook URL if provided
            $request->validate(
                [
                'webhook_url' => 'nullable|url'
                ]
            );

            // Use the new Transaction method to dispatch job
            $webhookUrl = $request->webhook_url ?? config('sendman.notification_url');
            $transaction->processLienRelease($webhookUrl);

            return ResponseUtils::success(
                [
                'message' => 'Lien release job has been queued for processing',
                'transaction_id' => $transaction->id,
                'status' => 'queued'
                ],
                202
            );
        } catch (Exception $e) {
            Log::error(
                'Failed to queue lien release job: ' . $e->getMessage(),
                [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
                ]
            );
            return ResponseUtils::error('Failed to queue lien release: ' . $e->getMessage());
        }
    }

    public function releaseAndWithdraw(LienReleaseAndWithdrawRequest $request, Transaction $transaction): JsonResponse
    {
        try {
            $data = $request->validated();



            $client = Client::where('external_id', $data['external_id'])->first();
            if (!$client) {
                return ResponseUtils::error('Client record not found', 404);
            }

            $account = Account::where('client_id', $client->id)
                ->where('id', $transaction->source_account_id)
                ->first();
            if (!$account) {
                return ResponseUtils::error('Account not found for this client', 404);
            }

            $data['processing_type'] = 'intra';
            $data['processing_channel'] = 'api';
            $data['external_reference'] = $data['transaction_reference'] ?? null;

            $webhookUrl = $request->webhook_url ?? config('sendman.notification_url');
            $transaction->processReleaseAndWithdraw($data, $webhookUrl);

            return ResponseUtils::success(
                [
                'message' => 'Release and withdraw job has been queued for processing',
                'transaction_id' => $transaction->id,
                'status' => 'queued'
                ],
                202
            );
        } catch (\Exception $e) {
            Log::error(
                'Failed to queue release and withdraw job: ' . $e->getMessage(),
                [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
                ]
            );
            return ResponseUtils::error('Failed to queue release and withdraw: ' . $e->getMessage());
        }
    }

    public function reverse(Transaction $transaction): JsonResponse
    {
        try {
            DB::beginTransaction();

            $reversal = $transaction->createReversal();

            DB::commit();

            return ResponseUtils::success($reversal, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(
                'Transaction reversal failed: ' . $e->getMessage(),
                [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
                ]
            );
            return ResponseUtils::error('Failed to reverse transaction: ' . $e->getMessage());
        }
    }

    public function statement(Request $request, $accountId): JsonResponse
    {
        $account = Account::where(['id' => $accountId])->first();
        ;

        if (!$account) {
            return ResponseUtils::error('Account not found', 404);
        }


        try {
            $startDate = $request->start_date ? Carbon::parse($request->start_date) : now()->startOfMonth();
            $endDate = $request->end_date ? Carbon::parse($request->end_date) : now();

            if ($startDate->gt($endDate)) {
                return ResponseUtils::error('Start date cannot be after end date', 422);
            }

            $statement = [
                'account_number' => $account->account_number,
                'account_name' => $account->account_name,
                'currency' => $account->currency,
                'period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString()
                ],
                'opening_balance' => $account->getBalanceAsOf($startDate),
                'closing_balance' => $account->getBalanceAsOf($endDate),
                'transactions' => Transaction::where('source_account_id', $account->id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderBy('created_at')
                    ->get()
                    ->map(
                        function ($transaction) {
                            return [
                            'date' => $transaction->created_at->toDateTimeString(),
                            'reference' => $transaction->internal_reference,
                            'source_available_balance_before' => $transaction->source_available_balance_before,
                            'type' => $transaction->transaction_type,
                            'description' => $transaction->description,
                            'amount' => $transaction->amount,
                            'balance' => $transaction->source_ledger_balance_after
                            ];
                        }
                    )
            ];

            return ResponseUtils::success($statement);
        } catch (\Exception $e) {
            Log::error(
                'Statement generation failed: ' . $e->getMessage(),
                [
                'account_id' => $account->id,
                'error' => $e->getMessage()
                ]
            );
            return ResponseUtils::error('Failed to generate statement: ' . $e->getMessage());
        }
    }

    public function show(string $transaction_id): JsonResponse
    {
        try {
            $transaction = Transaction::with(
                [
                'sourceAccount', 'destinationAccount', 'originalTransaction', 'reversalTransaction']
            )
                ->findOrFail($transaction_id);

            return ResponseUtils::success($transaction);
        } catch (ModelNotFoundException $e) {
            return ResponseUtils::error('Transaction not found', 404);
        } catch (\Exception $e) {
            Log::error(
                'Failed to retrieve transaction: ' . $e->getMessage(),
                [
                'transaction_id' => $transaction_id,
                'error' => $e->getMessage()
                ]
            );
            return ResponseUtils::error('Failed to retrieve transaction', 500);
        }
    }

    public function status(string $transaction_id): JsonResponse
    {
        try {
            $transaction = Transaction::select(['id', 'status', 'job_id', 'webhook_url', 'created_at', 'updated_at'])
                ->findOrFail($transaction_id);

            $response = [
                'transaction_id' => $transaction->id,
                'status' => $transaction->status,
                'job_id' => $transaction->job_id,
                'webhook_url' => $transaction->webhook_url,
                'created_at' => $transaction->created_at,
                'updated_at' => $transaction->updated_at
            ];

            // If job_id exists, get webhook events for this transaction
            if ($transaction->job_id) {
                $webhookEvents = \App\Models\WebhookEvent::where('payload->job_id', $transaction->job_id)
                    ->orderBy('created_at', 'desc')
                    ->get(['webhook_id', 'status', 'attempt', 'delivered_at', 'failed_at']);

                $response['webhook_events'] = $webhookEvents;
            }

            return ResponseUtils::success($response);
        } catch (ModelNotFoundException $e) {
            return ResponseUtils::error('Transaction not found', 404);
        } catch (Exception $e) {
            Log::error(
                'Failed to retrieve transaction status: ' . $e->getMessage(),
                [
                'transaction_id' => $transaction_id,
                'error' => $e->getMessage()
                ]
            );
            return ResponseUtils::error('Failed to retrieve transaction status', 500);
        }
    }

    public function transactionLog(Request $request, Account $account): JsonResponse
    {
        try {
            $query = Transaction::where('source_account_id', $account->id)
                ->when(
                    $request->has('transaction_type'),
                    function ($query) use ($request) {
                        $query->where('transaction_type', $request->transaction_type);
                    }
                )
                ->when(
                    $request->has('status'),
                    function ($query) use ($request) {
                        $query->where('status', $request->status);
                    }
                )
                ->when(
                    $request->has('start_date'),
                    function ($query) use ($request) {
                        $query->where('created_at', '>=', Carbon::parse($request->start_date));
                    }
                )
                ->when(
                    $request->has('end_date'),
                    function ($query) use ($request) {
                        $query->where('created_at', '<=', Carbon::parse($request->end_date));
                    }
                )
                ->orderBy('created_at', 'desc');

            return ResponseUtils::success($query->paginate());
        } catch (\Exception $e) {
            Log::error(
                'Transaction log retrieval failed: ' . $e->getMessage(),
                [
                'account_id' => $account->id,
                'error' => $e->getMessage()
                ]
            );
            return ResponseUtils::error('Failed to retrieve transaction log: ' . $e->getMessage());
        }
    }
}
