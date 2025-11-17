<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\AccountCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Requests\CloseAccountRequest;
use App\Http\Requests\LockAccountFundsRequest;
use App\Http\Requests\UnlockAccountFundsRequest;
use App\Models\Account;
use App\Models\Client;
use App\Models\Product;
use App\Traits\HttpResponseTrait;
use App\Utils\ResponseUtils;
use App\Exceptions\InsufficientFundsException;
use App\Models\Transaction;
use App\Models\Charge;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    use HttpResponseTrait;

    public function index(Request $request)
    {
        try {
            $accounts = [];
            $query = Account::query();

            if ($request->external_id) {
                $client = Client::where('external_id', $request->external_id)->first();

                if (!$client) {
                    return ResponseUtils::error('Client not found with the provided external_id', 404);
                }

                $accounts = $query->where('client_id', $client->id)->get();
            } elseif ($request->client_id) {
                $accounts  = $query->where('client_id', $request->client_id)->get();
            } else {
                $accounts = $query->get();
            }

            return ResponseUtils::success($accounts);
        } catch (\Exception $e) {
            Log::error('Failed to fetch accounts: ' . $e->getMessage());
            return ResponseUtils::error('Failed to fetch accounts');
        }
    }

    public function store(StoreAccountRequest $request)
    {
        try {
            $data = $request->validated();
            $data['account_number'] = $data['account_number'] ?? $this->generateAccountNumber();
            $data['status'] = $data['status'] ?? Account::STATUS_ACTIVE;

            $client = Client::where('external_id', $data['external_id'])->first();

            if (!$client) {
                return $this->errorResponse('Client record not found', 404);
            }


            $existingAccount = Account::where('client_id', $client->id)
            ->where('product_id', $data['product_id'])
            ->where('account_type', $data['account_type'])
            ->where('status', '!=', 'closed')
            ->first();

            if ($existingAccount) {
                return $this->successResponse(
                    [
                    'id' => $existingAccount->id,
                    'external_id' => $client->external_id,
                    'account_name' => $existingAccount->account_name,
                    'available_balance' => $existingAccount->available_balance,
                    'actual_balance' => $existingAccount->actual_balance,
                    'status' => $existingAccount->status,
                    ],
                    'Wallet account retrieved',
                    200
                );
            }


            /**
             * added this to set specific ids for
             * accounts from middleman
             */
            if ($request->has('id')) {
                $data['id'] = $request->input('id');
            }

            $product = Product::find($data['product_id']);


            $data['client_id'] = $client->id;
            $data['product_id'] = $product->id;
            $data['currency'] = $product->currency;
            $data['created_by_user_id'] = $client->id;

            DB::beginTransaction();

            $account = Account::create($data);

            DB::commit();

            return $this->successResponse(
                [
                'id' => $account->id,
                'external_id' => $client->external_id,
                'account_name' => $account->account_name,
                'available_balance' => $account->available_balance ?? 0.00,
                'actual_balance' => $account->actual_balance ?? 0.00,
                'status' => $account->status,
                ],
                'Account created successfully',
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create account: ' . $e->getMessage());
            return $this->errorResponse('Failed to create account');
        }
    }

    public function getBalance(Request $request, Account $account)
    {
        try {
            $account->load(['client', 'product']);
            return ResponseUtils::success($account);
        } catch (\Exception $e) {
            Log::error('Failed to fetch account details: ' . $e->getMessage());
            return ResponseUtils::error('Failed to fetch account details');
        }
    }

    public function show($accountId)
    {
        try {
            $account = Account::findOrFail($accountId);
            $account->load(['client', 'product', 'balanceHistory' => fn($q) => $q->latest()->limit(10)]);
            return ResponseUtils::success($account);
        } catch (ModelNotFoundException $e) {
            return ResponseUtils::error('Account not found', 404);
        } catch (\Exception $e) {
            Log::error('Failed to fetch account details: ' . $e->getMessage());
            return ResponseUtils::error('Failed to fetch account details');
        }
    }

    public function update(UpdateAccountRequest $request, Account $account)
    {
        try {
            DB::beginTransaction();

            $account->update($request->validated());
            $account->load(['client', 'product']);

            DB::commit();

            return ResponseUtils::success($account, 'Account updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update account: ' . $e->getMessage());
            return ResponseUtils::error('Failed to update account');
        }
    }

    public function close(CloseAccountRequest $request, Account $account)
    {
        try {
            DB::beginTransaction();

            if (!$account->close($request->closure_reason)) {
                throw new \Exception('Failed to close account');
            }

            $account->load(['client', 'product']);

            DB::commit();

            return ResponseUtils::success($account, 'Account closed successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to close account: ' . $e->getMessage());
            return ResponseUtils::error('Failed to close account');
        }
    }

    public function lockFunds(LockAccountFundsRequest $request, Account $account)
    {
        try {
            DB::beginTransaction();

            if (!$account->lock($request->amount)) {
                throw new \Exception('Failed to lock funds');
            }

            DB::commit();

            return ResponseUtils::success($account, 'Funds locked successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to lock funds: ' . $e->getMessage());
            return ResponseUtils::error('Failed to lock funds');
        }
    }

    public function unlockFunds(UnlockAccountFundsRequest $request, Account $account)
    {
        try {
            DB::beginTransaction();

            if (!$account->unlock($request->amount)) {
                throw new \Exception('Failed to unlock funds');
            }

            DB::commit();

            return ResponseUtils::success($account, 'Funds unlocked successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to unlock funds: ' . $e->getMessage());
            return ResponseUtils::error('Failed to unlock funds');
        }
    }

    protected function generateAccountNumber(): string
    {
        do {
            $number = date('Y') . str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (Account::where('account_number', $number)->exists());

        return $number;
    }

    public function initiate(InitiateAccountRequest $request)
    {
        try {
            DB::beginTransaction();

            // Create the account in pending activation status
            $data = $request->validated();
            $data['account_number'] = $data['account_number'] ?? $this->generateAccountNumber();
            $data['created_by_user_id'] = auth()->id();
            $data['status'] = Account::STATUS_PENDING_ACTIVATION;

            $account = Account::create($data);

            // Process KYC documents
            foreach ($request->file('kyc_documents') as $document) {
                $path = $document['file']->store('kyc_documents/accounts', 'private');

                $account->client->kycDocuments()->create(
                    [
                    'document_type' => $document['type'],
                    'file_path' => $path,
                    'status' => 'pending_review',
                    'uploaded_by_user_id' => auth()->id(),
                    ]
                );
            }

            $account->load(['client', 'product']);

            DB::commit();

            return ResponseUtils::success($account, 'Account initiated successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to initiate account: ' . $e->getMessage());
            return ResponseUtils::error('Failed to initiate account');
        }
    }

    public function activate(ActivateAccountRequest $request, Account $account)
    {
        try {
            DB::beginTransaction();

            if (!$account->activate()) {
                throw new \Exception('Failed to activate account');
            }

            // Update KYC documents status if all are verified
            $pendingDocs = $account->client->kycDocuments()
                ->where('status', 'pending_review')
                ->exists();

            if (!$pendingDocs) {
                $account->client->kycDocuments()
                    ->update(['status' => 'verified']);
            }

            // Record activation details
            $account->update(
                [
                'last_activity_at' => now(),
                'activation_remarks' => $request->activation_remarks,
                ]
            );

            $account->load(['client', 'product']);

            DB::commit();

            return ResponseUtils::success($account, 'Account activated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to activate account: ' . $e->getMessage());
            return ResponseUtils::error('Failed to activate account');
        }
    }

    public function overdraft(Request $request, Account $account)
    {
        $validated = $request->validate(
            [
            'status' => 'required|boolean',
            'limit' => 'required_if:status,true|numeric|min:0|nullable',
            'interest_rate' => 'required_if:status,true|numeric|min:0|max:100|nullable'
            ]
        );

        if ($validated['status']) {
            if ($account->enableOverdraft($validated['limit'], $validated['interest_rate'])) {
                return ResponseUtils::success(
                    $account->fresh(),
                    'Overdraft facility enabled successfully'
                );
            }
            return ResponseUtils::error(
                'Failed to enable overdraft facility',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } else {
            if ($account->disableOverdraft()) {
                return ResponseUtils::success(
                    $account->fresh(),
                    'Overdraft facility disabled successfully'
                );
            }
            return ResponseUtils::error(
                'Cannot disable overdraft while account is overdrawn',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

        /**
         * Update the single transaction limit for an account.
         *
         * @param  UpdateAccountTransactionLimitRequest $request
         * @param  Account                              $account
         * @return JsonResponse
         */
    public function updateTransactionLimit(UpdateAccountTransactionLimitRequest $request, Account $account): JsonResponse
    {
        try {
            DB::beginTransaction();

            $account->single_transaction_limit = $request->single_transaction_limit;
            $account->save();

            // Log the change
            Log::info(
                'Account transaction limit updated',
                [
                'account_id' => $account->id,
                'old_limit' => $account->getOriginal('single_transaction_limit'),
                'new_limit' => $account->single_transaction_limit,
                'updated_by' => auth()->id()
                ]
            );

            DB::commit();

            return ResponseUtils::success(
                [
                'message' => 'Transaction limit updated successfully',
                'account' => $account
                ]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error(
                'Failed to update account transaction limit',
                [
                'account_id' => $account->id,
                'error' => $e->getMessage()
                ]
            );

            return ResponseUtils::error(
                'Failed to update transaction limit',
                500
            );
        }
    }
}
