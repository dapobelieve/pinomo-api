<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GLAccountStoreRequest;
use App\Http\Requests\GLAccountUpdateRequest;
use App\Http\Resources\GLAccountResource;
use App\Models\GLAccount;
use App\Traits\HttpResponseTrait;
use App\Utils\ResponseUtils;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class GLAccountController extends Controller
{
    use HttpResponseTrait;

    public function index(Request $request)
    {
        try {
            $query = GLAccount::query();
            
            // Apply filters
            if ($request->has('account_type')) {
                $query->where('account_type', $request->account_type);
            }
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }
            
            // Get accounts with optional relationships
            $accounts = $query->with(['parent', 'children'])
                            ->orderBy('account_code')
                            ->paginate();
            
            return $this->successResponse(
                GLAccountResource::collection($accounts),
                'GL accounts retrieved successfully',
            );
        } catch (\Exception $e) {
            return ResponseUtils::error(
                'Failed to retrieve GL accounts',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $e
            );
        }
    }

    public function store(GLAccountStoreRequest $request)
    {
        try {
            $account = GLAccount::create($request->validated());
            
            return ResponseUtils::success(
                'GL account created successfully',
                new GLAccountResource($account),
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return ResponseUtils::error(
                'Failed to create GL account',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $e
            );
        }
    }

    public function show(GLAccount $account)
    {
        try {
            $account->load(['parent', 'children']);
            
            return ResponseUtils::success(
                'GL account retrieved successfully',
                new GLAccountResource($account)
            );
        } catch (\Exception $e) {
            return ResponseUtils::error(
                'Failed to retrieve GL account',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $e
            );
        }
    }

    public function update(GLAccountUpdateRequest $request, GLAccount $account)
    {
        try {
            $account->update($request->validated());
            
            return ResponseUtils::success(
                'GL account updated successfully',
                new GLAccountResource($account)
            );
        } catch (\Exception $e) {
            return ResponseUtils::error(
                'Failed to update GL account',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $e
            );
        }
    }

    public function destroy(GLAccount $account)
    {
        try {
            if ($account->children()->exists()) {
                return ResponseUtils::error(
                    'Cannot delete account with child accounts',
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            if ($account->current_balance != 0) {
                return ResponseUtils::error(
                    'Cannot delete account with non-zero balance',
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $account->delete();
            
            return ResponseUtils::success(
                'GL account deleted successfully'
            );
        } catch (\Exception $e) {
            return ResponseUtils::error(
                'Failed to delete GL account',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $e
            );
        }
    }
}