<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SuperAdmin\OrderController;
use App\Http\Controllers\SuperAdmin\ClientController;
use App\Http\Controllers\SuperAdmin\LedgerController;
use App\Http\Controllers\SuperAdmin\ReviewController;
use App\Http\Controllers\SuperAdmin\EarningController;
use App\Http\Controllers\SuperAdmin\PartnerController;
use App\Http\Controllers\SuperAdmin\ServiceController;
use App\Http\Controllers\SuperAdmin\CategoryController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\NotificationController;
use App\Http\Controllers\SuperAdmin\PromotionController;
use App\Http\Controllers\SuperAdmin\ReportController;
use App\Http\Controllers\SuperAdmin\BannerController;
use App\Http\Controllers\SuperAdmin\PartnerApplicationController;
use App\Http\Controllers\SuperAdmin\ServiceRequestController;
use App\Http\Controllers\SuperAdmin\ErrorReportController;
use App\Http\Controllers\SuperAdmin\LoginActivityController;
use App\Http\Controllers\Admin\AuthController;

Route::get('/', function () {
    return view('landing');
});

// Marketing site SEO + legal pages (hosted per Play Store listing requirements)
Route::get('/privacy', function () {
    return view('legal.privacy');
})->name('legal.privacy');
Route::get('/terms', function () {
    return view('legal.terms');
})->name('legal.terms');

// Admin Authentication Routes
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.login');
    });
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    // Throttled: the admin login is a password-only web form and was the one
    // unguarded brute-force surface left in the app.
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.post');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// Protected Admin routes
Route::prefix('admin')->name('admin.')->middleware(['admin.auth'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Layout Test (for debugging)
    //Partner Management
    Route::get('partners-statistics', [PartnerController::class, 'statistics'])->name('partners.statistics');
    Route::resource('partners', PartnerController::class);
    Route::post('partners/{partner}/toggle-status', [PartnerController::class, 'toggleStatus'])->name('partners.toggle-status');
    Route::post('partners/{partner}/update-verification', [PartnerController::class, 'updateVerification'])->name('partners.update-verification');

    // Partner Applications Management
    Route::get('partner-applications', [PartnerApplicationController::class, 'index'])->name('partner-applications.index');
    Route::get('partner-applications/{application}', [PartnerApplicationController::class, 'show'])->name('partner-applications.show');
    Route::get('partner-applications/{application}/review', [PartnerApplicationController::class, 'review'])->name('partner-applications.review');
    Route::patch('partner-applications/{application}/status', [PartnerApplicationController::class, 'updateStatus'])->name('partner-applications.update-status');
    Route::post('partner-applications/{application}/convert', [PartnerApplicationController::class, 'convertToPartner'])->name('partner-applications.convert');
    Route::get('partner-applications/{application}/document/{document}', [PartnerApplicationController::class, 'downloadDocument'])->name('partner-applications.download-document');
    Route::delete('partner-applications/{application}', [PartnerApplicationController::class, 'destroy'])->name('partner-applications.destroy');
    Route::post('partner-applications/bulk-action', [PartnerApplicationController::class, 'bulkAction'])->name('partner-applications.bulk-action');

    // Client/User Management
    Route::get('clients-statistics', [ClientController::class, 'statistics'])->name('clients.statistics');
    Route::resource('clients', ClientController::class)->except(['create', 'store']);
    Route::post('clients/{client}/toggle-status', [ClientController::class, 'toggleStatus'])->name('clients.toggle-status');
    Route::post('clients/{client}/verify-email', [ClientController::class, 'verifyEmail'])->name('clients.verify-email');

    // Categories Management
    Route::get('categories-statistics', [CategoryController::class, 'statistics'])->name('categories.statistics');
    Route::resource('categories', CategoryController::class);
    Route::post('categories/{category}/toggle-status', [CategoryController::class, 'toggleStatus'])->name('categories.toggle-status');
    Route::post('categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');

    // Services Management
    Route::resource('services', ServiceController::class);
    Route::post('services/{service}/toggle-status', [ServiceController::class, 'toggleStatus'])->name('services.toggle-status');

    // Orders Management (replaces Bookings)
    Route::get('orders-statistics', [OrderController::class, 'statistics'])->name('orders.statistics');
    Route::get('orders/export', [OrderController::class, 'export'])->name('orders.export');
    Route::resource('orders', OrderController::class);
    Route::get('orders/{order}/suitable-partners', [OrderController::class, 'getSuitablePartners'])->name('orders.suitable-partners');
    Route::post('orders/{order}/assign-partner', [OrderController::class, 'assignPartner'])->name('orders.assign-partner');
    Route::post('orders/{order}/update-status', [OrderController::class, 'updateStatus'])->name('orders.update-status-individual');
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('orders/{order}/complete', [OrderController::class, 'complete'])->name('orders.complete');
    Route::post('orders/update-status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
    Route::post('orders/bulk-update', [OrderController::class, 'bulkUpdateStatus'])->name('orders.bulk-update');

    // Order completion documents
    Route::get('orders/documents/{documentId}/download', [OrderController::class, 'downloadCompletionDocument'])->name('orders.download-document');
    Route::get('service-requests/{serviceRequestId}/documents', [OrderController::class, 'getCompletionDocuments'])->name('service-requests.documents');
    Route::post('documents/{documentId}/verify', [OrderController::class, 'verifyDocument'])->name('documents.verify');

    // Service Requests Management
    Route::patch('service-requests/{request_id}/assign', [ServiceRequestController::class, 'assign'])->name('service-requests.assign');
    Route::get('service-requests/{service_id}/partners', [ServiceRequestController::class, 'getAvailablePartners'])->name('service-requests.partners');

    // Reviews Management
    Route::resource('reviews', ReviewController::class)->only(['index', 'show', 'destroy']);
    Route::get('reviews-statistics', [ReviewController::class, 'statistics'])->name('reviews.statistics');
    Route::post('reviews/{review}/update-response', [ReviewController::class, 'updateResponse'])->name('reviews.update-response');
    Route::post('reviews/moderate', [ReviewController::class, 'moderate'])->name('reviews.moderate');

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('revenue', [ReportController::class, 'revenue'])->name('revenue');
        Route::get('revenue/export', [ReportController::class, 'revenueCsv'])->name('revenue.export');
        Route::get('delivery', [ReportController::class, 'delivery'])->name('delivery');
        Route::get('delivery/export', [ReportController::class, 'deliveryCsv'])->name('delivery.export');
    });

    // Notifications Management
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');

        // Templates
        Route::prefix('templates')->name('templates.')->group(function () {
            Route::get('/create', [NotificationController::class, 'showCreateTemplate'])->name('create');
            Route::post('/create', [NotificationController::class, 'storeTemplate'])->name('store');
            Route::get('/{template}/edit', [NotificationController::class, 'showEditTemplate'])->name('edit');
            Route::put('/{template}', [NotificationController::class, 'updateTemplate'])->name('update');
            Route::delete('/{template}', [NotificationController::class, 'deleteTemplate'])->name('delete');
        });

        // Campaigns
        Route::prefix('campaigns')->name('campaigns.')->group(function () {
            Route::get('/', [NotificationController::class, 'campaignsIndex'])->name('index');
            Route::get('/create', [NotificationController::class, 'showCreateCampaign'])->name('create');
            Route::post('/create', [NotificationController::class, 'storeCampaign'])->name('store');
            Route::get('/{campaign}', [NotificationController::class, 'showCampaign'])->name('show');
        });
    });

    // Marketing: discount offers (promo codes)
    Route::prefix('promotions')->name('promotions.')->group(function () {
        Route::get('/', [PromotionController::class, 'index'])->name('index');
        Route::post('/', [PromotionController::class, 'store'])->name('store');
        Route::put('/{promotion}', [PromotionController::class, 'update'])->name('update');
        Route::post('/{promotion}/toggle', [PromotionController::class, 'toggle'])->name('toggle');
    });

    // Marketing: app banners shown in the customer app
    Route::prefix('banners')->name('banners.')->group(function () {
        Route::get('/', [BannerController::class, 'index'])->name('index');
        Route::get('/create', [BannerController::class, 'create'])->name('create');
        Route::post('/', [BannerController::class, 'store'])->name('store');
        Route::get('/{banner}/edit', [BannerController::class, 'edit'])->name('edit');
        Route::put('/{banner}', [BannerController::class, 'update'])->name('update');
        Route::post('/{banner}/toggle', [BannerController::class, 'toggle'])->name('toggle');
        Route::delete('/{banner}', [BannerController::class, 'destroy'])->name('destroy');
    });

    // Earnings Management
    Route::prefix('earnings')->name('earnings.')->group(function () {
        Route::get('/', [EarningController::class, 'index'])->name('index');

        // Commission rules (must come before /{earning_id} route)
        Route::get('/commission-rules', [EarningController::class, 'commissionRules'])->name('commission-rules');
        Route::get('/commission-rules/create', [EarningController::class, 'createCommissionRule'])->name('commission-rules.create');
        Route::post('/commission-rules', [EarningController::class, 'storeCommissionRule'])->name('commission-rules.store');
        Route::get('/commission-rules/{rule_id}/edit', [EarningController::class, 'editCommissionRule'])->name('commission-rules.edit');
        Route::put('/commission-rules/{rule_id}', [EarningController::class, 'updateCommissionRule'])->name('commission-rules.update');
        Route::delete('/commission-rules/{rule_id}', [EarningController::class, 'destroyCommissionRule'])->name('commission-rules.destroy');

        // Balances (must come before /{earning_id} route)
        Route::get('/balances', [EarningController::class, 'balances'])->name('balances');
        Route::post('/balances/{partner_id}/recalculate', [EarningController::class, 'recalculateBalance'])->name('balances.recalculate');
        Route::post('/balances/recalculate-all', [EarningController::class, 'recalculateAllBalances'])->name('balances.recalculate-all');

        // Partner earnings (must come before /{earning_id} route)
        Route::get('/partner/{partner_id}', [EarningController::class, 'partnerEarnings'])->name('partner');

        // Bulk operations (must come before /{earning_id} route)
        Route::post('/bulk-recalculate', [EarningController::class, 'bulkRecalculate'])->name('bulk-recalculate');

        // Individual earning routes (must come after all specific routes)
        Route::get('/{earning_id}', [EarningController::class, 'show'])->name('show');
    });

    // Ledger Management
    Route::prefix('ledger')->name('ledger.')->group(function () {
        Route::get('/', [LedgerController::class, 'index'])->name('index');
        Route::get('/analytics', [LedgerController::class, 'analytics'])->name('analytics');
        Route::get('/reconciliation', [LedgerController::class, 'reconciliation'])->name('reconciliation');
        Route::get('/show/{entry}', [LedgerController::class, 'show'])->name('show');
        Route::get('/balances', [LedgerController::class, 'userBalance'])->name('balances');
        Route::get('/partner-balance-report', [LedgerController::class, 'partnerBalanceReport'])->name('partner-balance-report');
        Route::get('/partner-payouts', [LedgerController::class, 'partnerPayouts'])->name('partner-payouts');
        Route::post('/partner-payouts/process', [LedgerController::class, 'processPartnerPayout'])->name('partner-payouts.process');
        Route::post('/partner-payouts/complete', [LedgerController::class, 'completePartnerPayout'])->name('partner-payouts.complete');
        Route::post('/partner-payouts/fail', [LedgerController::class, 'failPartnerPayout'])->name('partner-payouts.fail');

        Route::post('/partner-balances/reconcile', [LedgerController::class, 'reconcilePartnerBalances'])->name('partner-balances.reconcile');
        Route::get('/partner-payouts/{partner_id}', [LedgerController::class, 'partnerPayoutDetails'])->name('partner-payouts.details');
        Route::get('/order/{booking_id}', [LedgerController::class, 'orderLedger'])->name('order');
        Route::get('/user/{user_id}', [LedgerController::class, 'userLedger'])->name('user');
        Route::get('/export', [LedgerController::class, 'export'])->name('export');

        // Partner Statement Routes
        Route::get('/partner/{partner_id}/statement-form', [LedgerController::class, 'showStatementForm'])->name('partner.statement-form');
        Route::post('/partner/{partner_id}/statement', [LedgerController::class, 'generatePartnerStatement'])->name('partner.statement');
        Route::get('/partner/{partner_id}/summary', [LedgerController::class, 'generateSummaryStatement'])->name('partner.summary');
    });

    // Error Reporting Portal
    // Client crash reports captured by POST /api/v1/errors, plus server-side
    // 5xx failures mirrored there by the RecordServerErrors middleware.
    Route::get('error-reports', [ErrorReportController::class, 'index'])->name('error-reports.index');
    Route::get('error-reports/{errorReport}', [ErrorReportController::class, 'show'])->name('error-reports.show');

    // Admin login activity (audit trail written by Admin\AuthController).
    Route::get('login-activity', [LoginActivityController::class, 'index'])->name('login-activity.index');
});

// eSewa payout return URLs.
// eSewa bounces the admin's browser back here without the admin session, so
// these cannot live inside the `admin.auth` group (that bounced every return to
// the login screen and the payout stayed stuck in `processing`). Each handler
// verifies the eSewa HMAC signature itself and never auto-finalizes a payout.
Route::prefix('admin/ledger/payout')->name('admin.ledger.payout.')->group(function () {
    Route::get('/success', [LedgerController::class, 'handleEsewaPayoutCallback'])->name('callback');
    Route::get('/failed', [LedgerController::class, 'handleEsewaPayoutFailure'])->name('failed');
});
