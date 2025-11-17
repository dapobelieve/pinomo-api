<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $this->extractApiKey($request);
        
        if (!$apiKey) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'API key is required',
                'code' => 401
            ], 401);
        }
        
        $apiKeyRecord = $this->validateApiKey($apiKey);
        
        if (!$apiKeyRecord) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid or expired API key',
                'code' => 401
            ], 401);
        }
        
        // Update last used timestamp
        $this->updateLastUsed($apiKeyRecord->id);
        
        // Add API key info to request for logging
        $request->merge([
            'api_key_name' => $apiKeyRecord->name,
            'api_key_service' => $apiKeyRecord->service_name,
            'api_key_permissions' => json_decode($apiKeyRecord->permissions, true)
        ]);
        
        // Log API request
        Log::info('API request authenticated', [
            'api_key_name' => $apiKeyRecord->name,
            'service_name' => $apiKeyRecord->service_name,
            'endpoint' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);
        
        return $next($request);
    }
    
    /**
     * Extract API key from request headers
     */
    private function extractApiKey(Request $request): ?string
    {
        // Check X-API-Key header first
        $apiKey = $request->header('X-API-Key');
        
        if (!$apiKey) {
            // Check Authorization header for Bearer token
            $authHeader = $request->header('Authorization');
            if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
                $apiKey = substr($authHeader, 7);
            }
        }
        
        return $apiKey;
    }
    
    /**
     * Validate API key against database
     */
    private function validateApiKey(string $apiKey): ?object
    {
        return DB::table('api_keys')
            ->where('key_hash', hash('sha256', $apiKey))
            ->where('is_active', true)
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->first();
    }
    
    /**
     * Update last used timestamp
     */
    private function updateLastUsed(int $apiKeyId): void
    {
        DB::table('api_keys')
            ->where('id', $apiKeyId)
            ->update(['last_used_at' => now()]);
    }
}
