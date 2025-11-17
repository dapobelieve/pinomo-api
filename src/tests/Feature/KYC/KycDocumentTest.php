<?php

namespace Tests\Feature\KYC;

use Tests\TestCase;
use App\Models\Client;
use App\Models\ClientKycDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

class KycDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();
        $this->client = Client::factory()->create();
        Storage::fake('kyc');
    }

    public function test_can_upload_kyc_document()
    {
        $file = UploadedFile::fake()->image('document.jpg');

        $response = $this->postJson("/api/v1/clients/{$this->client->id}/kyc-documents", [
            'document_type' => 'identity',
            'file' => $file
        ]);

        $response->assertStatus(201);
        Storage::disk('kyc')->assertExists($response->json('data.file_path'));
    }

    public function test_can_review_kyc_document()
    {
        $document = ClientKycDocument::factory()->create([
            'client_id' => $this->client->id
        ]);

        $response = $this->postJson("/api/v1/clients/{$this->client->id}/kyc-documents/{$document->id}/review", [
            'status' => 'approved',
            'comments' => 'Document verified'
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'approved']);
    }

    public function test_can_download_kyc_document()
    {
        $document = ClientKycDocument::factory()->create([
            'client_id' => $this->client->id
        ]);

        $response = $this->getJson("/api/v1/clients/{$this->client->id}/kyc-documents/{$document->id}/download");

        $response->assertStatus(200)
            ->assertHeader('Content-Type');
    }
}