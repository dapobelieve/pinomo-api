<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WebhookDemoController extends Controller
{
    public function receive(Request $request): JsonResponse
    {
        // Log all webhook data for debugging
        Log::info('Webhook received', [
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'timestamp' => now()->toISOString()
        ]);

        // Store webhook data in a file for easy inspection
        $webhookData = [
            'received_at' => now()->toISOString(),
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
            'method' => $request->method(),
            'signature' => $request->header('X-Webhook-Signature'),
            'webhook_id' => $request->header('X-Webhook-ID'),
            'webhook_timestamp' => $request->header('X-Webhook-Timestamp'),
        ];

        // Append to webhooks log file
        $logFile = 'webhooks/demo_' . now()->format('Y-m-d') . '.json';
        $existingData = Storage::exists($logFile) ? json_decode(Storage::get($logFile), true) : [];
        $existingData[] = $webhookData;
        Storage::put($logFile, json_encode($existingData, JSON_PRETTY_PRINT));

        // Verify signature (optional - for production use)
        $this->verifySignature($request);

        // Process different event types
        $payload = $request->all();
        $eventType = $payload['event'] ?? 'unknown';

        switch ($eventType) {
            case 'lien_release_completed':
                $this->handleLienReleaseCompleted($payload);
                break;
            case 'release_and_withdraw_completed':
                $this->handleReleaseAndWithdrawCompleted($payload);
                break;
            case 'transaction_job_failed':
                $this->handleJobFailed($payload);
                break;
            default:
                Log::info('Unknown webhook event type', ['event' => $eventType]);
        }

        // Return success response
        return response()->json([
            'status' => 'success',
            'message' => 'Webhook received and processed',
            'event_type' => $eventType,
            'webhook_id' => $request->header('X-Webhook-ID'),
            'processed_at' => now()->toISOString()
        ], 200);
    }

    private function verifySignature(Request $request): bool
    {
        $signature = $request->header('X-Webhook-Signature');
        if (!$signature) {
            Log::warning('Webhook received without signature');
            return false;
        }

        $secret = config('app.webhook_secret', config('app.key'));
        $payload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('Webhook signature verification failed', [
                'expected' => $expectedSignature,
                'received' => $signature
            ]);
            return false;
        }

        Log::info('Webhook signature verified successfully');
        return true;
    }

    private function handleLienReleaseCompleted(array $payload): void
    {
        Log::info('Processing lien release completed webhook', [
            'job_id' => $payload['job_id'] ?? null,
            'transaction_id' => $payload['transaction_id'] ?? null,
            'release_transaction_id' => $payload['release_transaction_id'] ?? null
        ]);

        // Add your business logic here
        // For example: update external system, send notifications, etc.
    }

    private function handleReleaseAndWithdrawCompleted(array $payload): void
    {
        Log::info('Processing release and withdraw completed webhook', [
            'job_id' => $payload['job_id'] ?? null,
            'transaction_id' => $payload['transaction_id'] ?? null,
            'data' => $payload['data'] ?? null
        ]);

        // Add your business logic here
        // For example: update external system, send notifications, etc.
    }

    private function handleJobFailed(array $payload): void
    {
        Log::error('Processing job failed webhook', [
            'job_id' => $payload['job_id'] ?? null,
            'job_type' => $payload['job_type'] ?? null,
            'transaction_id' => $payload['transaction_id'] ?? null,
            'error' => $payload['error'] ?? null
        ]);

        // Add your error handling logic here
        // For example: alert monitoring system, retry logic, etc.
    }

    public function logs(): JsonResponse
    {
        try {
            $date = request('date', now()->format('Y-m-d'));
            $logFile = 'webhooks/demo_' . $date . '.json';
            
            if (!Storage::exists($logFile)) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'No webhook logs found for ' . $date,
                    'data' => []
                ]);
            }

            $logs = json_decode(Storage::get($logFile), true);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Webhook logs retrieved',
                'date' => $date,
                'count' => count($logs),
                'data' => $logs
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve webhook logs: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve webhook logs'
            ], 500);
        }
    }
}