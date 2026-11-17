<?php

namespace App\Services;

use App\Models\Review;
use App\Models\Order;
use App\Models\User;
use App\Models\PartnerProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReviewService
{
    /**
     * Create a new review with optional media
     */
    public function createReview(array $data, ?array $mediaFiles = null): Review
    {
        $reviewData = [
            'order_id' => $data['order_id'],
            'user_id' => $data['user_id'],
            'partner_id' => $data['partner_id'],
            'rating_value' => $data['rating_value'],
            'review_text' => $data['review_text'] ?? null,
        ];

        $review = Review::create($reviewData);

        // Handle media uploads
        if ($mediaFiles) {
            $this->handleMediaUploads($review, $mediaFiles);
        }

        // Update partner's rating statistics
        $this->updatePartnerRatingStats($review->partner_id);

        return $review->load(['customer', 'partner', 'order', 'media']);
    }

    /**
     * Update an existing review
     */
    public function updateReview(Review $review, array $data, ?array $mediaFiles = null): Review
    {
        $updateData = [
            'rating_value' => $data['rating_value'],
            'review_text' => $data['review_text'] ?? null,
        ];

        $review->update($updateData);

        // Handle new media uploads
        if ($mediaFiles) {
            $this->handleMediaUploads($review, $mediaFiles);
        }

        // Update partner's rating statistics
        $this->updatePartnerRatingStats($review->partner_id);

        return $review->load(['customer', 'partner', 'order', 'media']);
    }

    /**
     * Add partner response to review
     */
    public function addPartnerResponse(Review $review, string $response, int $partnerId): Review
    {
        // Verify the partner owns this review
        if ($review->partner_id !== $partnerId) {
            throw new \Exception('Unauthorized to respond to this review');
        }

        $review->update([
            'partner_response' => $response,
            'partner_responded_at' => now()
        ]);

        return $review->load(['customer', 'partner', 'order', 'media']);
    }

    /**
     * Get reviews for a partner with filtering and pagination
     */
    public function getPartnerReviews(int $partnerId, array $filters = [], int $perPage = 10)
    {
        $query = Review::where('partner_id', $partnerId)
            ->with(['customer', 'order', 'media']);

        // Apply filters
        if (isset($filters['rating'])) {
            $query->where('rating_value', $filters['rating']);
        }

        if (isset($filters['has_response'])) {
            if ($filters['has_response']) {
                $query->whereNotNull('partner_response');
            } else {
                $query->whereNull('partner_response');
            }
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get reviews by a customer
     */
    public function getCustomerReviews(int $userId, int $perPage = 10)
    {
        return Review::where('user_id', $userId)
            ->with(['partner', 'order', 'media'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get partner rating statistics
     */
    public function getPartnerRatingStats(int $partnerId): array
    {
        $reviews = Review::where('partner_id', $partnerId)->get();

        if ($reviews->isEmpty()) {
            return [
                'total_reviews' => 0,
                'average_rating' => 0,
                'rating_distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
                'response_rate' => 0
            ];
        }

        $totalReviews = $reviews->count();
        $averageRating = round($reviews->avg('rating_value'), 2);
        $responsesCount = $reviews->whereNotNull('partner_response')->count();
        $responseRate = round(($responsesCount / $totalReviews) * 100, 2);

        // Rating distribution
        $ratingDistribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $count = $reviews->where('rating_value', $i)->count();
            $ratingDistribution[$i] = [
                'count' => $count,
                'percentage' => $totalReviews > 0 ? round(($count / $totalReviews) * 100, 2) : 0
            ];
        }

        return [
            'total_reviews' => $totalReviews,
            'average_rating' => $averageRating,
            'rating_distribution' => $ratingDistribution,
            'response_rate' => $responseRate,
            'recent_reviews' => $reviews->take(5)->values()
        ];
    }

    /**
     * Handle media file uploads for reviews
     */
    private function handleMediaUploads(Review $review, array $mediaFiles): void
    {
        foreach ($mediaFiles as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $this->storeReviewMedia($review, $file);
            }
        }
    }

    /**
     * Store individual media file
     */
    private function storeReviewMedia(Review $review, UploadedFile $file): void
    {
        $extension = $file->getClientOriginalExtension();
        $filename = 'review_' . $review->rating_id . '_' . Str::random(10) . '.' . $extension;
        $path = $file->storeAs('reviews/' . $review->rating_id, $filename, 'public');

        // Determine media type
        $mimeType = $file->getMimeType();
        $mediaType = str_starts_with($mimeType, 'image/') ? 'image' : 'video';

        // Create media record (assuming you have a ReviewMedia model)
        $review->media()->create([
            'file_path' => $path,
            'file_name' => $filename,
            'file_size' => $file->getSize(),
            'mime_type' => $mimeType,
            'media_type' => $mediaType
        ]);
    }

    /**
     * Update partner's overall rating statistics
     */
    private function updatePartnerRatingStats(int $partnerId): void
    {
        $stats = $this->getPartnerRatingStats($partnerId);

        // Update partner profile with latest stats
        PartnerProfile::where('partner_id', $partnerId)->update([
            'total_reviews' => $stats['total_reviews'],
            'average_rating' => $stats['average_rating'],
            'rating_stats' => json_encode($stats['rating_distribution'])
        ]);
    }

    /**
     * Delete a review and its media
     */
    public function deleteReview(Review $review): bool
    {
        // Delete associated media files
        if ($review->media) {
            foreach ($review->media as $media) {
                Storage::disk('public')->delete($media->file_path);
                $media->delete();
            }
        }

        $partnerId = $review->partner_id;
        $deleted = $review->delete();

        if ($deleted) {
            // Update partner stats after deletion
            $this->updatePartnerRatingStats($partnerId);
        }

        return $deleted;
    }

    /**
     * Check if user can review an order
     */
    public function canReviewOrder(int $userId, string $orderId): bool
    {
        $order = Order::where('order_id', $orderId)
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->first();

        if (!$order) {
            return false;
        }

        // Check if already reviewed
        $existingReview = Review::where('order_id', $orderId)
            ->where('user_id', $userId)
            ->exists();

        return !$existingReview;
    }

    /**
     * Get review for a specific order
     */
    public function getOrderReview(string $orderId, int $userId): ?Review
    {
        return Review::where('order_id', $orderId)
            ->where('user_id', $userId)
            ->with(['partner', 'order', 'media'])
            ->first();
    }

    /**
     * Get trending/featured reviews
     */
    public function getFeaturedReviews(int $limit = 10): array
    {
        // Get high-rating reviews with responses and media
        return Review::where('rating_value', '>=', 4)
            ->whereNotNull('partner_response')
            ->whereHas('media')
            ->with(['customer', 'partner', 'order', 'media'])
            ->latest()
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Validate review data
     */
    public function validateReviewData(array $data): array
    {
        $errors = [];

        if (!isset($data['rating_value']) || $data['rating_value'] < 1 || $data['rating_value'] > 5) {
            $errors[] = 'Rating must be between 1 and 5';
        }

        if (isset($data['review_text']) && strlen($data['review_text']) > 1000) {
            $errors[] = 'Review text cannot exceed 1000 characters';
        }

        return $errors;
    }
}
