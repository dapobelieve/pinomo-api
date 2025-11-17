<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Ramp\Logger\Facades\RampLogger;

class HealthController extends Controller
{
    /**
     * Health check endpoint
     */
    public function health(): JsonResponse
    {
        RampLogger::info('Health check requested', [
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
