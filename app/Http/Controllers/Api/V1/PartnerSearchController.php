<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\User;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PartnerSearchController extends Controller
{
    public function __construct(private AvailabilityService $availability)
    {
    }

    /**
     * Search for partners based on criteria
     */
    public function search(Request $request): JsonResponse
    {
        try {
            // Log the incoming request for debugging
            Log::info('Partner search request received', [
                'user_id' => $request->user()?->user_id,
                'params' => $request->all()
            ]);

            $request->validate([
                'search' => 'nullable|string|max:255',
                'service_ids' => 'nullable|string', // comma-separated service IDs
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'scheduled_at' => 'nullable|date|after_or_equal:today',
                // 0 is the "no rating filter" sentinel the app sends on every
                // search: the old between:1,5 rejected it as a 422 and the
                // whole partner list came back empty.
                'min_rating' => 'nullable|numeric|between:0,5',
                'max_distance' => 'nullable|numeric|min:1',
                'sort_by' => 'nullable|in:rating,distance,price,completion_rate',
                'sort_order' => 'nullable|in:asc,desc',
                'limit' => 'nullable|integer|min:1|max:50',
                'page' => 'nullable|integer|min:1'
            ]);

            $searchQuery = $request->input('search');
            $serviceIds = $request->input('service_ids'); // comma-separated
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $scheduledAt = $request->input('scheduled_at');
            $minRating = $request->input('min_rating', 0);
            $maxDistance = $request->input('max_distance');
            $sortBy = $request->input('sort_by', 'rating');
            $sortOrder = $request->input('sort_order', 'desc');
            $limit = $request->input('limit', 20);
            $page = $request->input('page', 1);

            // If no service is specified, return empty results with helpful message
            if (!$serviceIds) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => 20,
                        'total' => 0,
                        'has_more' => false,
                    ],
                    'filters' => [
                        'total_partners' => 0,
                        'available_now' => 0,
                        'within_5km' => null,
                        'service_experts' => null,
                    ],
                    'message' => 'Please select a service to find available partners'
                ]);
            }

            // Build the query
            $serviceIdArray = $serviceIds
                ? array_values(array_filter(array_map('intval', explode(',', $serviceIds))))
                : [];

            $query = PartnerProfile::query()
                ->with([
                    'user:user_id,first_name,last_name,phone,avatar_url',
                    'services:service_id,name',
                    'ratings:partner_id,rating_value,review_text,created_at'
                ])
                ->where('status', 'active')
                ->whereHas('user', function ($q) {
                    $q->where('is_active', true);
                })
                // New partners have a null average - they must still be
                // findable instead of being filtered out by any min_rating.
                ->where(function ($q) use ($minRating) {
                    $q->whereNull('rating_avg')->orWhere('rating_avg', '>=', $minRating);
                });

            // Add search filter
            if ($searchQuery) {
                $query->whereHas('user', function ($q) use ($searchQuery) {
                    $q->where(function ($subQ) use ($searchQuery) {
                        $subQ->where('first_name', 'LIKE', "%{$searchQuery}%")
                            ->orWhere('last_name', 'LIKE', "%{$searchQuery}%");
                    });
                });
            }

            // Filter by services if specified.
            // The booking requires the chosen partner to provide EVERY service in
            // the cart (BookingController rejects the booking otherwise), so a
            // partner matching only one of several services must not be offered
            // here - the customer would pick them and fail at checkout.
            foreach ($serviceIdArray as $requiredServiceId) {
                $query->whereHas('services', function ($q) use ($requiredServiceId) {
                    $q->where('services.service_id', $requiredServiceId);
                });
            }

            // Add distance calculation and filter if location provided
            if ($latitude && $longitude) {
                $query->join('addresses', function ($join) {
                    $join->on('partner_profiles.partner_id', '=', 'addresses.user_id')
                        ->where('addresses.is_default', true);
                })
                    ->addSelect([
                        'partner_profiles.*',
                        DB::raw("
                        (6371 * acos(cos(radians($latitude))
                        * cos(radians(COALESCE(addresses.latitude, 27.7172)))
                        * cos(radians(COALESCE(addresses.longitude, 85.3240)) - radians($longitude))
                        + sin(radians($latitude))
                        * sin(radians(COALESCE(addresses.latitude, 27.7172))))) AS distance
                    ")
                    ]);

                // Apply distance filter if specified using whereRaw instead of having
                if ($maxDistance) {
                    $query->whereRaw("
                        (6371 * acos(cos(radians($latitude))
                        * cos(radians(COALESCE(addresses.latitude, 27.7172)))
                        * cos(radians(COALESCE(addresses.longitude, 85.3240)) - radians($longitude))
                        + sin(radians($latitude))
                        * sin(radians(COALESCE(addresses.latitude, 27.7172))))) <= ?
                    ", [$maxDistance]);
                }
            }            // Apply sorting
            switch ($sortBy) {
                case 'rating':
                    $query->orderBy('rating_avg', $sortOrder)->orderByDesc('total_ratings');
                    break;
                case 'distance':
                    if ($latitude && $longitude) {
                        $query->orderBy('distance', $sortOrder);
                    } else {
                        $query->orderBy('rating_avg', $sortOrder);
                    }
                    break;
                case 'price':
                    // Order by base price for the first specified service
                    if ($serviceIds) {
                        $firstServiceId = explode(',', $serviceIds)[0];
                        $query->leftJoin('partner_skills', function ($join) use ($firstServiceId) {
                            $join->on('partner_profiles.partner_id', '=', 'partner_skills.partner_id')
                                ->where('partner_skills.service_id', $firstServiceId);
                        })->orderBy('partner_skills.base_price', $sortOrder);
                    } else {
                        $query->orderBy('rating_avg', $sortOrder);
                    }
                    break;
                case 'completion_rate':
                    // TODO: Implement completion rate sorting
                    $query->orderBy('rating_avg', $sortOrder);
                    break;
                default: // rating
                    $query->orderBy('rating_avg', $sortOrder)->orderByDesc('total_ratings');
                    break;
            }

            // Real slot filtering: when the customer sent a date/time, keep
            // only partners who are actually working and free for the full
            // service duration at that moment.
            // A date-only `scheduled_at` carries no time to test against, so
            // treat it as a day-level search instead of "midnight".
            $requestedStart = ($scheduledAt && str_contains($scheduledAt, ':'))
                ? $this->availability->parse($scheduledAt)
                : null;
            $duration = $this->availability->durationForServices($serviceIdArray);

            if ($requestedStart) {
                $matching = $query->get()
                    ->filter(fn ($partner) => $this->availability
                        ->check($partner->partner_id, $requestedStart, $duration)['available'])
                    ->values();

                $partners = new LengthAwarePaginator(
                    $matching->slice(max(0, ($page - 1) * $limit), $limit)->values(),
                    $matching->count(),
                    $limit,
                    $page,
                    ['path' => $request->url(), 'query' => $request->query()]
                );
            } else {
                $partners = $query->paginate($limit, ['*'], 'page', $page);
            }

            // Transform the data
            $firstServiceId = $serviceIdArray[0] ?? null;
            $transformedPartners = $partners->getCollection()->map(function ($partner) use ($firstServiceId, $requestedStart, $duration) {
                $status = $requestedStart
                    ? $this->availability->statusFor($partner->partner_id, $requestedStart, $duration)
                    : $this->availability->dayStatus($partner->partner_id, Carbon::today(), null, $duration);

                $data = [
                    'partner_id' => $partner->partner_id,
                    'user' => [
                        'first_name' => $partner->user->first_name,
                        'last_name' => $partner->user->last_name,
                        'full_name' => $partner->user->first_name . ' ' . $partner->user->last_name,
                        'phone' => $partner->user->phone,
                        'avatar_url' => $partner->user->avatar_url,
                    ],
                    'rating_avg' => (float) $partner->rating_avg,
                    'total_ratings' => $partner->total_ratings,
                    'services' => $partner->services->map(function ($service) {
                        return [
                            'service_id' => $service->service_id,
                            'name' => $service->name,
                        ];
                    }),
                    'base_price' => $this->getBasePrice($partner, $firstServiceId),
                    'distance' => isset($partner->distance) ? round($partner->distance, 1) : null,
                    'available_for_slot' => $requestedStart ? $status === 'available' || $status === 'limited' : null,
                    'availability_status' => $status,
                    'completion_rate' => $this->getCompletionRate($partner),
                    'response_time_avg' => $this->getResponseTime($partner),
                    'recent_reviews' => $partner->ratings->take(3)->map(function ($rating) {
                        return [
                            'rating_value' => $rating->rating_value,
                            'review_text' => $rating->review_text,
                            'created_at' => $rating->created_at->format('M d, Y'),
                        ];
                    }),
                ];

                return $data;
            });

            // Calculate filter statistics
            $filterStats = [
                'total_partners' => $partners->total(),
                'available_now' => $this->getAvailablePartnersCount(),
                'within_5km' => $latitude && $longitude ? $this->getPartnersWithinRadius($latitude, $longitude, 5) : null,
                'service_experts' => $firstServiceId ? $this->getServiceExpertsCount($firstServiceId) : null,
            ];

            return response()->json([
                'success' => true,
                'data' => $transformedPartners,
                'pagination' => [
                    'current_page' => $partners->currentPage(),
                    'last_page' => $partners->lastPage(),
                    'per_page' => $partners->perPage(),
                    'total' => $partners->total(),
                    'has_more' => $partners->hasMorePages(),
                ],
                'filters' => $filterStats,
                'message' => 'Partners retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Partner search failed', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search partners',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get partner availability for specific date
     */
    public function checkAvailability(Request $request, $partnerId): JsonResponse
    {
        try {
            $request->validate([
                'date' => 'required|date|after_or_equal:today',
                'service_id' => 'nullable|exists:services,service_id'
            ]);

            $partner = PartnerProfile::with('user')->findOrFail($partnerId);
            $date = Carbon::parse($request->input('date'))->startOfDay();
            $serviceId = $request->input('service_id');

            // Check if partner is active
            if ($partner->status !== 'active' || !$partner->user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partner is not available'
                ], 400);
            }

            $slots = $this->availability->slotsFor($partner->partner_id, $date, $serviceId ? (int) $serviceId : null);
            $availableSlots = collect($slots)->where('available', true)->values();

            $formattedSlots = $availableSlots->map(function ($slot) use ($date, $partnerId) {
                return [
                    'time' => $slot['time'],
                    'display_time' => $date->copy()->setTimeFromTimeString($slot['time'])->format('g:i A'),
                    'datetime' => $date->toDateString() . ' ' . $slot['time'] . ':00',
                    'partner_id' => $partnerId,
                ];
            })->all();

            return response()->json([
                'success' => true,
                'data' => [
                    'partner_id' => $partnerId,
                    'date' => $date->toDateString(),
                    'service_id' => $serviceId,
                    'duration_minutes' => $this->availability->durationForService($serviceId ? (int) $serviceId : null),
                    'available_slots' => $formattedSlots,
                    'all_slots' => $slots,
                    'total_slots' => count($slots),
                    'available_count' => count($formattedSlots),
                    'booking_percentage' => count($slots) > 0
                        ? round((count($slots) - count($formattedSlots)) / count($slots) * 100, 1)
                        : 100.0,
                ],
                'message' => 'Availability retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Partner availability check failed', [
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check availability'
            ], 500);
        }
    }

    /**
     * Get detailed partner profile
     */
    public function show(Request $request, $partnerId): JsonResponse
    {
        try {
            $partner = PartnerProfile::with([
                'user:user_id,first_name,last_name,phone,avatar_url,email',
                'services:service_id,name,description,image_url',
                'ratings.customer:user_id,first_name,last_name'
            ])->findOrFail($partnerId);

            // Check if partner is active
            if ($partner->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Partner profile not available'
                ], 404);
            }

            $profileData = [
                'partner_id' => $partner->partner_id,
                'user' => [
                    'first_name' => $partner->user->first_name,
                    'last_name' => $partner->user->last_name,
                    'full_name' => $partner->user->first_name . ' ' . $partner->user->last_name,
                    'phone' => $partner->user->phone,
                    'avatar_url' => $partner->user->avatar_url,
                ],
                'rating_avg' => (float) $partner->rating_avg,
                'total_ratings' => $partner->total_ratings,
                'services' => $partner->services,
                'completion_rate' => $this->getCompletionRate($partner),
                'response_time_avg' => $this->getResponseTime($partner),
                'total_jobs_completed' => $this->getTotalJobsCompleted($partner),
                'member_since' => $partner->created_at->format('M Y'),
                'recent_reviews' => $partner->ratings->take(10)->map(function ($rating) {
                    $customer = $rating->customer;

                    return [
                        'rating_value' => $rating->rating_value,
                        'review_text' => $rating->review_text,
                        'customer_name' => $customer
                            ? trim($customer->first_name . ' ' . substr((string) $customer->last_name, 0, 1) . '.')
                            : 'Customer',
                        'created_at' => $rating->created_at->format('M d, Y'),
                    ];
                }),
                'rating_distribution' => $this->getRatingDistribution($partner),
            ];

            return response()->json([
                'success' => true,
                'data' => $profileData,
                'message' => 'Partner profile retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Partner profile retrieval failed', [
                'partner_id' => $partnerId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve partner profile'
            ], 500);
        }
    }

    // Helper methods
    private function getBasePrice($partner, $serviceId = null)
    {
        if (!$serviceId) return null;

        $skill = DB::table('partner_skills')
            ->where('partner_id', $partner->partner_id)
            ->where('service_id', $serviceId)
            ->first();

        return $skill ? (float) $skill->base_price : null;
    }

    private function getCompletionRate($partner)
    {
        $totalJobs = DB::table('service_requests')
            ->where('partner_id', $partner->partner_id)
            ->whereIn('status', ['completed', 'cancelled'])
            ->count();

        if ($totalJobs === 0) return 100;

        $completedJobs = DB::table('service_requests')
            ->where('partner_id', $partner->partner_id)
            ->where('status', 'completed')
            ->count();

        return round(($completedJobs / $totalJobs) * 100, 1);
    }

    private function getResponseTime($partner)
    {
        // Placeholder - implement based on partner response time tracking
        return '15 minutes'; // Default response time
    }

    private function getTotalJobsCompleted($partner)
    {
        return DB::table('service_requests')
            ->where('partner_id', $partner->partner_id)
            ->where('status', 'completed')
            ->count();
    }

    private function getRatingDistribution($partner)
    {
        $distribution = DB::table('ratings')
            ->where('partner_id', $partner->partner_id)
            ->select('rating_value', DB::raw('count(*) as count'))
            ->groupBy('rating_value')
            ->get()
            ->keyBy('rating_value');

        $result = [];
        for ($i = 1; $i <= 5; $i++) {
            $result[$i] = $distribution->get($i)->count ?? 0;
        }

        return $result;
    }

    private function getAvailablePartnersCount()
    {
        return PartnerProfile::where('status', 'active')
            ->whereHas('user', function ($q) {
                $q->where('is_active', true);
            })
            ->get()
            ->filter(fn ($partner) => $this->availability
                ->dayStatus($partner->partner_id, Carbon::today()) !== 'unavailable')
            ->count();
    }

    private function getPartnersWithinRadius($lat, $lng, $radius)
    {
        return PartnerProfile::where('status', 'active')
            ->whereHas('user', function ($q) {
                $q->where('is_active', true);
            })
            ->join('addresses', function ($join) {
                $join->on('partner_profiles.partner_id', '=', 'addresses.user_id')
                    ->where('addresses.is_default', true);
            })
            ->whereRaw("
                (6371 * acos(cos(radians($lat))
                * cos(radians(COALESCE(addresses.latitude, 27.7172)))
                * cos(radians(COALESCE(addresses.longitude, 85.3240)) - radians($lng))
                + sin(radians($lat))
                * sin(radians(COALESCE(addresses.latitude, 27.7172))))) <= $radius
            ")
            ->count();
    }

    private function getServiceExpertsCount($serviceId)
    {
        return PartnerProfile::where('status', 'active')
            ->whereHas('services', function ($q) use ($serviceId) {
                $q->where('services.service_id', $serviceId);
            })
            ->count();
    }
}
