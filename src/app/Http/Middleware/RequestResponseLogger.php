<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RequestResponseLogger
{
    public function handle(Request $request, Closure $next)
    {
        // Check if request logging is enabled
        if (!config('app.enable_request_logging', true)) {
            return $next($request);
        }

        // Debug: Log that middleware is being executed
        error_log("REQUEST_LOGGER: Middleware is executing for " . $request->getMethod() . " " . $request->getRequestUri());

        // Generate unique request ID
        $requestId = Str::uuid()->toString();
        $request->headers->set('X-Request-ID', $requestId);

        $startTime = microtime(true);

        // Log incoming request
        $this->logRequest($request, $requestId);

        $response = $next($request);

        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);

        // Log outgoing response
        $this->logResponse($request, $response, $requestId, $executionTime);

        // Add request ID to response headers
        if (method_exists($response, 'headers')) {
            $response->headers->set('X-Request-ID', $requestId);
        }

        return $response;
    }

    private function logRequest(Request $request, string $requestId): void
    {
        $logData = [
            'type' => 'REQUEST',
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'url' => $request->fullUrl(),
            'uri' => $request->getRequestUri(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $this->filterHeaders($request->headers->all()),
            'query_params' => $request->query->all(),
            'body' => $this->shouldLogRequestBody() ? $this->getRequestBody($request) : null,
            'timestamp' => now()->toISOString(),
        ];

        // Try both stdout and default log channels
        Log::channel('stdout')->info('Incoming Request', $logData);
        \Log::info('REQUEST_LOGGER: Incoming Request', $logData);
    }

    private function logResponse(Request $request, $response, string $requestId, float $executionTime): void
    {
        $statusCode = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;
        $headers = method_exists($response, 'headers') ? $response->headers->all() : [];

        $logData = [
            'type' => 'RESPONSE',
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'url' => $request->fullUrl(),
            'status_code' => $statusCode,
            'headers' => $this->filterHeaders($headers),
            'body' => $this->shouldLogResponseBody() ? $this->getResponseBody($response) : null,
            'execution_time_ms' => $executionTime,
            'timestamp' => now()->toISOString(),
        ];

        $logLevel = $statusCode >= 400 ? 'error' : 'info';

        // Try both stdout and default log channels
        Log::channel('stdout')->{$logLevel}('Outgoing Response', $logData);
        \Log::{$logLevel}('REQUEST_LOGGER: Outgoing Response', $logData);
    }

    private function filterHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'cookie', 'set-cookie', 'x-api-key', 'x-auth-token'];

        return array_filter($headers, function ($key) use ($sensitiveHeaders) {
            return !in_array(strtolower($key), $sensitiveHeaders);
        }, ARRAY_FILTER_USE_KEY);
    }

    private function getRequestBody(Request $request): array|string|null
    {
        $content = $request->getContent();

        if (empty($content)) {
            return null;
        }

        // Try to decode JSON
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $this->filterSensitiveData($decoded);
        }

        // For form data
        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'])) {
            return $this->filterSensitiveData($request->all());
        }

        // Limit body size for other content types
        return strlen($content) > 1000 ? substr($content, 0, 1000) . '... [truncated]' : $content;
    }

    private function getResponseBody($response): string|null
    {
        if (!method_exists($response, 'getContent')) {
            return null;
        }

        $content = $response->getContent();

        // Limit response body size in logs
        if (strlen($content) > 2000) {
            return substr($content, 0, 2000) . '... [truncated]';
        }

        return $content;
    }

    private function filterSensitiveData(array $data): array
    {
        $sensitiveFields = ['password', 'token', 'secret', 'key', 'api_key', 'credit_card', 'cvv', 'pin'];

        array_walk_recursive($data, function (&$value, $key) use ($sensitiveFields) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                $value = '***FILTERED***';
            }
        });

        return $data;
    }

    private function shouldLogRequestBody(): bool
    {
        return config('app.log_request_body', true);
    }

    private function shouldLogResponseBody(): bool
    {
        return config('app.log_response_body', true);
    }
}
