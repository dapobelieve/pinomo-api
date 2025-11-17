<?php

namespace App\Jobs;

use App\Models\ClientKycDocument;
use App\Models\KycStorageConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessKycDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    public function __construct(
        protected ClientKycDocument $document,
        protected string $tempPath
    ) {}

    public function handle(): void
    {
        try {
            // Validate temporary file
            if (!file_exists($this->tempPath)) {
                throw new \RuntimeException('Temporary file not found');
            }

            // Get active storage configuration
            $activeConfig = KycStorageConfig::where('is_active', true)->first();
            if (!$activeConfig) {
                throw new \RuntimeException('No active storage configuration found');
            }

            // Prepare storage path
            $path = $activeConfig->settings['path'] . '/' . $this->document->client_id . '/' . $this->document->id;
            $disk = Storage::disk($activeConfig->settings['disk']);

            // Upload file
            $disk->putFileAs($path, $this->tempPath, $this->document->file_name);

            // Verify upload
            if (!$disk->exists($path . '/' . $this->document->file_name)) {
                throw new \RuntimeException('File upload verification failed');
            }

            // Update document status
            $this->document->update([
                'status' => 'pending_review',
                'file_path' => $path . '/' . $this->document->file_name,
                'storage_config_id' => $activeConfig->id,
                'storage_disk' => $activeConfig->settings['disk'],
                'uploaded_at' => now()
            ]);

            // Cleanup temporary file
            if (file_exists($this->tempPath)) {
                unlink($this->tempPath);
            }

            Log::info('KYC document processed successfully', [
                'document_id' => $this->document->id,
                'client_id' => $this->document->client_id,
                'storage_path' => $path . '/' . $this->document->file_name
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process KYC document', [
                'document_id' => $this->document->id,
                'client_id' => $this->document->client_id,
                'error' => $e->getMessage()
            ]);

            $this->document->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('KYC document processing job failed', [
            'document_id' => $this->document->id,
            'client_id' => $this->document->client_id,
            'error' => $exception->getMessage()
        ]);

        // Cleanup temporary file if it still exists
        if (file_exists($this->tempPath)) {
            unlink($this->tempPath);
        }

        $this->document->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage()
        ]);
    }
}