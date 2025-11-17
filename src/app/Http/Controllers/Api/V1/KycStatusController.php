<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientKycStatusHistory;
use App\Utils\ResponseUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KycStatusController extends Controller
{
    public function update(Request $request, Client $client)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:pending,verified,rejected',
                'notes' => 'required|string'
            ]);

            $oldStatus = $client->kyc_status;
            
            // Create status history record
            ClientKycStatusHistory::create([
                'client_id' => $client->id,
                'old_status' => $oldStatus,
                'new_status' => $validated['status'],
                'action_by_user_id' => Auth::id(),
                'notes' => $validated['notes'],
                'changed_at' => now()
            ]);

            $client->update([
                'kyc_status' => $validated['status']
            ]);

            return ResponseUtils::success($client->load('kycStatusHistory'), 'KYC status updated successfully');
        } catch (\Exception $e) {
            return ResponseUtils::error('Failed to update KYC status', 500);
        }
    }

    public function history(Client $client)
    {
        try {
            $history = $client->kycStatusHistory()
                               ->with('actionBy')
                               ->orderBy('changed_at', 'desc')
                               ->get();
            return ResponseUtils::success($history, 'KYC status history retrieved successfully');
        } catch (\Exception $e) {
            return ResponseUtils::error('Failed to retrieve KYC status history', 500);
        }
    }
}