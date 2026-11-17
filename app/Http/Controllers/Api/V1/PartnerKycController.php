<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PartnerKycDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PartnerKycController extends Controller
{
    /**
     * Display a listing of partner's KYC documents.
     */
    public function index(Request $request)
    {
        try {
            $partnerId = Auth::id();

            $documents = PartnerKycDocument::forPartner($partnerId)
                ->with('verifiedBy:user_id,first_name,last_name')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'document_type' => $doc->document_type,
                        'document_name' => $doc->document_name,
                        'document_type_name' => $doc->document_type_name,
                        'file_size' => $doc->file_size,
                        'file_size_formatted' => $doc->file_size_formatted,
                        'file_type' => $doc->file_type,
                        'status' => $doc->status,
                        'uploaded_at' => $doc->uploaded_at,
                        'expires_at' => $doc->expires_at,
                        'rejection_reason' => $doc->rejection_reason,
                        'verified_by' => $doc->verifiedBy ? [
                            'name' => $doc->verifiedBy->first_name . ' ' . $doc->verifiedBy->last_name
                        ] : null,
                        'verified_at' => $doc->verified_at,
                        'is_required' => $doc->is_required,
                        'is_expired' => $doc->isExpired(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $documents
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created KYC document.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'document_type' => 'required|in:license,insurance,identity,address_proof,bank_statement,tax_certificate',
                'document_name' => 'required|string|max:255',
                'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max
                'expires_at' => 'nullable|date|after:today',
                'is_required' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $partnerId = Auth::id();
            $file = $request->file('file');

            // Check if document type already exists for this partner
            $existingDoc = PartnerKycDocument::forPartner($partnerId)
                ->where('document_type', $request->document_type)
                ->first();

            if ($existingDoc) {
                // Delete old file if exists
                if (Storage::disk('private')->exists($existingDoc->file_path)) {
                    Storage::disk('private')->delete($existingDoc->file_path);
                }
                $existingDoc->delete();
            }

            // Generate secure file path
            $extension = $file->getClientOriginalExtension();
            $hashedName = Str::random(40) . '.' . $extension;
            $filePath = "kyc/{$partnerId}/{$request->document_type}/{$hashedName}";

            // Store file securely
            $file->storeAs('', $filePath, 'private');

            // Create document record
            $document = PartnerKycDocument::create([
                'partner_id' => $partnerId,
                'document_type' => $request->document_type,
                'document_name' => $request->document_name,
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'file_type' => $extension,
                'uploaded_at' => now(),
                'expires_at' => $request->expires_at,
                'is_required' => $request->is_required ?? true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'data' => [
                    'id' => $document->id,
                    'document_type' => $document->document_type,
                    'document_name' => $document->document_name,
                    'status' => $document->status,
                    'uploaded_at' => $document->uploaded_at
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified KYC document (secure download).
     */
    public function show(string $id)
    {
        try {
            $partnerId = Auth::id();
            $user = Auth::user();

            $document = PartnerKycDocument::findOrFail($id);

            // Security check: Only partner owner or super-admin can access
            if ($document->partner_id !== $partnerId && $user->user_type !== 'super-admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to document'
                ], 403);
            }

            if (!Storage::disk('private')->exists($document->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Document file not found'
                ], 404);
            }

            $fileContent = Storage::disk('private')->get($document->file_path);

            // Determine MIME type based on file extension
            $mimeTypes = [
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png'
            ];
            $mimeType = $mimeTypes[$document->file_type] ?? 'application/octet-stream';

            return response($fileContent)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'inline; filename="' . $document->document_name . '"');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // This method can be used to update document metadata (not the file itself)
        // For file updates, use the store method which replaces existing documents
        return response()->json([
            'success' => false,
            'message' => 'Document updates not supported. Please upload a new document.'
        ], 405);
    }

    /**
     * Remove the specified KYC document.
     */
    public function destroy(string $id)
    {
        try {
            $partnerId = Auth::id();

            $document = PartnerKycDocument::forPartner($partnerId)->findOrFail($id);

            // Delete physical file
            if (Storage::disk('private')->exists($document->file_path)) {
                Storage::disk('private')->delete($document->file_path);
            }

            // Delete database record
            $document->delete();

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get required document types for KYC completion.
     */
    public function getRequiredDocuments()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'required_documents' => [
                    'license' => [
                        'name' => 'Professional License',
                        'description' => 'Valid professional license for your service category',
                        'required' => true,
                        'max_size' => '5MB',
                        'formats' => ['PDF', 'JPG', 'PNG']
                    ],
                    'identity' => [
                        'name' => 'National ID/Passport',
                        'description' => 'Government issued photo identification',
                        'required' => true,
                        'max_size' => '5MB',
                        'formats' => ['PDF', 'JPG', 'PNG']
                    ],
                    'address_proof' => [
                        'name' => 'Address Proof',
                        'description' => 'Utility bill or official document showing your address',
                        'required' => true,
                        'max_size' => '5MB',
                        'formats' => ['PDF', 'JPG', 'PNG']
                    ],
                    'insurance' => [
                        'name' => 'Insurance Certificate',
                        'description' => 'Professional liability insurance (if applicable)',
                        'required' => false,
                        'max_size' => '5MB',
                        'formats' => ['PDF', 'JPG', 'PNG']
                    ]
                ]
            ]
        ]);
    }
}
