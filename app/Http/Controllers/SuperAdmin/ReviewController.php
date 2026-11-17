<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Order;
use App\Models\User;
use App\Models\Service;
use App\Services\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    private ReviewService $reviewService;

    public function __construct(ReviewService $reviewService)
    {
        $this->reviewService = $reviewService;
    }

    /**
     * Dashboard - Get review overview
     */
    public function dashboard(): JsonResponse
    {
        $stats = [
            'total_reviews' => Review::count(),
            'avg_rating' => round(Review::avg('rating_value'), 2),
            'reviews_today' => Review::whereDate('created_at', today())->count(),
            'reviews_this_week' => Review::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'reviews_this_month' => Review::whereMonth('created_at', now()->month)->count(),
            'pending_responses' => Review::whereNull('partner_response')->count(),
            'reviews_with_media' => Review::whereHas('media')->count(),
            'flagged_reviews' => Review::where('is_flagged', true)->count(),
        ];

        $ratingDistribution = Review::selectRaw('rating_value, COUNT(*) as count')
            ->groupBy('rating_value')
            ->orderBy('rating_value')
            ->get()
            ->pluck('count', 'rating_value')
            ->toArray();

        $recentReviews = Review::with(['customer', 'partner', 'order.service', 'media'])
            ->latest()
            ->limit(10)
            ->get();

        $topRatedPartners = DB::table('reviews')
            ->select('partner_id', DB::raw('AVG(rating_value) as avg_rating'), DB::raw('COUNT(*) as review_count'))
            ->groupBy('partner_id')
            ->having('review_count', '>=', 5)
            ->orderByDesc('avg_rating')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                $partner = User::find($item->partner_id);
                return [
                    'partner' => $partner,
                    'avg_rating' => round($item->avg_rating, 2),
                    'review_count' => $item->review_count
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'rating_distribution' => $ratingDistribution,
                'recent_reviews' => $recentReviews,
                'top_rated_partners' => $topRatedPartners
            ]
        ]);
    }

    /**
     * Get all reviews with filtering and pagination
     */
    public function getReviews(Request $request): JsonResponse
    {
        $query = Review::with(['customer', 'partner', 'order.service', 'media']);

        // Filter by rating
        if ($request->filled('rating')) {
            $query->where('rating_value', $request->rating);
        }

        // Filter by rating range
        if ($request->filled('min_rating')) {
            $query->where('rating_value', '>=', $request->min_rating);
        }
        if ($request->filled('max_rating')) {
            $query->where('rating_value', '<=', $request->max_rating);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter by partner
        if ($request->filled('partner_id')) {
            $query->where('partner_id', $request->partner_id);
        }

        // Filter by service
        if ($request->filled('service_id')) {
            $query->whereHas('order', function ($q) use ($request) {
                $q->where('service_id', $request->service_id);
            });
        }

        // Filter by response status
        if ($request->filled('has_response')) {
            if ($request->boolean('has_response')) {
                $query->whereNotNull('partner_response');
            } else {
                $query->whereNull('partner_response');
            }
        }

        // Filter by media
        if ($request->filled('has_media')) {
            if ($request->boolean('has_media')) {
                $query->whereHas('media');
            } else {
                $query->whereDoesntHave('media');
            }
        }

        // Filter by flagged status
        if ($request->filled('is_flagged')) {
            $query->where('is_flagged', $request->boolean('is_flagged'));
        }

        // Search in review text
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('review_text', 'like', "%{$search}%")
                    ->orWhere('partner_response', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Sort options
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        if (in_array($sortBy, ['created_at', 'rating_value', 'updated_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $reviews = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $reviews
        ]);
    }

    /**
     * Display a listing of reviews (web view)
     */
    public function index(Request $request): View
    {
        $query = Review::with(['customer', 'partner.user', 'order.service', 'media']);

        // Filter by rating
        if ($request->filled('rating')) {
            $query->where('rating_value', $request->rating);
        }

        // Filter by response status
        if ($request->filled('has_response')) {
            if ($request->boolean('has_response')) {
                $query->whereNotNull('partner_response');
            } else {
                $query->whereNull('partner_response');
            }
        }

        // Search in review text or customer name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('review_text', 'like', "%{$search}%")
                    ->orWhere('partner_response', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Sort options
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = 'desc';

        if (in_array($sortBy, ['created_at', 'rating_value'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $reviews = $query->paginate(20);

        return view('admin.reviews.index', compact('reviews'));
    }

    /**
     * Get single review details
     */
    public function getReview(Review $review): JsonResponse
    {
        $review->load(['customer', 'partner', 'order.service', 'media']);

        return response()->json([
            'success' => true,
            'data' => $review
        ]);
    }

    /**
     * Display the specified review
     */
    public function show(Review $review): View
    {
        $review->load([
            'customer',
            'partner.user',
            'order.orderItems.service'
        ]);

        return view('admin.reviews.show', compact('review'));
    }

    /**
     * Flag/unflag a review
     */
    public function toggleFlag(Review $review): JsonResponse
    {
        $review->update([
            'is_flagged' => !$review->is_flagged,
            'flagged_at' => $review->is_flagged ? null : now(),
            'flagged_by' => $review->is_flagged ? null : request()->user()->user_id
        ]);

        return response()->json([
            'success' => true,
            'message' => $review->is_flagged ? 'Review flagged successfully' : 'Review unflagged successfully',
            'data' => $review->fresh()
        ]);
    }

    /**
     * Add admin note to review
     */
    public function addAdminNote(Request $request, Review $review): JsonResponse
    {
        $request->validate([
            'admin_note' => 'required|string|max:1000'
        ]);

        $review->update([
            'admin_note' => $request->admin_note,
            'admin_note_by' => request()->user()->user_id,
            'admin_note_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Admin note added successfully',
            'data' => $review->fresh()
        ]);
    }

    /**
     * Update partner response for the review.
     */
    public function updateResponse(Request $request, Review $review): JsonResponse
    {
        $validated = $request->validate([
            'partner_response' => 'required|string|max:1000',
        ]);

        $review->update([
            'partner_response' => $validated['partner_response'],
            'partner_responded_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Partner response updated successfully',
            'data' => [
                'partner_response' => $review->partner_response,
                'partner_responded_at' => $review->partner_responded_at->format('M j, Y g:i A'),
            ]
        ]);
    }

    /**
     * Delete a review (soft delete)
     */
    public function deleteReview(Review $review): JsonResponse
    {
        $review->update([
            'deleted_by' => request()->user()->user_id,
            'deleted_at' => now(),
            'deletion_reason' => 'Deleted by admin'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review deleted successfully'
        ]);
    }

    /**
     * Remove the specified review from storage.
     * Alias for deleteReview() for RESTful route compatibility.
     */
    public function destroy(Review $review): JsonResponse
    {
        return $this->deleteReview($review);
    }

    /**
     * Get review analytics
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $dateFrom = $request->input('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        $analytics = [
            // Daily review counts
            'daily_reviews' => Review::selectRaw('DATE(created_at) as date, COUNT(*) as count, AVG(rating) as avg_rating')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->groupBy('date')
                ->orderBy('date')
                ->get(),

            // Rating distribution
            'rating_distribution' => Review::selectRaw('rating, COUNT(*) as count')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->groupBy('rating')
                ->orderBy('rating')
                ->get(),

            // Service-wise ratings
            'service_ratings' => DB::table('reviews')
                ->join('orders', 'reviews.order_id', '=', 'orders.order_id')
                ->join('services', 'orders.service_id', '=', 'services.service_id')
                ->select(
                    'services.name as service_name',
                    DB::raw('AVG(reviews.rating) as avg_rating'),
                    DB::raw('COUNT(reviews.review_id) as review_count')
                )
                ->whereBetween('reviews.created_at', [$dateFrom, $dateTo])
                ->groupBy('services.service_id', 'services.name')
                ->having('review_count', '>=', 3)
                ->orderByDesc('avg_rating')
                ->get(),

            // Partner performance
            'partner_performance' => DB::table('reviews')
                ->join('users', 'reviews.partner_id', '=', 'users.user_id')
                ->select(
                    'users.name as partner_name',
                    'users.user_id as partner_id',
                    DB::raw('AVG(reviews.rating) as avg_rating'),
                    DB::raw('COUNT(reviews.review_id) as review_count')
                )
                ->whereBetween('reviews.created_at', [$dateFrom, $dateTo])
                ->groupBy('users.user_id', 'users.name')
                ->having('review_count', '>=', 3)
                ->orderByDesc('avg_rating')
                ->limit(20)
                ->get(),

            // Response rate
            'response_stats' => [
                'total_reviews' => Review::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
                'with_response' => Review::whereBetween('created_at', [$dateFrom, $dateTo])
                    ->whereNotNull('partner_response')
                    ->count(),
                'with_media' => Review::whereBetween('created_at', [$dateFrom, $dateTo])
                    ->whereHas('media')
                    ->count(),
                'flagged' => Review::whereBetween('created_at', [$dateFrom, $dateTo])
                    ->where('is_flagged', true)
                    ->count()
            ]
        ];

        // Calculate response rate percentage
        $analytics['response_stats']['response_rate'] = $analytics['response_stats']['total_reviews'] > 0
            ? round(($analytics['response_stats']['with_response'] / $analytics['response_stats']['total_reviews']) * 100, 2)
            : 0;

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Get review statistics for dashboard.
     * Alias for getAnalytics() for backward compatibility with existing routes.
     */
    public function statistics(): JsonResponse
    {
        return $this->getAnalytics(request());
    }

    /**
     * Get partners with low ratings for intervention
     */
    public function getLowRatedPartners(Request $request): JsonResponse
    {
        $threshold = $request->input('threshold', 3.0);
        $minReviews = $request->input('min_reviews', 5);

        $lowRatedPartners = DB::table('reviews')
            ->join('users', 'reviews.partner_id', '=', 'users.user_id')
            ->join('partner_profiles', 'users.user_id', '=', 'partner_profiles.user_id')
            ->select(
                'users.user_id',
                'users.name',
                'users.email',
                'users.phone',
                'partner_profiles.business_name',
                DB::raw('AVG(reviews.rating) as avg_rating'),
                DB::raw('COUNT(reviews.review_id) as review_count'),
                DB::raw('COUNT(CASE WHEN reviews.rating <= 2 THEN 1 END) as poor_reviews'),
                DB::raw('MAX(reviews.created_at) as last_review_date')
            )
            ->where('users.is_active', true)
            ->groupBy('users.user_id', 'users.name', 'users.email', 'users.phone', 'partner_profiles.business_name')
            ->having('review_count', '>=', $minReviews)
            ->having('avg_rating', '<', $threshold)
            ->orderBy('avg_rating')
            ->get()
            ->map(function ($partner) {
                $partner->avg_rating = round($partner->avg_rating, 2);
                return $partner;
            });

        return response()->json([
            'success' => true,
            'data' => [
                'threshold' => $threshold,
                'min_reviews' => $minReviews,
                'partners' => $lowRatedPartners
            ]
        ]);
    }

    /**
     * Bulk actions on reviews
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:flag,unflag,delete,add_note',
            'review_ids' => 'required|array',
            'review_ids.*' => 'exists:reviews,review_id',
            'note' => 'required_if:action,add_note|string|max:1000'
        ]);

        $reviewIds = $request->input('review_ids');
        $action = $request->input('action');
        $affectedCount = 0;

        switch ($action) {
            case 'flag':
                $affectedCount = Review::whereIn('review_id', $reviewIds)
                    ->update([
                        'is_flagged' => true,
                        'flagged_at' => now(),
                        'flagged_by' => request()->user()->user_id
                    ]);
                break;

            case 'unflag':
                $affectedCount = Review::whereIn('review_id', $reviewIds)
                    ->update([
                        'is_flagged' => false,
                        'flagged_at' => null,
                        'flagged_by' => null
                    ]);
                break;

            case 'delete':
                $affectedCount = Review::whereIn('review_id', $reviewIds)
                    ->update([
                        'deleted_by' => request()->user()->user_id,
                        'deleted_at' => now(),
                        'deletion_reason' => 'Bulk deleted by admin'
                    ]);
                break;

            case 'add_note':
                $affectedCount = Review::whereIn('review_id', $reviewIds)
                    ->update([
                        'admin_note' => $request->input('note'),
                        'admin_note_by' => request()->user()->user_id,
                        'admin_note_at' => now()
                    ]);
                break;
        }

        return response()->json([
            'success' => true,
            'message' => "Bulk action '{$action}' completed successfully",
            'data' => [
                'affected_count' => $affectedCount,
                'action' => $action
            ]
        ]);
    }

    /**
     * Moderate reviews (legacy method for backward compatibility)
     */
    public function moderate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'review_ids' => 'required|array',
            'review_ids.*' => 'exists:reviews,review_id',
            'action' => 'required|in:hide,show',
        ]);

        $count = Review::whereIn('review_id', $validated['review_ids'])
            ->update(['is_visible' => $validated['action'] === 'show']);

        return response()->json([
            'success' => true,
            'message' => ucfirst($validated['action']) . " {$count} reviews successfully",
        ]);
    }

    /**
     * Export reviews data
     */
    public function exportReviews(Request $request): JsonResponse
    {
        $query = Review::with(['customer', 'partner', 'order.service']);

        // Apply same filters as getReviews
        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $reviews = $query->orderBy('created_at', 'desc')->get();

        $exportData = $reviews->map(function ($review) {
            return [
                'review_id' => $review->review_id,
                'customer_name' => $review->customer->name ?? 'N/A',
                'customer_email' => $review->customer->email ?? 'N/A',
                'partner_name' => $review->partner->name ?? 'N/A',
                'service_name' => $review->order->service->name ?? 'N/A',
                'rating' => $review->rating,
                'review_text' => $review->review_text,
                'partner_response' => $review->partner_response,
                'has_media' => $review->media->count() > 0 ? 'Yes' : 'No',
                'is_flagged' => $review->is_flagged ? 'Yes' : 'No',
                'created_at' => $review->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $review->updated_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $exportData,
            'meta' => [
                'total_records' => $exportData->count(),
                'exported_at' => now()->format('Y-m-d H:i:s')
            ]
        ]);
    }
}
