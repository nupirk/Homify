<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BannerController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\PartnerController;
use App\Http\Controllers\Api\V1\PartnerSearchController;
use App\Http\Controllers\Api\V1\ServiceController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\PartnerServiceController;
use App\Http\Controllers\Api\V1\EarningController;
use App\Http\Controllers\Api\V1\PartnerPayoutController;
use App\Http\Controllers\Api\V1\PaymentMethodController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\V1\PartnerApplicationController;
use App\Http\Controllers\Api\V1\OtpController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

Route::prefix('v1')->group(function () {

    // Public
    Route::Get('test', function () {
        return ['data' => 'Hello'];
    });

    // Deep health check for uptime monitors / deploy gates (no auth).
    Route::get('health', [\App\Http\Controllers\Api\V1\HealthController::class, 'index']);

    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('login',    [AuthController::class, 'login'])->middleware('throttle:auth');
    // Password recovery (public): phone + verified password_reset OTP + new password
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth');

    // OTP Verification (public - can be used before/after auth)
    Route::post('otp/send', [OtpController::class, 'sendOtp']);
    Route::post('otp/verify', [OtpController::class, 'verifyOtp']);
    Route::post('otp/resend', [OtpController::class, 'resendOtp']);

    // Public Categories and Services (no auth required)
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);
    Route::get('services',             [ServiceController::class, 'index']);
    Route::get('services/{service}',   [ServiceController::class, 'show']);
    Route::get('topRated', [PartnerController::class, 'topRated']);

    // Partner Application (no auth required for submission)
    Route::post('partner/apply', [PartnerApplicationController::class, 'submitApplication'])->middleware('throttle:partner-apply');

    // Analytics (public ingest — pre-login events; user attached only if token present)
    Route::post('analytics', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'store'])
        ->middleware('throttle:analytics');

    // Client error/crash ingest (public — the app can fail before login)
    Route::post('errors', [\App\Http\Controllers\Api\V1\ErrorReportController::class, 'store'])
        ->middleware('throttle:analytics');

    // Marketing CMS (customer feed). Public on purpose: banners and claimable
    // promo codes are what a signed-out visitor browses; inside auth:sanctum
    // they returned 401 and the app rendered no promotional surface at all.
    Route::get('banners', [BannerController::class, 'index']);
    Route::get('promotions/active', [\App\Http\Controllers\Api\V1\PromotionController::class, 'active']);

    // Document download route with access token authentication (no sanctum auth required)
    Route::get('service-completion/documents/{documentId}/{accessToken}', [BookingController::class, 'downloadDocument'])
        ->name('api.service-completion.download-document');

    // Auth-only
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', function (Request $r) {
            // Log request for debugging
            Log::info('User endpoint accessed', [
                'user_id' => $r->user()?->user_id,
                'has_auth_header' => $r->hasHeader('Authorization')
            ]);

            $user = $r->user();
            if ($user) {
                return response()->json([
                    'success' => true,
                    'user' => [
                        'id' => $user->user_id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'user_type' => $user->user_type,
                        'is_active' => $user->is_active,
                        'email_verified_at' => $user->email_verified_at,
                        'avatar_url' => $user->avatar_url,
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }
        });
        // Auth
        Route::post('logout', [AuthController::class, 'logout']);

        // Customer self-service
        Route::get('customer', [CustomerController::class, 'show']);
        Route::put('customer', [CustomerController::class, 'update']);
        Route::post('customer/avatar', [CustomerController::class, 'uploadAvatar']);
        Route::delete('customer/avatar', [CustomerController::class, 'removeAvatar']);
        Route::get('customer/avatar/{filename}', [CustomerController::class, 'getAvatar']);

        // User Addresses Management
        Route::apiResource('addresses', AddressController::class);
        Route::patch('addresses/{address}/set-default', [AddressController::class, 'setDefault']);
        Route::get('addresses/default', [AddressController::class, 'getDefault']);

        // Partner self-service
        Route::get('partner', [PartnerController::class, 'show']);
        Route::put('partner', [PartnerController::class, 'update']);

        // Partner live location heartbeat (active jobs only, app-gated)
        Route::post('partner/location', [\App\Http\Controllers\Api\V1\TrackingController::class, 'heartbeat']);

        // Partner working hours (drives availability for booking + assignment)
        Route::get('partner/availability', [\App\Http\Controllers\Api\V1\PartnerAvailabilityController::class, 'index']);
        Route::put('partner/availability', [\App\Http\Controllers\Api\V1\PartnerAvailabilityController::class, 'update']);

        // Partner KYC Document Management
        Route::prefix('partner/kyc')->group(function () {
            Route::get('documents', [\App\Http\Controllers\Api\V1\PartnerKycController::class, 'index']);
            Route::post('documents', [\App\Http\Controllers\Api\V1\PartnerKycController::class, 'store']);
            Route::get('documents/requirements', [\App\Http\Controllers\Api\V1\PartnerKycController::class, 'getRequiredDocuments']);
            Route::get('documents/{document}', [\App\Http\Controllers\Api\V1\PartnerKycController::class, 'show'])->name('partner.kyc.document');
            Route::delete('documents/{document}', [\App\Http\Controllers\Api\V1\PartnerKycController::class, 'destroy']);
        });

        // Partner Application Status (for users to check their application)
        Route::get('partner/application/status', [PartnerApplicationController::class, 'getApplicationStatus']);

        // Admin Partner Application Management
        Route::middleware('can:super-admin')->prefix('admin/partner-applications')->group(function () {
            Route::get('pending', [PartnerApplicationController::class, 'getPendingApplications']);
            Route::post('{applicationId}/approve', [PartnerApplicationController::class, 'approveApplication']);
            Route::post('{applicationId}/reject', [PartnerApplicationController::class, 'rejectApplication']);
        });

        // Admin KYC Document Management
        Route::middleware('can:super-admin')->prefix('admin/kyc')->group(function () {
            Route::get('documents/pending', [\App\Http\Controllers\Api\V1\PartnerKycController::class, 'getPendingDocuments']);
            Route::post('documents/{document}/approve', [\App\Http\Controllers\Api\V1\PartnerKycController::class, 'approveDocument']);
            Route::post('documents/{document}/reject', [\App\Http\Controllers\Api\V1\PartnerKycController::class, 'rejectDocument']);
            Route::get('documents/{document}/download', [\App\Http\Controllers\Api\V1\PartnerKycController::class, 'downloadDocument']);
        });

        // Admin-only categories and services management
        Route::apiResource('categories', CategoryController::class)->except(['index', 'show'])->middleware('can:super-admin');
        Route::apiResource('services',     ServiceController::class)->except(['index', 'show'])->middleware('can:super-admin');

        // Bookings
        Route::apiResource('bookings', BookingController::class)->only(['index', 'store']);
        Route::get('bookings/debug', [BookingController::class, 'debug']); // Debug endpoint
        Route::get('bookings/unpaid', [BookingController::class, 'unpaidBookings']); // Customer's unpaid online orders (Pay Now)
        Route::prefix('bookings')->group(function () {
            Route::post('retry-payment/{order}',     [BookingController::class, 'retryPayment'])->middleware('throttle:payments');
            Route::post('{order}/payment-failed',    [BookingController::class, 'markPaymentFailed'])->middleware('throttle:payments');
            Route::post('{booking}/confirm-cash',    [BookingController::class, 'confirmCash']); // customer confirms PAS cash declaration
            Route::post('{booking}/dispute-cash',    [BookingController::class, 'disputeCash']); // customer disputes PAS cash declaration
            Route::get('assigned',                   [BookingController::class, 'getAssignedJobs']); // partner
            Route::patch('{booking}/assign-partner', [BookingController::class, 'assignPartner'])->middleware('can:super-admin'); // super-admin
            Route::patch('{booking}/accept',         [BookingController::class, 'accept']);        // partner
            Route::patch('{booking}/reject',         [BookingController::class, 'reject']);        // partner
            Route::patch('{booking}/start-work',     [BookingController::class, 'startWork']);     // partner
            Route::patch('{booking}/complete',       [BookingController::class, 'complete']);      // partner
            Route::patch('{booking}/cancel',         [BookingController::class, 'cancel']);        // customer / super-admin
            Route::patch('{booking}/dismiss-payment', [BookingController::class, 'dismissFailedPayment']); // customer closes a failed attempt

            // New Partner Assignment Acceptance/Rejection Routes
            Route::patch('{order}/accept-assignment', [BookingController::class, 'acceptBooking']); // partner accepts direct assignment
            Route::patch('{order}/reject-assignment', [BookingController::class, 'rejectBooking']); // partner rejects direct assignment
            Route::patch('{order}/cancel-accepted-job', [BookingController::class, 'cancelAcceptedJob']); // partner cancels after acceptance
            Route::patch('{order}/reassign',          [BookingController::class, 'reassignBooking'])->middleware('can:super-admin'); // admin reassignment

            // Live tracking (customer polling: status + fresh partner coords + destination)
            Route::get('{booking}/tracking', [\App\Http\Controllers\Api\V1\TrackingController::class, 'show']);

            // In-booking chat (customer <-> assigned partner, polling transport)
            Route::get('chat/unread', [\App\Http\Controllers\Api\V1\BookingChatController::class, 'unreadCounts']); // per-order unread badge counts
            Route::get('{booking}/messages', [\App\Http\Controllers\Api\V1\BookingChatController::class, 'index']);
            Route::post('{booking}/messages', [\App\Http\Controllers\Api\V1\BookingChatController::class, 'store'])->middleware('throttle:chat');
            Route::post('{booking}/messages/read', [\App\Http\Controllers\Api\V1\BookingChatController::class, 'markRead']);
        });
        Route::get('bookings/{booking}', [BookingController::class, 'show']); // Customer booking details

        // Growth: promo codes + customer referral
        Route::post('promotions/validate', [\App\Http\Controllers\Api\V1\PromotionController::class, 'validatePromo'])
            ->middleware('throttle:20,1');

        Route::get('referral', [\App\Http\Controllers\Api\V1\ReferralController::class, 'index']);
        Route::post('referral/apply', [\App\Http\Controllers\Api\V1\ReferralController::class, 'apply'])
            ->middleware('throttle:10,1');

        // Membership (Homify Plus)
        Route::get('membership', [MembershipController::class, 'index']);
        Route::post('membership/purchase', [MembershipController::class, 'purchase'])
            ->middleware('throttle:payments');

        // Service Completion Routes - integrated into booking lifecycle
        Route::prefix('service-completion')->name('api.service-completion.')->group(function () {
            Route::post('{serviceRequestId}/upload-documents', [BookingController::class, 'uploadDocuments']);
            Route::patch('{serviceRequestId}/complete',       [BookingController::class, 'completeService']);
            Route::get('{serviceRequestId}/documents',        [BookingController::class, 'getDocuments']);
        });

        // Payments
        Route::prefix('payments')->group(function () {
            Route::post('initiate',               [PaymentController::class, 'initiate'])->middleware('throttle:payments');
            Route::post('retry',                  [PaymentController::class, 'retry'])->middleware('throttle:payments');
            Route::get('{payment}/status',        [PaymentController::class, 'status']);
            Route::post('{payment}/cancel',       [PaymentController::class, 'cancel'])->middleware('throttle:payments');
        });

        // Partner-service pivot
        Route::get('partner/services',        [PartnerServiceController::class, 'index']);
        Route::post('partner/services',       [PartnerServiceController::class, 'attach']);
        Route::delete('partner/services/{service}', [PartnerServiceController::class, 'detach']);

        // Earnings (partner only)
        Route::get('earnings', [EarningController::class, 'index']);
        Route::post('earnings/request-payout', [EarningController::class, 'requestPayout']); // DEPRECATED - returns 410 error

        // Partner Payouts (partner only) - MAIN PAYOUT SYSTEM
        Route::prefix('payouts')->group(function () {
            Route::get('/', [PartnerPayoutController::class, 'index']);
            Route::get('/balance', [PartnerPayoutController::class, 'getBalance']);
            Route::post('/request', [PartnerPayoutController::class, 'requestPayout']); // ACTIVE PAYOUT ENDPOINT
            Route::patch('/{payoutId}/cancel', [PartnerPayoutController::class, 'cancelPayout']);
        });

        // Payment Methods (user management)
        Route::prefix('payment-methods')->group(function () {
            Route::get('/', [PaymentMethodController::class, 'index']);                    // Get all payment methods
            Route::post('/', [PaymentMethodController::class, 'store']);                   // Add new payment method
            Route::put('/{id}', [PaymentMethodController::class, 'update']);               // Update payment method
            Route::patch('/{id}/default', [PaymentMethodController::class, 'setDefault']); // Set as default
            Route::delete('/{id}', [PaymentMethodController::class, 'destroy']);           // Delete payment method
        });

        // Reviews System
        Route::prefix('reviews')->group(function () {
            // Customer review management
            Route::get('my-reviews', [ReviewController::class, 'myReviews']);
            Route::get('booking/{bookingId}', [ReviewController::class, 'getBookingReview']); // Check if user has reviewed booking
            Route::post('orders/{orderId}', [ReviewController::class, 'store']); // Create review for order
            Route::get('{reviewId}', [ReviewController::class, 'show']);
            Route::put('{reviewId}', [ReviewController::class, 'update']);
            Route::delete('{reviewId}', [ReviewController::class, 'destroy']);

            // Partner review management
            Route::get('partner/received', [ReviewController::class, 'partnerReviews']);
            Route::post('{reviewId}/respond', [ReviewController::class, 'respondToReview']);

            // Public review endpoints
            Route::get('/', [ReviewController::class, 'index']); // Get reviews with filters
            Route::get('partner/{partnerId}/stats', [ReviewController::class, 'partnerRatingStats']);
        });

// Notifications
Route::get('notifications',                    [NotificationController::class, 'index']);
Route::get('notifications/unread-count',       [NotificationController::class, 'unreadCount']);
Route::patch('notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);
Route::patch('notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);

        // User Devices (for push notifications)
        Route::post('user/devices/register', [NotificationController::class, 'registerDevice']);

        // Partner Search (customer-facing)
        Route::prefix('partners')->group(function () {
            Route::get('search', [PartnerSearchController::class, 'search']);
            Route::get('{partnerId}', [PartnerSearchController::class, 'show']);
            Route::get('{partnerId}/availability', [PartnerSearchController::class, 'checkAvailability']);
        });
    });

    // Public webhook routes (no authentication required)
    Route::middleware('throttle:webhooks')->group(function () {
        Route::get('payment/callback/{gateway}', [PaymentWebhookController::class, 'callback'])->name('payment.callback');
        Route::post('webhooks/khalti/payment', [PaymentWebhookController::class, 'handleKhaltiWebhook']);
        Route::post('webhooks/esewa/payment', [PaymentWebhookController::class, 'handleEsewaWebhook']);
        Route::get('webhooks/esewa/success', [PaymentWebhookController::class, 'esewaSuccess']);
        Route::get('webhooks/esewa/failure', [PaymentWebhookController::class, 'esewaFailure']);
        Route::get('webhooks/membership/esewa/success', [PaymentWebhookController::class, 'membershipEsewaSuccess'])
            ->name('membership.webhook.success');
        Route::get('webhooks/membership/esewa/failure', [PaymentWebhookController::class, 'membershipEsewaFailure'])
            ->name('membership.webhook.failure');
        Route::post('webhooks/khalti/payout', [PaymentWebhookController::class, 'khaltiPayout']);
        Route::post('webhooks/esewa/payout', [PaymentWebhookController::class, 'esewaPayout']);
        Route::post('payment/timeout/{orderId}', [PaymentWebhookController::class, 'handlePaymentTimeout']);
    });

    // Admin routes (require super admin authentication)
    Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {

        // User management (JSON for in-app AdminDashboard)
        Route::get('users', [\App\Http\Controllers\SuperAdmin\DashboardController::class, 'users']);

        // Funnel analytics
        Route::get('analytics/funnel', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'funnel']);

        // Client error reports
        Route::get('errors', [\App\Http\Controllers\Api\V1\ErrorReportController::class, 'index']);

        // Notification Management
        Route::prefix('notifications')->group(function () {
            Route::get('dashboard', [\App\Http\Controllers\SuperAdmin\NotificationController::class, 'dashboard']);
            Route::get('analytics', [\App\Http\Controllers\SuperAdmin\NotificationController::class, 'getAnalytics']);

            // Templates
            Route::get('templates', [\App\Http\Controllers\SuperAdmin\NotificationController::class, 'getTemplates']);
            Route::post('templates', [\App\Http\Controllers\SuperAdmin\NotificationController::class, 'createTemplate']);
            Route::put('templates/{template}', [\App\Http\Controllers\SuperAdmin\NotificationController::class, 'updateTemplate']);
            Route::delete('templates/{template}', [\App\Http\Controllers\SuperAdmin\NotificationController::class, 'deleteTemplate']);

            // Campaigns
            Route::get('campaigns', [\App\Http\Controllers\SuperAdmin\NotificationController::class, 'getCampaigns']);
            Route::post('campaigns', [\App\Http\Controllers\SuperAdmin\NotificationController::class, 'createCampaign']);
            Route::get('campaigns/{campaign}', [\App\Http\Controllers\SuperAdmin\NotificationController::class, 'getCampaign']);

            // Quick notifications
            Route::post('send-quick', [\App\Http\Controllers\SuperAdmin\NotificationController::class, 'sendQuickNotification']);
            Route::post('send-negative-balance', [\App\Http\Controllers\SuperAdmin\NotificationController::class, 'notifyNegativeBalancePartners']);

            // Target estimation
            Route::post('target-counts', [\App\Http\Controllers\SuperAdmin\NotificationController::class, 'getTargetCounts']);
        });

        // Review Management
        Route::prefix('reviews')->group(function () {
            Route::get('dashboard', [\App\Http\Controllers\SuperAdmin\ReviewController::class, 'dashboard']);
            Route::get('analytics', [\App\Http\Controllers\SuperAdmin\ReviewController::class, 'getAnalytics']);
            Route::get('export', [\App\Http\Controllers\SuperAdmin\ReviewController::class, 'exportReviews']);

            // Reviews CRUD
            Route::get('/', [\App\Http\Controllers\SuperAdmin\ReviewController::class, 'getReviews']);
            Route::get('{review}', [\App\Http\Controllers\SuperAdmin\ReviewController::class, 'getReview']);
            Route::post('{review}/toggle-flag', [\App\Http\Controllers\SuperAdmin\ReviewController::class, 'toggleFlag']);
            Route::post('{review}/admin-note', [\App\Http\Controllers\SuperAdmin\ReviewController::class, 'addAdminNote']);
            Route::delete('{review}', [\App\Http\Controllers\SuperAdmin\ReviewController::class, 'deleteReview']);

            // Bulk actions
            Route::post('bulk-action', [\App\Http\Controllers\SuperAdmin\ReviewController::class, 'bulkAction']);

            // Partner insights
            Route::get('low-rated-partners', [\App\Http\Controllers\SuperAdmin\ReviewController::class, 'getLowRatedPartners']);

            // Legacy routes for backward compatibility
            Route::get('statistics', [\App\Http\Controllers\SuperAdmin\ReviewController::class, 'statistics']);
            Route::post('moderate', [\App\Http\Controllers\SuperAdmin\ReviewController::class, 'moderate']);
        });

        // Audit Logs
        Route::prefix('audit-logs')->group(function () {
            Route::get('/', [\App\Http\Controllers\SuperAdmin\AuditLogController::class, 'index']);
            Route::get('actions', [\App\Http\Controllers\SuperAdmin\AuditLogController::class, 'actions']);
            Route::get('{log}', [\App\Http\Controllers\SuperAdmin\AuditLogController::class, 'show']);
        });

        // Marketing CMS: banners CRUD (public feed: GET /banners above).
        // Super-admin gated the same way partner-applications are.
        Route::prefix('banners')->middleware('can:super-admin')->group(function () {
            Route::get('/', [BannerController::class, 'adminIndex']);            // GET    /admin/banners
            Route::post('/', [BannerController::class, 'store']);                // POST   /admin/banners (multipart image or JSON image_url)
            Route::put('/{banner}', [BannerController::class, 'update']);        // PUT    /admin/banners/{banner}
            Route::delete('/{banner}', [BannerController::class, 'destroy']);    // DELETE /admin/banners/{banner}
        });

        // Marketing CMS: promotions CRUD (public feed: GET /promotions/active above).
        // promo_type is derived server-side; validatePromo semantics are untouched.
        Route::prefix('promotions')->middleware('can:super-admin')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\PromotionController::class, 'adminIndex']);         // GET  /admin/promotions
            Route::post('/', [\App\Http\Controllers\Api\V1\PromotionController::class, 'store']);             // POST /admin/promotions
            Route::put('/{promotion}', [\App\Http\Controllers\Api\V1\PromotionController::class, 'update']);  // PUT  /admin/promotions/{promotion}
        });
    });
});
