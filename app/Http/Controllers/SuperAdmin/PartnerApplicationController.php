<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PartnerApplication;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PartnerApplicationController extends Controller
{
    /**
     * Display a listing of partner applications.
     */
    public function index(Request $request): View
    {
        $query = PartnerApplication::with(['services', 'reviewedBy']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search by name, email, or phone
        if ($request->filled('search')) {
            $searchTerm = '%' . $request->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'ILIKE', $searchTerm)
                    ->orWhere('last_name', 'ILIKE', $searchTerm)
                    ->orWhere('email', 'ILIKE', $searchTerm)
                    ->orWhere('phone', 'ILIKE', $searchTerm)
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", [$searchTerm]);
            });
        }

        // Sort options
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $query->orderBy($sortBy, $sortOrder);

        $applications = $query->paginate(15)->withQueryString();

        // Statistics
        $stats = [
            'total_applications' => PartnerApplication::count(),
            'pending_applications' => PartnerApplication::where('status', 'pending')->count(),
            'under_review' => PartnerApplication::where('status', 'under_review')->count(),
            'approved_applications' => PartnerApplication::where('status', 'approved')->count(),
            'rejected_applications' => PartnerApplication::where('status', 'rejected')->count(),
            'converted_applications' => PartnerApplication::where('status', 'converted')->count(),
        ];

        return view('admin.partner-applications.index', compact('applications', 'stats'));
    }

    /**
     * Display the specified partner application.
     */
    public function show(PartnerApplication $application): View
    {
        $application->load(['services', 'reviewedBy', 'createdPartner']);

        return view('admin.partner-applications.show', compact('application'));
    }

    /**
     * Show the form for reviewing the application.
     */
    public function review(PartnerApplication $application): View|RedirectResponse
    {
        if (!$application->canBeApproved() && !$application->canBeRejected()) {
            // Terminal states (converted / rejected) have no review form -
            // send the admin to the application's detail page instead of a 404.
            return redirect()
                ->route('admin.partner-applications.show', $application)
                ->with('info', 'This application has already been processed.');
        }

        $application->load(['services', 'reviewedBy', 'createdPartner']);

        return view('admin.partner-applications.review', compact('application'));
    }

    /**
     * Update the application status.
     */
    public function updateStatus(Request $request, PartnerApplication $application): RedirectResponse
    {
        $request->validate([
            'status' => 'required|in:pending,under_review,approved,rejected',
            'admin_notes' => 'nullable|string|max:1000',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        // Additional validation for rejection_reason when status is rejected
        if ($request->status === 'rejected' && empty($request->rejection_reason)) {
            return back()->withErrors(['rejection_reason' => 'Rejection reason is required when rejecting an application.'])->withInput();
        }

        $data = [
            'status' => $request->status,
            'admin_notes' => $request->admin_notes,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ];

        if ($request->status === 'approved') {
            $data['approved_at'] = now();
            $data['rejected_at'] = null;
            $data['rejection_reason'] = null;

            // Find existing user by email and convert to partner
            try {
                DB::beginTransaction();

                $user = \App\Models\User::where('email', $application->email)->first();

                if (!$user) {
                    DB::rollBack();
                    return back()->with('error', 'No user account found with this email address.');
                }

                // Check if user is already a partner
                if ($user->user_type === 'partner') {
                    DB::rollBack();
                    return back()->with('error', 'This user is already a partner.');
                }

                // Update user type to partner
                $user->update([
                    'user_type' => 'partner',
                    'partner_application_status' => 'approved',
                    'partner_application_reviewed_at' => now(),
                    'partner_application_reviewed_by' => Auth::id(),
                    'partner_application_rejection_reason' => null,
                ]);

                // Create partner profile only if it doesn't exist yet
                // (a duplicate row would violate the partner_id primary key)
                $profile = $user->partnerProfile;
                if (!$profile) {
                    $profile = $user->partnerProfile()->create([
                        'status' => 'active',
                        'rating_avg' => 0,
                        'total_ratings' => 0,
                        'bank_account' => [],
                        'kyc_verified_at' => now(),
                    ]);
                } else {
                    $profile->update([
                        'status' => 'active',
                        'kyc_verified_at' => now(),
                    ]);
                }

                // Attach services from application (no-op for already-attached ones)
                $serviceIds = $application->services->pluck('service_id')->toArray();
                if (!empty($serviceIds)) {
                    $profile->services()->syncWithoutDetaching($serviceIds);
                }

                // Update application status to converted
                $data['status'] = 'converted';
                $data['created_partner_id'] = $user->user_id;

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Partner application approval conversion failed', [
                    'application_id' => $application->application_id,
                    'error' => $e->getMessage()
                ]);
                return back()->with('error', 'Failed to convert user to partner. Please try again.');
            }
        } elseif ($request->status === 'rejected') {
            $data['rejected_at'] = now();
            $data['approved_at'] = null;
            $data['rejection_reason'] = $request->rejection_reason;
        }

        $application->update($data);

        $statusLabel = $application->status_label;

        // The review page aborts(404) for any application that can no longer
        // be approved or rejected - so never redirect there for terminal
        // states.
        if ($application->status === 'converted') {
            $partner = User::find($application->created_partner_id);

            if ($partner) {
                return redirect()
                    ->route('admin.partners.show', $partner)
                    ->with('success', "Application approved! Partner account is ready for {$partner->full_name}.");
            }

            return redirect()
                ->route('admin.partner-applications.index')
                ->with('success', "Application has been {$statusLabel}!");
        }

        if ($application->status === 'rejected') {
            return redirect()
                ->route('admin.partner-applications.show', $application)
                ->with('success', "Application has been {$statusLabel}!");
        }

        return redirect()->route('admin.partner-applications.review', $application)
            ->with('success', "Application has been {$statusLabel}!");
    }

    /**
     * Convert approved application to partner.
     */
    public function convertToPartner(Request $request, PartnerApplication $application): RedirectResponse
    {
        if (!$application->isApproved()) {
            return back()->with('error', 'Only approved applications can be converted to partners.');
        }

        if ($application->status === 'converted') {
            return back()->with('error', 'This application has already been converted to a partner.');
        }

        try {
            DB::beginTransaction();

            // Find existing user by email
            $user = User::where('email', $application->email)->first();

            if (!$user) {
                DB::rollBack();
                return back()->with('error', 'No user account found with this email address.');
            }

            // Check if user is already a partner
            if ($user->user_type === 'partner') {
                DB::rollBack();
                return back()->with('error', 'This user is already a partner.');
            }

            // Update user type to partner
            $user->update([
                'user_type' => 'partner',
                'partner_application_status' => 'approved',
                'partner_application_reviewed_at' => now(),
                'partner_application_reviewed_by' => Auth::id(),
                'partner_application_rejection_reason' => null,
            ]);

            // Create partner profile only if it doesn't exist yet
            // (a duplicate row would violate the partner_id primary key)
            $profile = $user->partnerProfile;
            if (!$profile) {
                $profile = $user->partnerProfile()->create([
                    'status' => 'active',
                    'rating_avg' => 0,
                    'total_ratings' => 0,
                    'bank_account' => [],
                    'kyc_verified_at' => now(),
                ]);
            } else {
                $profile->update([
                    'status' => 'active',
                    'kyc_verified_at' => now(),
                ]);
            }

            // Attach services from application (no-op for already-attached ones)
            $serviceIds = $application->services->pluck('service_id')->toArray();
            if (!empty($serviceIds)) {
                $profile->services()->syncWithoutDetaching($serviceIds);
            }

            // Update application status
            $application->update([
                'status' => 'converted',
                'created_partner_id' => $user->user_id,
            ]);

            DB::commit();

            return redirect()->route('admin.partners.show', $user)
                ->with('success', "Partner account created successfully for {$user->full_name}!");
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Partner application conversion failed', [
                'application_id' => $application->application_id,
                'error' => $e->getMessage()
            ]);
            return back()
                ->with('error', 'Failed to create partner account. Please try again.')
                ->withInput();
        }
    }

    public function downloadDocument(PartnerApplication $application, string $documentIndex): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        if (!$application->hasDocuments()) {
            abort(404, 'No documents found');
        }

        $documents = $application->documents;

        // Check if documentIndex is numeric (array index) or string (document type)
        if (is_numeric($documentIndex)) {
            // Numeric index
            $documentKey = (int) $documentIndex;
            if (!isset($documents[$documentKey]) || !isset($documents[$documentKey]['file_path'])) {
                abort(404, 'Document not found');
            }
            $documentData = $documents[$documentKey];
        } else {
            // Document type - find by type
            $documentData = null;
            foreach ($documents as $doc) {
                if (isset($doc['type']) && $doc['type'] === $documentIndex) {
                    $documentData = $doc;
                    break;
                }
            }

            if (!$documentData || !isset($documentData['file_path'])) {
                abort(404, 'Document not found');
            }
        }

        $filePath = $documentData['file_path'];

        if (!Storage::disk('private')->exists($filePath)) {
            abort(404, 'File not found');
        }

        $documentName = $documentData['name'] ?? 'document';
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        return response()->download(
            Storage::disk('private')->path($filePath),
            $documentName . '.' . $extension
        );
    }

    /**
     * Delete the specified application.
     */
    public function destroy(PartnerApplication $application): RedirectResponse
    {
        try {
            // Check if application is converted
            if ($application->status === 'converted') {
                return back()->with('error', 'Cannot delete converted applications.');
            }

            // Delete associated documents
            if ($application->hasDocuments()) {
                foreach ($application->documents as $document) {
                    if (isset($document['file_path']) && Storage::disk('private')->exists($document['file_path'])) {
                        Storage::disk('private')->delete($document['file_path']);
                    }
                }
            }

            $applicantName = $application->full_name;
            $application->delete();

            return redirect()->route('admin.partner-applications.index')
                ->with('success', "Application from {$applicantName} deleted successfully!");
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete application. Please try again.');
        }
    }

    /**
     * Bulk actions for applications.
     */
    public function bulkAction(Request $request): RedirectResponse
    {
        $request->validate([
            'action' => 'required|in:approve,reject,delete',
            'applications' => 'required|array|min:1',
            'applications.*' => 'exists:partner_applications,application_id',
            'rejection_reason' => 'required_if:action,reject|string|max:500',
        ]);

        $applications = PartnerApplication::whereIn('application_id', $request->applications)->get();
        $count = 0;

        foreach ($applications as $application) {
            try {
                switch ($request->action) {
                    case 'approve':
                        if ($application->canBeApproved()) {
                            $application->update([
                                'status' => 'approved',
                                'approved_at' => now(),
                                'reviewed_by' => Auth::id(),
                                'reviewed_at' => now(),
                            ]);
                            $count++;
                        }
                        break;

                    case 'reject':
                        if ($application->canBeRejected()) {
                            $application->update([
                                'status' => 'rejected',
                                'rejected_at' => now(),
                                'rejection_reason' => $request->rejection_reason,
                                'reviewed_by' => Auth::id(),
                                'reviewed_at' => now(),
                            ]);
                            $count++;
                        }
                        break;

                    case 'delete':
                        if ($application->status !== 'converted') {
                            $application->delete();
                            $count++;
                        }
                        break;
                }
            } catch (\Exception $e) {
                // Continue with other applications if one fails
                continue;
            }
        }

        $actionLabel = ucfirst($request->action) . ($request->action === 'delete' ? 'd' : 'd');

        return back()->with('success', "{$count} application(s) {$actionLabel} successfully!");
    }
}
