<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Booking;
use App\Models\PartnerProfile;
use App\Models\Review;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    /**
     * Recalculate a partner's average rating and rating count
     * so partner_profiles.rating_avg / total_ratings stay in sync.
     */
    private function syncPartnerRating($partnerId)
    {
        $stats = Review::where('partner_id', $partnerId)
            ->selectRaw('AVG(rating_value) as avg_rating, COUNT(*) as rating_count')
            ->first();

        PartnerProfile::where('partner_id', $partnerId)->update([
            'rating_avg' => $stats->avg_rating,
            'total_ratings' => $stats->rating_count,
        ]);
    }

    /**
     * Get reviews for a specific partner
     */
    public function index(Request $request)
    {
        $partnerId = $request->get('partner_id');
        $rating = $request->get('rating');
        $perPage = $request->get('per_page', 15);

        $query = Review::with(['customer', 'partner', 'order']);

        if ($partnerId) {
            $query->where('partner_id', $partnerId);
        }

        if ($rating) {
            $query->byRating($rating);
        }

        // Order by most recent first
        $query->orderBy('created_at', 'desc');

        $reviews = $query->paginate($perPage);

        return ReviewResource::collection($reviews);
    }

    /**
     * Store a new review (customer creates review after job completion)
     */
    public function store(Request $request, $orderId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'rating_value' => 'required|integer|between:1,5',
                'review_text' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find the order
            $order = Order::findOrFail($orderId);

            // Check if user is the customer for this order
            if ($order->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only review your own orders'
                ], 403);
            }

            // Check if order is completed
            if ($order->status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only review completed orders'
                ], 400);
            }

            // Check if review already exists
            $existingReview = Review::where('order_id', $orderId)->first();
            if ($existingReview) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reviewed this order'
                ], 400);
            }

            // Partner is stored on the order's service requests, not on the order itself
            $partnerId = $order->serviceRequests()
                ->whereNotNull('partner_id')
                ->value('partner_id');

            if (!$partnerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot review this order - no partner is assigned'
                ], 422);
            }

            // Create review
            $review = Review::create([
                'order_id' => $order->order_id,
                'user_id' => Auth::id(),
                'partner_id' => $partnerId,
                'rating_value' => $request->rating_value,
                'review_text' => $request->review_text,
            ]);

            $review->load(['customer', 'partner', 'order']);

            $this->syncPartnerRating($partnerId);

            return response()->json([
                'success' => true,
                'message' => 'Review submitted successfully',
                'data' => new ReviewResource($review)
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to submit review', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific review
     */
    public function show($reviewId)
    {
        try {
            $review = Review::with(['customer', 'partner', 'order'])->findOrFail($reviewId);

            return response()->json([
                'success' => true,
                'data' => new ReviewResource($review)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found'
            ], 404);
        }
    }

    /**
     * Update review (customer can edit their review)
     */
    public function update(Request $request, $reviewId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'rating_value' => 'required|integer|between:1,5',
                'review_text' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $review = Review::findOrFail($reviewId);

            // Check if user owns this review
            if ($review->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only edit your own reviews'
                ], 403);
            }

            // Update review
            $review->update([
                'rating_value' => $request->rating_value,
                'review_text' => $request->review_text,
            ]);

            $review->load(['customer', 'partner', 'order']);

            $this->syncPartnerRating($review->partner_id);

            return response()->json([
                'success' => true,
                'message' => 'Review updated successfully',
                'data' => new ReviewResource($review)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete review (customer can delete their review)
     */
    public function destroy($reviewId)
    {
        try {
            $review = Review::findOrFail($reviewId);

            // Check if user owns this review
            if ($review->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only delete your own reviews'
                ], 403);
            }

            $partnerId = $review->partner_id;
            $review->delete();

            $this->syncPartnerRating($partnerId);

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Partner responds to a review
     */
    public function respondToReview(Request $request, $reviewId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'partner_response' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $review = Review::findOrFail($reviewId);

            // Check if user is the partner for this review
            if ($review->partner_id !== Auth::user()->partnerProfile->partner_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only respond to reviews for your services'
                ], 403);
            }

            // Update review with partner response
            $review->update([
                'partner_response' => $request->partner_response,
                'partner_responded_at' => now(),
            ]);

            $review->load(['customer', 'partner', 'order']);

            return response()->json([
                'success' => true,
                'message' => 'Response added successfully',
                'data' => new ReviewResource($review)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add response',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get reviews for the current user (customer's reviews)
     */
    public function myReviews(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);

            $reviews = Review::with(['customer', 'partner', 'order'])
                ->where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return ReviewResource::collection($reviews);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch reviews',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's review for a specific order/booking
     * Note: This accepts order_id (which frontend may call as bookingId)
     */
    public function getBookingReview($orderId)
    {
        try {
            // Find the order to validate ownership
            $order = Order::findOrFail($orderId);

            // Check if order belongs to the authenticated user
            if ($order->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to order'
                ], 403);
            }

            // Find the review for this order by the current user
            $review = Review::with(['customer', 'partner'])
                ->where('user_id', Auth::id())
                ->where('order_id', $orderId)
                ->first();

            if (!$review) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'No review found for this order'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new ReviewResource($review)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch order review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get reviews for partner (partner's received reviews)
     */
    public function partnerReviews(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $partnerId = Auth::user()->partnerProfile->partner_id;

            $reviews = Review::with(['customer', 'partner', 'order'])
                ->where('partner_id', $partnerId)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // The partner app reads `response.success` + `response.data.data`,
            // so the paginator is wrapped instead of returned raw (a bare
            // resource collection has no `success` flag and always looked like
            // a failure).
            $payload = ReviewResource::collection($reviews)->response()->getData(true);

            return response()->json([
                'success' => true,
                'data' => $payload,
                'message' => 'Reviews retrieved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch reviews',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get partner rating statistics
     */
    public function partnerRatingStats($partnerId)
    {
        try {
            $stats = Review::where('partner_id', $partnerId)
                ->select(
                    DB::raw('AVG(rating_value) as average_rating'),
                    DB::raw('COUNT(*) as total_reviews'),
                    DB::raw('SUM(CASE WHEN rating_value = 5 THEN 1 ELSE 0 END) as five_star'),
                    DB::raw('SUM(CASE WHEN rating_value = 4 THEN 1 ELSE 0 END) as four_star'),
                    DB::raw('SUM(CASE WHEN rating_value = 3 THEN 1 ELSE 0 END) as three_star'),
                    DB::raw('SUM(CASE WHEN rating_value = 2 THEN 1 ELSE 0 END) as two_star'),
                    DB::raw('SUM(CASE WHEN rating_value = 1 THEN 1 ELSE 0 END) as one_star')
                )
                ->first();

            // Calculate percentages
            $totalReviews = $stats->total_reviews ?: 1; // Avoid division by zero

            return response()->json([
                'success' => true,
                'data' => [
                    'average_rating' => round($stats->average_rating, 2),
                    'total_reviews' => $stats->total_reviews,
                    'rating_distribution' => [
                        5 => [
                            'count' => $stats->five_star,
                            'percentage' => round(($stats->five_star / $totalReviews) * 100, 1)
                        ],
                        4 => [
                            'count' => $stats->four_star,
                            'percentage' => round(($stats->four_star / $totalReviews) * 100, 1)
                        ],
                        3 => [
                            'count' => $stats->three_star,
                            'percentage' => round(($stats->three_star / $totalReviews) * 100, 1)
                        ],
                        2 => [
                            'count' => $stats->two_star,
                            'percentage' => round(($stats->two_star / $totalReviews) * 100, 1)
                        ],
                        1 => [
                            'count' => $stats->one_star,
                            'percentage' => round(($stats->one_star / $totalReviews) * 100, 1)
                        ]
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rating statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
