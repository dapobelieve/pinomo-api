<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class WebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    protected string $webhookUrl;
    protected array $payload;
    protected string $webhookId;

    public function __construct(string $webhookUrl, array $payload)
    {
        $this->webhookUrl = $webhookUrl;
        $this->payload = $payload;
        $this->webhookId = Str::uuid()->toString();
        
        $this->payload['webhook_id'] = $this->webhookId;
        $this->payload['timestamp'] = now()->toISOString();
    }

    public function handle(): void
    {
        Log::info('Processing webhook delivery', [
            'webhook_id' => $this->webhookId,
            'url' => $this->webhookUrl,
            'event' => $this->payload['event'] ?? 'unknown'
        ]);

        // Create or update webhook event record (idempotent)
        $isFirstAttempt = $this->attempts() === 1;
        
        $webhookEvent = WebhookEvent::updateOrCreate(
            ['webhook_id' => $this->webhookId],
            [
                'url' => $this->webhookUrl,
                'payload' => $this->payload,
                'status' => $isFirstAttempt ? 'pending' : 'retrying',
                'attempt' => $this->attempts(),
                'scheduled_at' => $isFirstAttempt ? now() : null
            ]
        );

        try {
            $signature = $this->generateSignature($this->payload);
            
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-ID' => $this->webhookId,
                    'X-Webhook-Timestamp' => $this->payload['timestamp'],
                    'User-Agent' => 'Bankman-Webhook/1.0'
                ])
                ->post($this->webhookUrl, $this->payload);

            if ($response->successful()) {
                $webhookEvent->update([
                    'status' => 'delivered',
                    'response_status' => $response->status(),
                    'response_body' => $response->body(),
                    'delivered_at' => now()
                ]);

                Log::info('Webhook delivered successfully', [
                    'webhook_id' => $this->webhookId,
                    'url' => $this->webhookUrl,
                    'status' => $response->status()
                ]);
            } else {
                throw new \Exception("HTTP {$response->status()}: {$response->body()}");
            }

        } catch (\Exception $e) {
            $webhookEvent->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'failed_at' => now()
            ]);

            Log::warning('Webhook delivery failed', [
                'webhook_id' => $this->webhookId,
                'url' => $this->webhookUrl,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Webhook delivery permanently failed', [
            'webhook_id' => $this->webhookId,
            'url' => $this->webhookUrl,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage()
        ]);

        WebhookEvent::where('webhook_id', $this->webhookId)
            ->update([
                'status' => 'permanently_failed',
                'error_message' => $exception->getMessage(),
                'failed_at' => now()
            ]);

    }

    protected function generateSignature(array $payload): string
    {
        $secret = config('app.webhook_secret', config('app.key'));
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
        
        return 'sha256=' . hash_hmac('sha256', $jsonPayload, $secret);
    }

    public function uniqueId(): string
    {
        return $this->webhookId;
    }
}