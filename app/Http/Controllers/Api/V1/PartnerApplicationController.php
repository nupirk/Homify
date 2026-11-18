<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PartnerApplication;
use App\Models\PartnerProfile;
use App\Models\PartnerKycDocument;
use App\Models\Service;
use App\Support\Redactor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\MessageBag;

class PartnerApplicationController extends Controller
{
    /**
     * Check if any document files were uploaded in the request
     */
    private function checkForDocumentFiles(Request $request): bool
    {
        if (!$request->input('documents')) {
            return false;
        }

        foreach ($request->input('documents', []) as $index => $docInfo) {
            $fileKey = "documents.{$index}.file";
            if ($request->hasFile($fileKey)) {
                return true;
            }
        }

        return false;
    }
    /**
     * Build the 422 response for a failed validation.
     *
     * Answering every failure with a blanket "Validation failed" left the
     * caller (and the mobile form) with nothing to act on. The headline
     * message now carries the actual problems; `errors` keeps the full
     * field => messages map for clients that render them per field.
     */
    private function validationErrorResponse(MessageBag $errors): JsonResponse
    {
        $messages = $errors->all();

        return response()->json([
            'success' => false,
            'message' => $messages === [] ? 'Validation failed' : implode(' ', $messages),
            'errors' => $errors,
        ], 422);
    }

    /**
     * Submit partner application with KYC documents
     */
    public function submitApplication(Request $request)
    {
        // Diagnostics only: the old line dumped the whole body (including the
        // applicant's email, phone and address) into the log. What matters for
        // debugging a failed submit is the transport size and the document
        // shape - not the applicant's personal details.
        $base64Documents = $request->input('documents_base64');

        Log::info('Partner application submission started', [
            'email' => $request->filled('email') ? Redactor::email((string) $request->input('email')) : null,
            'documents' => $this->checkForDocumentFiles($request)
                ? 'files'
                : (is_array($base64Documents) ? 'base64' : 'none'),
            'document_count' => is_array($base64Documents) ? count($base64Documents) : 0,
            'content_length' => (int) $request->header('Content-Length'),
        ]);

        try {
            // Decode JSON strings for array fields
            $requestData = $request->all();
            if (isset($requestData['availability_hours']) && is_string($requestData['availability_hours'])) {
                $requestData['availability_hours'] = json_decode($requestData['availability_hours'], true);
            }
            if (isset($requestData['portfolio_links']) && is_string($requestData['portfolio_links'])) {
                $requestData['portfolio_links'] = json_decode($requestData['portfolio_links'], true);
            }
            if (isset($requestData['references']) && is_string($requestData['references'])) {
                $requestData['references'] = json_decode($requestData['references'], true);
            }

            // Detect re-application: an existing REJECTED application for this email
            // means the user may submit a fresh application (e.g. via "Apply Again")
            $email = $requestData['email'] ?? null;
            $existingUser = $email ? User::where('email', $email)->first() : null;
            $existingApplication = $email ? PartnerApplication::where('email', $email)->first() : null;
            $isReapply = $existingApplication && $existingApplication->status === 'rejected';

            $emailRules = ['required', 'email', 'max:255'];
            $phoneRules = ['required', 'string', 'max:20'];

            if ($isReapply) {
                // Allow the same email/phone (they belong to the rejected application's user)
                $emailRules[] = Rule::unique('users', 'email')->ignore($existingUser?->user_id, 'user_id');
                $phoneRules[] = Rule::unique('users', 'phone')->ignore($existingUser?->user_id, 'user_id');
            } else {
                $emailRules[] = Rule::unique('users', 'email');
                $emailRules[] = Rule::unique('partner_applications', 'email');
                $phoneRules[] = Rule::unique('users', 'phone');
                $phoneRules[] = Rule::unique('partner_applications', 'phone');
            }

            $validator = Validator::make($requestData, [
                // Personal Information
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => $emailRules,
                'phone' => $phoneRules,
                'password' => 'required|string|min:8|confirmed',

                // Address Information
                'address' => 'required|string|max:500',
                'city' => 'required|string|max:100',
                'state' => 'required|string|max:100',
                'postal_code' => 'required|string|max:20',
                'emergency_contact' => 'nullable|string|max:20',

                // Partner Profile Information
                'bio' => 'required|string|min:50|max:1000',
                'experience_years' => 'required|integer|min:0|max:50',
                'hourly_rate' => 'required|numeric|min:0',
                'why_partner' => 'required|string|max:1000',
                'availability_hours' => 'nullable|array',
                'portfolio_links' => 'nullable|array',
                'references' => 'nullable|array',
                'services' => 'required|array|min:1',
                'services.*' => 'integer|exists:services,service_id',

                // KYC Documents (flexible validation)
                'documents' => 'nullable|array',
                'documents.*.type' => 'required_with:documents|in:license,insurance,identity,address_proof,bank_statement,tax_certificate',
                'documents.*.name' => 'required_with:documents|string|max:255',
                'documents.*.file' => 'required_with:documents|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max
                'documents.*.expires_at' => 'nullable|date|after:today',
            ]);

            if ($validator->fails()) {
                Log::warning('Partner application validation failed', [
                    'errors' => $validator->errors()
                ]);

                return $this->validationErrorResponse($validator->errors());
            }

            DB::beginTransaction();

            // Create partner application record
            $applicationData = [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'emergency_contact' => $request->emergency_contact,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'postal_code' => $request->postal_code,
                'bio' => $request->bio,
                'hourly_rate' => $request->hourly_rate,
                'experience_years' => $request->experience_years,
                'why_partner' => $request->why_partner,
                'availability_hours' => $requestData['availability_hours'] ?? null,
                'portfolio_links' => $requestData['portfolio_links'] ?? null,
                'references' => $requestData['references'] ?? null,
                'status' => 'pending',
            ];

            if ($isReapply && $existingApplication) {
                // Re-application: reset the rejected application to pending with fresh data
                $existingApplication->update(array_merge($applicationData, [
                    'rejection_reason' => null,
                    'rejected_at' => null,
                    'reviewed_at' => null,
                    'reviewed_by' => null,
                    'admin_notes' => null,
                    'documents' => null,
                ]));
                $existingApplication->services()->detach();
                $application = $existingApplication;

                Log::info('Partner application re-submitted after rejection', [
                    'application_id' => $application->application_id,
                    'email' => $application->email
                ]);
            } else {
                $application = PartnerApplication::create($applicationData);

                Log::info('Partner application created', [
                    'application_id' => $application->application_id,
                    'email' => $application->email
                ]);
            }

            // Attach selected services
            if (!empty($request->services)) {
                $application->services()->attach($request->services);
                Log::info('Services attached to application', [
                    'application_id' => $application->application_id,
                    'services' => $request->services
                ]);
            }

            // Check if this is a base64 upload (alternative for React Native)
            if ($request->has('documents_base64')) {
                Log::info('Processing base64 document upload');

                // Validate base64 upload format
                $validator = Validator::make($request->all(), [
                    'documents_base64' => 'required|array|min:1|max:10',
                    'documents_base64.*.name' => 'required|string|max:255',
                    'documents_base64.*.type' => 'required|string',
                    'documents_base64.*.data' => 'required|string|max:6000000',
                    'document_types' => 'required|array',
                    'document_types.*' => 'required|in:license,insurance,identity,address_proof,bank_statement,tax_certificate',
                    'document_names' => 'required|array',
                    'document_names.*' => 'required|string|max:255',
                    'document_expirations' => 'nullable|array',
                    'document_expirations.*' => 'nullable|date|after:today',
                ]);

                if ($validator->fails()) {
                    Log::warning('Base64 validation failed', [
                        'errors' => $validator->errors()
                    ]);

                    DB::rollBack();
                    return $this->validationErrorResponse($validator->errors());
                }

                // Per-file limits alone still allow an arbitrarily large total.
                // Refuse it here with a readable 422 instead of letting nginx or
                // PHP cut the request off with an opaque 413.
                $totalBase64 = array_sum(array_map(
                    static fn ($document) => strlen((string) ($document['data'] ?? '')),
                    (array) $request->input('documents_base64', [])
                ));

                if ($totalBase64 > 16000000) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Your documents are too large to send together. Attach smaller files or fewer documents.',
                        'errors' => [
                            'documents_base64' => ['The combined documents exceed the upload limit.'],
                        ],
                    ], 422);
                }

                // Handle base64 document upload
                try {
                    $this->handleBase64DocumentUpload($request, $application);
                } catch (\Exception $e) {
                    DB::rollBack();
                    return $this->failureResponse('Failed to upload documents', $e);
                }

                // Documents uploaded successfully, continue with user creation
            }

            // Legacy FormData upload validation
            $validator = Validator::make($request->all(), [
                'documents' => 'nullable|array|min:1|max:10',
                'documents.*.type' => 'required_with:documents|in:license,insurance,identity,address_proof,bank_statement,tax_certificate',
                'documents.*.name' => 'required_with:documents|string|max:255',
                'documents.*.file' => 'required_with:documents|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max
                'documents.*.expires_at' => 'nullable|date|after:today',
            ]);

            if ($validator->fails()) {
                Log::warning('FormData validation failed', [
                    'errors' => $validator->errors()
                ]);

                return $this->validationErrorResponse($validator->errors());
            }

            // Handle legacy FormData upload
            $uploadedDocuments = [];
            $hasAnyDocumentFiles = false;

            // Check if any document files were uploaded
            if ($request->input('documents')) {
                foreach ($request->input('documents', []) as $index => $docInfo) {
                    $fileKey = "documents.{$index}.file";
                    if ($request->hasFile($fileKey)) {
                        $hasAnyDocumentFiles = true;
                        break;
                    }
                }
            }

            if ($hasAnyDocumentFiles) {
                Log::info('Processing legacy FormData document uploads', [
                    'document_count' => count($request->input('documents', []))
                ]);

                foreach ($request->input('documents', []) as $index => $docInfo) {
                    $fileKey = "documents.{$index}.file";

                    if ($request->hasFile($fileKey)) {
                        $file = $request->file($fileKey);

                        if ($file && $file->isValid()) {
                            $extension = $file->getClientOriginalExtension();
                            $hashedName = Str::random(40) . '.' . $extension;
                            $filePath = "partner-applications/{$application->application_id}/{$docInfo['type']}/{$hashedName}";

                            // Store file securely
                            $storedPath = $file->storeAs('', $filePath, 'private');

                            if ($storedPath) {
                                $uploadedDocuments[] = [
                                    'type' => $docInfo['type'],
                                    'name' => $docInfo['name'],
                                    'file_path' => $filePath,
                                    'file_size' => $file->getSize(),
                                    'file_type' => $extension,
                                    'uploaded_at' => now()->toISOString(),
                                    'expires_at' => $docInfo['expires_at'] ?? null,
                                ];

                                Log::info('Document uploaded', [
                                    'application_id' => $application->application_id,
                                    'document_type' => $docInfo['type'],
                                    'file_path' => $filePath
                                ]);
                            }
                        } else {
                            Log::warning('Invalid file upload', [
                                'index' => $index,
                                'file_key' => $fileKey,
                                'file_exists' => $request->hasFile($fileKey)
                            ]);
                        }
                    } else {
                        Log::warning('File key not found', [
                            'index' => $index,
                            'file_key' => $fileKey,
                            'all_files' => array_keys($request->allFiles())
                        ]);
                    }
                }

                // Update application with documents
                if (!empty($uploadedDocuments)) {
                    $application->update(['documents' => $uploadedDocuments]);
                    Log::info('Documents saved to application', [
                        'application_id' => $application->application_id,
                        'document_count' => count($uploadedDocuments)
                    ]);
                }
            } else {
                Log::info('No document files uploaded for application', [
                    'application_id' => $application->application_id,
                    'input_documents' => $request->input('documents')
                ]);
            }

            // Also create a user account for login purposes
            if ($isReapply && $existingUser) {
                // Reuse the existing user account, refresh password and status
                $existingUser->update([
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'phone' => $request->phone,
                    'password' => Hash::make($request->password),
                    'partner_application_status' => 'pending',
                    'partner_application_submitted_at' => now(),
                    'partner_application_reviewed_at' => null,
                    'partner_application_reviewed_by' => null,
                    'partner_application_rejection_reason' => null,
                    'is_active' => true,
                ]);
                $user = $existingUser;

                Log::info('User account refreshed for re-application', [
                    'user_id' => $user->user_id,
                    'application_id' => $application->application_id
                ]);
            } else {
                $user = User::create([
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'password' => Hash::make($request->password),
                    'user_type' => 'customer', // Start as customer
                    'partner_application_status' => 'pending',
                    'partner_application_submitted_at' => now(),
                    'is_active' => true,
                ]);

                Log::info('User account created for application', [
                    'user_id' => $user->user_id,
                    'application_id' => $application->application_id
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Partner application submitted successfully. Your application is under review.',
                'data' => [
                    'application_id' => $application->application_id,
                    'user_id' => $user->user_id,
                    'application_status' => 'pending',
                    'submitted_at' => $application->created_at
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->failureResponse('Failed to submit partner application', $e);
        }
    }

    /**
     * Turn an unexpected failure into something the applicant can act on.
     *
     * A bare "Failed to submit partner application" made every failure look
     * identical in the form. A duplicate email/phone is the common one, and it
     * is a 422 with a field error rather than a server error.
     */
    private function failureResponse(string $fallback, \Exception $e): JsonResponse
    {
        // Client-caused document problems are not server errors: the applicant
        // can fix them by re-attaching the file.
        if ($e instanceof \InvalidArgumentException) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        Log::error($fallback, [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        if ($e instanceof \Illuminate\Database\QueryException) {
            $sqlState = $e->errorInfo[0] ?? null;
            $driverCode = $e->errorInfo[1] ?? null;

            if (in_array($sqlState, ['23505', '23000'], true) || $driverCode === 1062) {
                return response()->json([
                    'success' => false,
                    'message' => 'That email address or phone number is already registered. Sign in instead, or apply with different contact details.',
                    'errors' => [
                        'email' => ['This email address or phone number is already registered.'],
                    ],
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => 'We could not save your application: ' . $e->getPrevious()?->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => false,
            'message' => $fallback . ': ' . $e->getMessage(),
        ], 500);
    }

    /**
     * Get partner application status (for users to check their application)
     */
    public function getApplicationStatus()
    {
        try {
            $user = Auth::user();

            // Check if user has a partner application
            $application = PartnerApplication::where('email', $user->email)->first();

            if (!$application) {
                // Fallback to check user model for legacy applications
                if (!$user->partner_application_submitted_at || $user->partner_application_status === 'none') {
                    return response()->json([
                        'success' => false,
                        'message' => 'No partner application found',
                    ], 404);
                }

                $applicationData = [
                    'status' => $user->partner_application_status,
                    'submitted_at' => $user->partner_application_submitted_at,
                    'reviewed_at' => $user->partner_application_reviewed_at,
                    'rejection_reason' => $user->partner_application_rejection_reason,
                ];
            } else {
                // Use PartnerApplication model data
                $applicationData = [
                    'application_id' => $application->application_id,
                    'status' => $application->status,
                    'submitted_at' => $application->created_at,
                    'reviewed_at' => $application->reviewed_at,
                    'rejection_reason' => $application->rejection_reason,
                    'documents' => $application->documents,
                ];
            }

            // If approved, include partner profile data
            if (isset($applicationData['status']) && $applicationData['status'] === 'approved' && $user->user_type === 'partner') {
                $applicationData['partner_profile'] = $user->partnerProfile;
            }

            return response()->json([
                'success' => true,
                'data' => $applicationData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch application status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin: Get all pending partner applications
     */
    public function getPendingApplications()
    {
        try {
            $applications = PartnerApplication::where('status', 'pending')
                ->with(['services'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($application) {
                    return [
                        'application_id' => $application->application_id,
                        'name' => $application->first_name . ' ' . $application->last_name,
                        'email' => $application->email,
                        'phone' => $application->phone,
                        'submitted_at' => $application->created_at,
                        'experience_years' => $application->experience_years,
                        'why_partner' => $application->why_partner,
                        'emergency_contact' => $application->emergency_contact,
                        'address' => $application->address,
                        'city' => $application->city,
                        'state' => $application->state,
                        'postal_code' => $application->postal_code,
                        'services' => $application->services->pluck('name'),
                        'documents_count' => is_array($application->documents) ? count($application->documents) : 0,
                        'documents' => $application->documents,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $applications
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending applications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin: Approve partner application
     */
    public function approveApplication(Request $request, $applicationId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'admin_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            $application = PartnerApplication::findOrFail($applicationId);

            if ($application->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Application is not in pending status'
                ], 400);
            }

            DB::beginTransaction();

            // Update application status
            $application->update([
                'status' => 'approved',
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
                'admin_notes' => $request->admin_notes,
                'approved_at' => now(),
            ]);

            // Update or create corresponding user account
            $user = User::where('email', $application->email)->first();
            if ($user) {
                $user->update([
                    'user_type' => 'partner',
                    'partner_application_status' => 'approved',
                    'partner_application_reviewed_at' => now(),
                    'partner_application_reviewed_by' => Auth::id(),
                ]);

                // Update partner profile status
                if ($user->partnerProfile) {
                    $user->partnerProfile->update([
                        'status' => 'active',
                        'kyc_verified_at' => now(),
                    ]);
                } else {
                    // Create the partner profile if it doesn't exist - without it the
                    // approved partner cannot access the partner home, jobs, or earnings
                    PartnerProfile::create([
                        'partner_id' => $user->user_id,
                        'status' => 'active',
                        'rating_avg' => 0,
                        'total_ratings' => 0,
                        'kyc_verified_at' => now(),
                    ]);
                }

                // Attach the selected services to the partner so they can be matched to jobs
                $serviceIds = $application->services()->pluck('services.service_id')->all();
                if (!empty($serviceIds) && $user->partnerProfile) {
                    $user->partnerProfile->services()->sync($serviceIds);
                    Log::info('Services synced to approved partner profile', [
                        'partner_id' => $user->user_id,
                        'services' => $serviceIds
                    ]);
                }
            }

            Log::info('Partner application approved', [
                'application_id' => $application->application_id,
                'email' => $application->email,
                'admin_id' => Auth::id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Partner application approved successfully',
                'data' => [
                    'application_id' => $application->application_id,
                    'user_id' => $user ? $user->user_id : null,
                    'new_user_type' => 'partner',
                    'approved_at' => $application->approved_at
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve application',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin: Reject partner application
     */
    public function rejectApplication(Request $request, $applicationId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return $this->validationErrorResponse($validator->errors());
            }

            $application = PartnerApplication::findOrFail($applicationId);

            if ($application->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Application is not in pending status'
                ], 400);
            }

            // Update application status
            $application->update([
                'status' => 'rejected',
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
                'rejection_reason' => $request->rejection_reason,
                'rejected_at' => now(),
            ]);

            // Update corresponding user account if exists
            $user = User::where('email', $application->email)->first();
            if ($user) {
                $user->update([
                    'partner_application_status' => 'rejected',
                    'partner_application_reviewed_at' => now(),
                    'partner_application_reviewed_by' => Auth::id(),
                    'partner_application_rejection_reason' => $request->rejection_reason,
                ]);
            }

            Log::info('Partner application rejected', [
                'application_id' => $application->application_id,
                'email' => $application->email,
                'rejection_reason' => $request->rejection_reason,
                'admin_id' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Partner application rejected',
                'data' => [
                    'application_id' => $application->application_id,
                    'user_id' => $user ? $user->user_id : null,
                    'rejected_at' => $application->rejected_at
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject application',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle base64 document upload for React Native
     */
    private function handleBase64DocumentUpload(Request $request, PartnerApplication $application): bool
    {
        try {
            $documentsBase64 = $request->input('documents_base64', []);
            $documentTypes = $request->input('document_types', []);
            $documentNames = $request->input('document_names', []);
            $documentExpirations = $request->input('document_expirations', []);

            Log::info('Base64 document upload data', [
                'documents_count' => count($documentsBase64),
                'document_types_count' => count($documentTypes),
                'application_id' => $application->application_id
            ]);

            if (empty($documentsBase64) || count($documentsBase64) !== count($documentTypes) || count($documentsBase64) !== count($documentNames)) {
                throw new \InvalidArgumentException(
                    'The attached documents did not match the selected document types. Please re-attach them and try again.'
                );
            }

            $uploadedDocuments = [];

            foreach ($documentsBase64 as $index => $docData) {
                // Decode base64 file
                $fileData = base64_decode((string) ($docData['data'] ?? ''), true);
                if ($fileData === false) {
                    Log::warning('Invalid base64 data', [
                        'index' => $index,
                        'application_id' => $application->application_id
                    ]);
                    // Silently skipping the file left the application short of
                    // the documents the applicant thought they sent.
                    throw new \InvalidArgumentException(
                        '"' . ($docData['name'] ?? 'The file') . '" could not be read. Please pick the file again.'
                    );
                }

                // Generate unique filename
                $extension = $this->getExtensionFromMimeType($docData['type']);
                $filename = 'partner_app_' . $application->application_id . '_' . time() . '_' . $index . '.' . $extension;
                $filePath = 'partner-applications/' . $filename;

                Log::info('Base64 Document Processing', [
                    'index' => $index,
                    'original_name' => $docData['name'],
                    'mime_type' => $docData['type'],
                    'extension' => $extension,
                    'generated_filename' => $filename,
                    'file_path' => $filePath,
                    'application_id' => $application->application_id,
                    'document_type' => $documentTypes[$index]
                ]);

                // Store file
                if (!Storage::disk('private')->put($filePath, $fileData)) {
                    Log::error('Failed to store base64 document', [
                        'index' => $index,
                        'file_path' => $filePath,
                        'application_id' => $application->application_id
                    ]);
                    throw new \Exception("Failed to store document {$index}");
                }

                // Calculate file hash for integrity
                $fileHash = hash('sha256', $fileData);

                // Create document record
                $uploadedDocuments[] = [
                    'type' => $documentTypes[$index],
                    'name' => $documentNames[$index],
                    'file_path' => $filePath,
                    'file_size' => strlen($fileData),
                    'file_type' => $extension,
                    'uploaded_at' => now()->toISOString(),
                    'expires_at' => $documentExpirations[$index] ?? null,
                ];

                Log::info('Document stored successfully', [
                    'document_index' => $index,
                    'file_path' => $filePath,
                    'file_size' => strlen($fileData),
                    'application_id' => $application->application_id
                ]);
            }

            // Update application with documents
            if (!empty($uploadedDocuments)) {
                $application->update(['documents' => $uploadedDocuments]);
                Log::info('Documents saved to partner application', [
                    'application_id' => $application->application_id,
                    'document_count' => count($uploadedDocuments)
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Base64 document upload failed', [
                'application_id' => $application->application_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Get file extension from mime type
     */
    private function getExtensionFromMimeType(string $mimeType): string
    {
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        return $extensions[$mimeType] ?? 'bin';
    }
}
