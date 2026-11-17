<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Support\SchemaCompat;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class PartnerController extends Controller
{
    /**
     * Display a listing of partners.
     */
    public function index(Request $request): View
    {
        $query = User::where('user_type', 'partner')
            ->with(['partnerProfile', 'services']);

        // Filter by activity status
        if ($request->filled('active_status')) {
            if ($request->active_status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->active_status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Filter by verification status
        if ($request->filled('verification_status')) {
            if ($request->verification_status === 'verified') {
                $query->whereHas('partnerProfile', function ($q) {
                    $q->where('status', 'active');
                });
            } else {
                $query->whereHas('partnerProfile', function ($q) use ($request) {
                    $q->where('status', $request->verification_status);
                });
            }
        }

        // Search by name, email, or phone
        if ($request->filled('search')) {
            $searchTerm = '%' . $request->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', SchemaCompat::likeOperator(), $searchTerm)
                    ->orWhere('last_name', SchemaCompat::likeOperator(), $searchTerm)
                    ->orWhere('email', SchemaCompat::likeOperator(), $searchTerm)
                    ->orWhere('phone', SchemaCompat::likeOperator(), $searchTerm)
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", [$searchTerm]);
            });
        }

        // Sort options
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $query->orderBy($sortBy, $sortOrder);

        $partners = $query->paginate(15)->withQueryString();

        // Statistics
        $stats = [
            'total_partners' => User::where('user_type', 'partner')->count(),
            'active_partners' => User::where('user_type', 'partner')->where('is_active', true)->count(),
            'verified_partners' => User::where('user_type', 'partner')
                ->whereHas('partnerProfile', function ($q) {
                    $q->where('status', 'active'); // Only count 'active' status as verified
                })->count(),
            'pending_verification' => User::where('user_type', 'partner')
                ->whereHas('partnerProfile', function ($q) {
                    $q->where('status', 'pending_verification');
                })->count(),
            'pending_applications' => \App\Models\PartnerApplication::whereIn('status', ['pending', 'under_review'])->count(),
        ];

        // Recent applications for quick overview
        $recentApplications = \App\Models\PartnerApplication::with(['reviewedBy'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Count of pending applications for badge display
        $pendingApplicationsCount = \App\Models\PartnerApplication::where('status', 'pending')->count();

        return view('admin.partners.index', compact('partners', 'stats', 'recentApplications', 'pendingApplicationsCount'));
    }

    /**
     * Show the form for creating a new partner.
     */
    public function create(): View
    {
        $services = Service::where('is_active', true)->get();
        return view('admin.partners.create', compact('services'));
    }

    /**
     * Store a newly created partner.
     */
    public function store(Request $request): RedirectResponse
    {
        try {
            $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'required|string|unique:users,phone',
                'password' => 'required|string|min:8|confirmed',
                'emergency_contact' => 'nullable|string|max:15',
                'services' => 'required|array|min:1',
                'services.*' => 'exists:services,service_id',
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'status' => 'nullable|in:active,pending_verification,inactive',
            ]);

            // Create user
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'user_type' => 'partner',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            // Handle avatar upload
            $avatarUrl = null;
            if ($request->hasFile('avatar')) {
                $avatarPath = $request->file('avatar')->store('partners/avatars', 'public');
                $avatarUrl = '/storage/' . $avatarPath;
                $user->update(['avatar_url' => $avatarUrl]);
            }

            // Create partner profile
            $user->partnerProfile()->create([
                'status' => $request->get('status', 'pending_verification'),
                'rating_avg' => 0,
                'total_ratings' => 0,
                'bank_account' => [],
                'kyc_verified_at' => null,
            ]);

            // Attach services
            $user->services()->attach($request->services);

            return redirect()->route('admin.partners.index')
                ->with('success', 'Partner "' . $user->first_name . ' ' . $user->last_name . '" created successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to create partner. Please try again.')
                ->withInput();
        }
    }

    /**
     * Display the specified partner.
     */
    public function show(User $partner): View
    {
        // Ensure we're dealing with a partner
        if ($partner->user_type !== 'partner') {
            abort(404, 'Partner not found');
        }

        // Load the partner with all necessary relationships
        $partner->load([
            'partnerProfile.serviceRequests.order.client',
            'partnerProfile.serviceRequests.order.orderItems.service',
            'partnerProfile.earnings.order.client',
            'partnerProfile.earnings.order.orderItems.service',
            'partnerProfile.earnings.commissionRule',
            'partnerProfile.balance',
            'services',
            'ratings'
        ]);

        // Get partner profile or fail
        $partnerProfile = $partner->partnerProfile;
        if (!$partnerProfile) {
            abort(404, 'Partner profile not found');
        }

        // Get service requests for this partner
        $serviceRequests = $partnerProfile->serviceRequests ?? collect();

        // Get all service requests with detailed information
        $allServiceRequests = $serviceRequests->sortByDesc('created_at');
        $completedServiceRequests = $serviceRequests->where('status', 'completed');
        $inProgressServiceRequests = $serviceRequests->whereIn('status', ['pending', 'confirmed', 'in_progress']);

        // Get partner earnings data
        $earnings = $partnerProfile->earnings ?? collect();
        $balance = $partnerProfile->balance;

        // Calculate monthly earnings for chart (last 12 months)
        $monthlyEarnings = $earnings
            ->filter(function ($earning) {
                return $earning->calculated_at && $earning->calculated_at >= now()->subMonths(12);
            })
            ->groupBy(function ($earning) {
                return $earning->calculated_at->format('Y-m');
            })
            ->map(function ($group, $month) {
                return [
                    'month' => $month,
                    'year' => substr($month, 0, 4),
                    'month_num' => substr($month, 5, 2),
                    'total' => $group->sum('partner_share'),
                    'count' => $group->count()
                ];
            })
            ->sortByDesc('month')
            ->take(12)
            ->values();

        // Get services offered by this partner
        $servicesOffered = $partner->services ?? collect();

        // Calculate comprehensive statistics
        $stats = [
            // Order statistics
            'total_orders' => $serviceRequests->count(),
            'completed_orders' => $completedServiceRequests->count(),
            'in_progress_orders' => $inProgressServiceRequests->count(),
            'pending_orders' => $serviceRequests->where('status', 'pending')->count(),

            // Earnings statistics
            'total_earnings' => $earnings->sum('partner_share'),
            'total_gross_amount' => $earnings->sum('gross_amount'),
            'platform_earnings' => $earnings->sum('platform_share'),
            'average_earning_per_order' => $earnings->isNotEmpty() ? $earnings->avg('partner_share') : 0,
            'earnings_count' => $earnings->count(),

            // Balance statistics (PREPAID MODEL)
            'available_balance' => $balance->available_balance ?? 0,
            'total_earnings' => $balance->total_earnings ?? 0,    // Fixed field name
            'total_withdrawn' => $balance->total_withdrawn ?? 0,

            // Rating statistics
            'average_rating' => $partner->ratings->avg('rating_value') ?? 0,
            'total_reviews' => $partner->ratings->count(),

            // Service statistics
            'services_offered_count' => $servicesOffered->count(),
            'active_services_count' => $servicesOffered->where('is_active', true)->count(),
        ];

        // Recent activity (last 10 service requests)
        $recentOrders = $allServiceRequests->take(10);

        // Recent earnings (last 10 earnings)
        $recentEarnings = $earnings->sortByDesc('calculated_at')->take(10);

        // Working hours the partner set in the partner app, so support can see
        // why a partner was or was not offered a job.
        $availability = app(\App\Services\AvailabilityService::class);
        $workingHours = collect(range(0, 6))->map(function (int $offset) use ($partner, $availability) {
            $date = \Carbon\Carbon::today()->addDays($offset);

            return [
                'date' => $date,
                'windows' => $availability->windowsFor((int) $partner->user_id, $date),
            ];
        });

        // Get assigned services (services the partner can perform)
        $assignedServices = $servicesOffered;

        // Get completed services with details
        $completedServicesDetails = $completedServiceRequests->map(function ($serviceRequest) {
            $order = $serviceRequest->order;
            $orderItems = $order ? $order->orderItems : collect();

            return [
                'service_request' => $serviceRequest,
                'order' => $order,
                'services' => $orderItems->pluck('service')->filter(),
                'total_amount' => $order ? $order->total_amount : 0,
                'completed_at' => $serviceRequest->completed_at ?? $serviceRequest->updated_at,
                'client_name' => $order && $order->client ? $order->client->first_name . ' ' . $order->client->last_name : 'Unknown Client'
            ];
        })->sortByDesc('completed_at');

        return view('admin.partners.show', compact(
            'partner',
            'workingHours',
            'stats',
            'recentOrders',
            'recentEarnings',
            'earnings',
            'balance',
            'monthlyEarnings',
            'assignedServices',
            'completedServicesDetails',
            'allServiceRequests',
            'completedServiceRequests',
            'inProgressServiceRequests'
        ));
    }

    /**
     * Show the form for editing the partner.
     */
    public function edit(User $partner): View
    {
        $partner->load(['partnerProfile', 'services']);
        $services = Service::where('is_active', true)->get();
        return view('admin.partners.edit', compact('partner', 'services'));
    }

    /**
     * Update the specified partner.
     */
    public function update(Request $request, User $partner): RedirectResponse
    {
        try {
            $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($partner->user_id, 'user_id')],
                'phone' => ['required', 'string', Rule::unique('users', 'phone')->ignore($partner->user_id, 'user_id')],
                'password' => 'nullable|string|min:8|confirmed',
                'status' => ['required', Rule::in(['onboarding', 'pending_verification', 'active', 'inactive', 'suspended'])],
                'business_name' => 'nullable|string|max:255',
                'business_address' => 'nullable|string|max:500',
                'bio' => 'nullable|string|max:2000',
                'experience_years' => 'nullable|integer|min:0|max:60',
                'hourly_rate' => 'nullable|numeric|min:0',
                'services' => 'required|array|min:1',
                'services.*' => 'exists:services,service_id',
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'is_active' => 'boolean',
            ]);

            // Update user
            $userData = [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'is_active' => $request->boolean('is_active'),
            ];

            if ($request->filled('password')) {
                $userData['password'] = Hash::make($request->password);
            }

            if ($request->hasFile('avatar')) {
                $avatarPath = $request->file('avatar')->store('partners/avatars', 'public');
                $userData['avatar_url'] = '/storage/' . $avatarPath;
            }

            $partner->update($userData);

            // Update partner profile (verification status + business fields).
            if (!$partner->partnerProfile) {
                $partner->partnerProfile()->create([
                    'status' => $request->status,
                    'rating_avg' => 0,
                    'total_ratings' => 0,
                    'bank_account' => [],
                ]);
            } else {
                $partner->partnerProfile->update([
                    'status' => $request->status,
                    'business_name' => $request->business_name,
                    'business_address' => $request->business_address,
                    'bio' => $request->bio,
                    'experience_years' => $request->experience_years,
                    'hourly_rate' => $request->hourly_rate,
                ]);
            }

            // Sync services
            $partner->services()->sync($request->services);

            return redirect()->route('admin.partners.show', $partner)
                ->with('success', 'Partner "' . $partner->full_name . '" updated successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to update partner', [
                'partner_id' => $partner->user_id,
                'error' => $e->getMessage(),
            ]);
            return redirect()->back()
                ->with('error', 'Failed to update partner. Please try again.')
                ->withInput();
        }
    }

    /**
     * Remove the specified partner.
     */
    public function destroy(User $partner): RedirectResponse
    {
        try {
            // Check if partner has active service requests
            $activeRequests = $partner->partnerProfile->serviceRequests()
                ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
                ->count();

            if ($activeRequests > 0) {
                return back()->with('error', 'Cannot delete partner "' . $partner->full_name . '" because they have ' . $activeRequests . ' active service request(s).');
            }

            $partnerName = $partner->full_name;
            $partner->delete();

            return redirect()->route('admin.partners.index')
                ->with('success', 'Partner "' . $partnerName . '" deleted successfully!');
        } catch (\Exception $e) {
            return redirect()->route('admin.partners.index')
                ->with('error', 'Failed to delete partner. Please try again.');
        }
    }

    /**
     * Toggle partner status.
     */
    public function toggleStatus(User $partner): RedirectResponse
    {
        $partner->update(['is_active' => !$partner->is_active]);

        $status = $partner->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Partner {$status} successfully!");
    }

    /**
     * Update verification status.
     */
    public function updateVerification(Request $request, User $partner): RedirectResponse
    {
        $request->validate([
            'status' => 'required|in:onboarding,active,inactive,suspended,pending_verification',
            'rejection_reason' => 'required_if:status,suspended|string|max:500',
        ]);

        $partner->partnerProfile->update([
            'status' => $request->status,
            'rejection_reason' => $request->rejection_reason,
        ]);

        return back()->with('success', 'Partner status updated successfully!');
    }

    /**
     * Get partners statistics.
     */
    public function statistics(): View
    {
        $stats = [
            'total_partners' => User::where('user_type', 'partner')->count(),
            'active_partners' => User::where('user_type', 'partner')->where('is_active', true)->count(),
            'verified_partners' => User::where('user_type', 'partner')
                ->whereHas('partnerProfile', function ($q) {
                    $q->where('status', 'active');
                })->count(),
            'top_earning_partners' => User::where('user_type', 'partner')
                ->take(10)
                ->get(),
            'recent_registrations' => User::where('user_type', 'partner')
                ->latest()
                ->take(10)
                ->get(),
        ];

        return view('admin.partners.statistics', compact('stats'));
    }
}
