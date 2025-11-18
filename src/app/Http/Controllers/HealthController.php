<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Ramp\Logger\Facades\Log;

class HealthController extends Controller
{
    /**
     * Health check endpoint
     */
    public function health(): JsonResponse
    {
        Log::info('Health check requested', [
            'service' => 'bankman',
            'endpoint' => '/health'
        ]);

        return response()->json([
            'status' => 'ok',
            'service' => 'bankman',
            'timestamp' => now()->toISOString()
        ]);
    }
}
