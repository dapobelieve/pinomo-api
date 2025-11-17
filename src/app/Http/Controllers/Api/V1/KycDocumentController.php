<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClientKycDocument;
use App\Models\Client;
use App\Utils\ResponseUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class KycDocumentController extends Controller
{
    public function store(Request $request, Client $client)
    {
        try {
            $request->validate([
                'file' => 'required|file|max:10240', // 10MB max
                'type' => 'required|string',
                'issue_date' => 'required|date',
                'expiry_date' => 'required|date|after:issue_date',
                'issuing_authority' => 'required|string',
            ]);

            $file = $request->file('file');
            $document = $client->kycDocuments()->create([
                'type' => $request->type,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'issue_date' => $request->issue_date,
                'expiry_date' => $request->expiry_date,
                'issuing_authority' => $request->issuing_authority,
                'status' => 'processing',
            ]);

            ProcessKycDocument::dispatch($document, $file->path())
                ->onQueue(config('kyc.queue.queue'));

            return ResponseUtils::success($document, 'KYC document uploaded successfully', 201);
        } catch (\Exception $e) {
            return ResponseUtils::error('Failed to upload KYC document', 500);
        }
    }

    public function review(Request $request, Client $client, ClientKycDocument $document)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:approved,rejected',
                'notes' => 'required|string'
            ]);

            $document->update([
                'status' => $validated['status'],
                'notes' => $validated['notes'],
                'reviewed_by_user_id' => Auth::id(),
                'review_date' => now()
            ]);

            return ResponseUtils::success($document, 'KYC document review completed successfully');
        } catch (\Exception $e) {
            return ResponseUtils::error('Failed to review KYC document', 500);
        }
    }

    public function index(Client $client)
    {
        try {
            $documents = $client->kycDocuments()->with(['uploadedBy', 'reviewedBy'])->get();
            return ResponseUtils::success($documents, 'KYC documents retrieved successfully');
        } catch (\Exception $e) {
            return ResponseUtils::error('Failed to retrieve KYC documents', 500);
        }
    }

    public function show(Client $client, ClientKycDocument $document)
    {
        try {
            return ResponseUtils::success(
                $document->load(['uploadedBy', 'reviewedBy']),
                'KYC document retrieved successfully'
            );
        } catch (\Exception $e) {
            return ResponseUtils::error('Failed to retrieve KYC document', 500);
        }
    }

    public function download(Client $client, ClientKycDocument $document)
    {
        try {
            return Storage::disk('private')->download($document->file_path);
        } catch (\Exception $e) {
            return ResponseUtils::error('Failed to download KYC document', 500);
        }
    }
}