<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Models\Client;
use App\Utils\ResponseUtils;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Client::query();

            if ($request->has('client_type')) {
                $query->where('client_type', $request->client_type);
            }

            // Filter by owner (for organization clients)
            if ($request->has('owner_client_id')) {
                $query->where('owner_client_id', $request->owner_client_id);
            }

            if ($request->has('kyc_status')) {
                $query->where('kyc_status', $request->kyc_status);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                        ->orWhere('business_name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }

            // Load relationships
            $query->with(['owner', 'ownedBusinesses']);

            $clients = $query->paginate($request->get('per_page', 15));

            return ResponseUtils::success(
                $clients,
                'Clients retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to fetch clients: ' . $e->getMessage());
            return ResponseUtils::error(
                'Failed to fetch clients',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function store(StoreClientRequest $request)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validated();

            $validatedData['external_id'] = $validatedData['external_id'] ?? Str::uuid();

            // Handle business relationship logic
            if ($validatedData['client_type'] === 'organization') {
                // Ensure organization has an owner
                if (empty($validatedData['owner_client_id'])) {
                    throw new \InvalidArgumentException('Organization clients must have an owner');
                }

                // Verify owner exists and is individual
                $owner = Client::find($validatedData['owner_client_id']);
                if (!$owner || !$owner->isIndividualClient()) {
                    throw new \InvalidArgumentException('Owner must be a valid individual client');
                }

                // Clear individual-specific fields for organizations
                $validatedData = array_merge($validatedData, [
                    'first_name' => null,
                    'last_name' => null,
                    'date_of_birth' => null,
                    'gender' => null,
                    'marital_status' => null,
                    'nationality' => null,
                    'organization_business_ids' => null
                ]);
            } else {
                // Individual client - clear business fields and owner
                $validatedData = array_merge($validatedData, [
                    'owner_client_id' => null,
                    'business_name' => null,
                    'business_registration_number' => null,
                    'business_registration_date' => null,
                    'business_type' => null,
                    'tax_identification_number' => null,
                    'industry_sector' => null,
                    'representative_first_name' => null,
                    'representative_last_name' => null,
                    'representative_position' => null
                ]);
            }

            $client = Client::create($validatedData);

            // If creating an organization, update the owner's business relationships
            if ($client->isBusinessClient()) {
                $owner = $client->owner;
                $owner->addBusinessRelationship($client->id);
            }

            Log::info('Client created successfully', [
                'client_id' => $client->id,
                'external_id' => $client->external_id,
                'client_type' => $client->client_type,
                'owner_client_id' => $client->owner_client_id
            ]);

            DB::commit();

            // Load relationships for response
            $client->load(['owner', 'ownedBusinesses']);

            return ResponseUtils::success(
                [
                    'id' => $client->id,
                    'external_id' => $client->external_id,
                    'client_type' => $client->client_type,
                    'owner_client_id' => $client->owner_client_id,
                    'owner' => $client->owner ? [
                        'id' => $client->owner->id,
                        'name' => $client->owner->getDisplayName(),
                        'email' => $client->owner->email
                    ] : null,
                    'display_name' => $client->getDisplayName(),
                    'email' => $client->email,
                    'phone_number' => $client->phone_number,
                    'kyc_status' => $client->kyc_status,
                    'created_at' => $client->created_at
                ],
                'Client created successfully',
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create client', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return ResponseUtils::error(
                'Failed to create client: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function show(Client $client)
    {
        try {
            $client->load([
                'kycDocuments',
                'kycStatusHistory',
                'owner',
                'ownedBusinesses'
            ]);

            return ResponseUtils::success(
                $client,
                'Client retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve client: ' . $e->getMessage());
            return ResponseUtils::error(
                'Failed to retrieve client',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function update(StoreClientRequest $request, Client $client)
    {
        try {
            DB::beginTransaction();


            $validatedData = $request->validated();

            // Prevent changing client type after creation
            if (isset($validatedData['client_type']) && $validatedData['client_type'] !== $client->client_type) {
                throw new \InvalidArgumentException('Client type cannot be changed after creation');
            }




            // Handle ownership changes for organization clients
            if ($client->isBusinessClient() && isset($validatedData['owner_client_id'])) {
                $oldOwner = $client->owner;
                $newOwnerId = $validatedData['owner_client_id'];

                // Verify new owner exists and is individual
                $newOwner = Client::find($newOwnerId);
                if (!$newOwner || !$newOwner->isIndividualClient()) {
                    throw new \InvalidArgumentException('Owner must be a valid individual client');
                }

                // Update relationships if owner changed
                if ($oldOwner && $oldOwner->id !== $newOwnerId) {
                    $oldOwner->removeBusinessRelationship($client->id);
                    $newOwner->addBusinessRelationship($client->id);
                }
            }

            $client->update($validatedData);

            Log::info('Client updated successfully', [
                'client_id' => $client->id,
                'client_type' => $client->client_type,
                'owner_client_id' => $client->owner_client_id
            ]);
            dd($client);

            DB::commit();

            $client->load(['owner', 'ownedBusinesses']);

            return ResponseUtils::success(
                $client,
                'Client updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update client', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return ResponseUtils::error(
                'Failed to update client: ' . $e->getMessage(),
                ResponseAlias::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function destroy(Client $client)
    {
        try {
            DB::beginTransaction();

            // Check if client can be deleted
            if (!$client->canBeDeleted()) {
                return ResponseUtils::error(
                    'Cannot delete client. Individual clients who own businesses must transfer or close their businesses first.',
                    Response::HTTP_CONFLICT
                );
            }

            // If deleting organization, remove from owner's business relationships
            if ($client->isBusinessClient() && $client->owner) {
                $client->owner->removeBusinessRelationship($client->id);
            }

            $client->delete();

            Log::info('Client deleted successfully', [
                'client_id' => $client->id,
                'client_type' => $client->client_type
            ]);

            DB::commit();

            return ResponseUtils::success(
                null,
                'Client deleted successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete client', [
                'client_id' => $client->id,
                'error' => $e->getMessage()
            ]);

            return ResponseUtils::error(
                'Failed to delete client: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function getOwnedBusinesses(Client $client)
    {
        try {
            if (!$client->isIndividualClient()) {
                return ResponseUtils::error(
                    'Only individual clients can own businesses',
                    Response::HTTP_BAD_REQUEST
                );
            }

            $businesses = $client->ownedBusinesses()
                ->select([
                    'id', 'external_id', 'business_name', 'business_type',
                    'business_registration_number', 'kyc_status', 'created_at'
                ])
                ->get();

            return ResponseUtils::success(
                $businesses,
                'Owned businesses retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve owned businesses', [
                'client_id' => $client->id,
                'error' => $e->getMessage()
            ]);

            return ResponseUtils::error(
                'Failed to retrieve owned businesses',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
