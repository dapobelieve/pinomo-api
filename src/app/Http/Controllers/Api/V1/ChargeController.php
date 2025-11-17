<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\ChargeStoreRequest;
use App\Http\Requests\V1\ChargeUpdateRequest;
use App\Http\Resources\ChargeResource;
use App\Models\Account;
use App\Models\Charge;
use App\Traits\HttpResponseTrait;
use App\Utils\ResponseUtils;
use App\Exceptions\InsufficientFundsException;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChargeController extends Controller
{
    use HttpResponseTrait;

    public function index(): JsonResponse
    {
        $charges = Charge::with('glAccount')->get();
        return ResponseUtils::success(
            ChargeResource::collection($charges),
            'Charges retrieved successfully'
        );
    }

    public function store(ChargeStoreRequest $request): JsonResponse
    {

        $charge = Charge::create($request->validated());
        return $this->successResponse(
            new ChargeResource($charge),
            'Charge created successfully',
            Response::HTTP_CREATED
        );
    }

    public function show(Charge $charge): JsonResponse
    {
        return ResponseUtils::success(
            new ChargeResource($charge->load('glAccount')),
            'Charge retrieved successfully'
        );
    }

    public function update(ChargeUpdateRequest $request, Charge $charge): JsonResponse
    {
        $charge->update($request->validated());
        return ResponseUtils::success(
            new ChargeResource($charge),
            'Charge updated successfully'
        );
    }

    public function deactivate(Charge $charge): JsonResponse
    {
        $charge->update(['is_active' => false]);
        return ResponseUtils::success(
            new ChargeResource($charge),
            'Charge deactivated successfully'
        );
    }

  public function applyCharge(Request $request, Account $account): JsonResponse
  {
    // Validate request
    $validated = $request->validate([
      'charge_id' => 'required|uuid|exists:charges,id',
      'charge_config' => 'nullable|array',
      'effective_from' => 'nullable|date',
      'effective_until' => 'nullable|date|after:effective_from',
      'notes' => 'nullable|string|max:1000',
    ]);

    // Get the charge
    $charge = Charge::findOrFail($validated['charge_id']);

    // Check if charge is active
    if (!$charge->is_active) {
      return ResponseUtils::error(
        'Cannot assign inactive charge',
        Response::HTTP_UNPROCESSABLE_ENTITY
      );
    }

    // Check if account is active
    if (!$account->isActive()) {
      return ResponseUtils::error(
        'Cannot assign charge to inactive account',
        Response::HTTP_UNPROCESSABLE_ENTITY
      );
    }

    DB::beginTransaction();

    try {
      // Create account charge record
      $accountChargeId = (string) Str::uuid();
      DB::table('account_charges')->insert([
        'id' => $accountChargeId,
        'account_id' => $account->id,
        'charge_id' => $charge->id,
        'is_active' => true,
        'charge_config' => json_encode($validated['charge_config'] ?? null),
        'effective_from' => $validated['effective_from'] ?? now(),
        'effective_until' => $validated['effective_until'] ?? null,
        'created_by_user_id' => auth()->id(),
        'notes' => $validated['notes'] ?? null,
        'created_at' => now(),
        'updated_at' => now(),
      ]);

      // Process the transaction
      $transaction = Transaction::createCharge([
        'account_id' => $account->id,
        'charge_id' => $charge->id,
        'amount' => $charge->calculateAmount($account),
        'metadata' => ['charge_config' => $validated['charge_config'] ?? null]
      ]);

      $transaction->processAndSave();

      DB::commit();

      return ResponseUtils::success(
        [
          'account_charge_id' => $accountChargeId,
          'transaction' => $transaction
        ],
        'Charge applied successfully'
      );

    } catch (InsufficientFundsException $e) {
      DB::rollBack();
      return ResponseUtils::error($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
    } catch (\Exception $e) {
      DB::rollBack();
      Log::error('Failed to apply charge: ' . $e->getMessage());
      return ResponseUtils::error(
        'Failed to apply charge: ' . $e->getMessage(),
        Response::HTTP_INTERNAL_SERVER_ERROR
      );
    }
  }
}