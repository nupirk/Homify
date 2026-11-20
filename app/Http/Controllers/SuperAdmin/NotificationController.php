<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Models\NotificationCampaign;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\PartnerProfile;
use App\Models\PartnerBalance;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    private PushNotificationService $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }

    /**
     * Display the notifications management page
     */
    public function index(): View
    {
        $templates = NotificationTemplate::orderBy('created_at', 'desc')->get();
        $totalTemplates = $templates->count();
        $activeTemplates = $templates->where('is_active', true)->count();

        // Calculate campaign statistics
        $activeCampaigns = NotificationCampaign::whereIn('status', ['pending', 'active', 'scheduled', 'sending'])
            ->count();

        $sentToday = NotificationCampaign::whereDate('created_at', today())
            ->whereIn('status', ['sent', 'completed'])
            ->count();

        $failedToday = NotificationCampaign::whereDate('created_at', today())
            ->where('status', 'failed')
            ->count();

        return view('admin.notifications.index', compact(
            'templates',
            'totalTemplates',
            'activeTemplates',
            'activeCampaigns',
            'sentToday',
            'failedToday'
        ));
    }

    /**
     * Display the notification campaigns list.
     */
    public function campaignsIndex(): View
    {
        $campaigns = NotificationCampaign::with(['template', 'creator'])
            ->latest()
            ->paginate(20);

        return view('admin.notifications.campaigns.index', compact('campaigns'));
    }

    /**
     * Show the form for creating a new template
     */
    public function showCreateTemplate(): View
    {
        return view('admin.notifications.templates.create');
    }

    /**
     * Show the form for editing a template
     */
    public function showEditTemplate(NotificationTemplate $template): View
    {
        return view('admin.notifications.templates.edit', compact('template'));
    }

    /**
     * Store a newly created template via web form
     */
    public function storeTemplate(Request $request)
    {
        try {
            Log::info('NotificationController::storeTemplate (Web) started', [
                'request_data' => $request->all(),
                'user_id' => Auth::user()?->user_id ?? 'null',
            ]);

            $validatedData = $request->validate([
                'name' => 'required|string|max:100|unique:notification_templates',
                'type' => 'required|in:booking_confirmed,booking_updated,booking_cancelled,partner_assigned,partner_on_way,service_started,service_completed,payment_due,payment_received,review_request,promotional,system_update,reminder,custom',
                'delivery_method' => 'nullable|in:push,email,sms,in_app,multiple',
                'title' => 'required|string|max:200',
                'content' => 'required|string',
                'subject' => 'nullable|string|max:200',
                'email_template' => 'nullable|string',
                'action_text' => 'nullable|string|max:100',
                'action_url' => 'nullable|url',
                'category' => 'nullable|string|in:booking,payment,marketing,system,reminder',
                'priority' => 'nullable|string|in:low,normal,high,urgent',
                'description' => 'nullable|string|max:500',
            ]);

            // Check if user is authenticated
            if (!Auth::check()) {
                return redirect()->route('admin.login')->with('error', 'Please log in to continue.');
            }

            // Map frontend field names to backend field names
            $templateData = [
                'name' => $validatedData['name'],
                'type' => $validatedData['type'],
                'title_template' => $validatedData['title'],
                'message_template' => $validatedData['content'],
                'variables' => [
                    'customer_name' => 'Customer full name',
                    'first_name' => 'Customer first name',
                    'last_name' => 'Customer last name',
                    'partner_name' => 'Partner name',
                    'service_name' => 'Service name',
                    'order_id' => 'Order/Booking ID',
                    'amount' => 'Payment amount',
                    'date' => 'Current date',
                    'time' => 'Current time',
                ],
                'is_active' => $request->has('is_active'),
                'created_by' => Auth::user()->user_id,
            ];

            // Add optional fields if provided
            if (isset($validatedData['delivery_method'])) {
                $templateData['delivery_method'] = $validatedData['delivery_method'];
            }
            if (isset($validatedData['priority'])) {
                $templateData['priority'] = $validatedData['priority'];
            }

            // Add optional fields if provided
            if (isset($validatedData['subject'])) {
                $templateData['subject'] = $validatedData['subject'];
            }
            if (isset($validatedData['email_template'])) {
                $templateData['email_template'] = $validatedData['email_template'];
            }
            if (isset($validatedData['action_text'])) {
                $templateData['action_text'] = $validatedData['action_text'];
            }
            if (isset($validatedData['action_url'])) {
                $templateData['action_url'] = $validatedData['action_url'];
            }

            Log::info('Creating template with data', $templateData);

            $template = NotificationTemplate::create($templateData);

            Log::info('Template created successfully', [
                'template_id' => $template->template_id,
                'name' => $template->name
            ]);

            return redirect()->route('admin.notifications.index')
                ->with('success', 'Notification template created successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);

            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('NotificationController::storeTemplate (Web) failed', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return back()->with('error', 'An error occurred while creating the template: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Show the form for creating a new campaign
     */
    public function showCreateCampaign(): View
    {
        $templates = NotificationTemplate::where('is_active', true)
            ->orderBy('name')
            ->get();

        // Calculate recipient counts for all target types
        $recipientCounts = [
            'all_users' => User::count(),
            'all_partners' => User::where('user_type', 'partner')->count(),
            'active_partners' => User::where('user_type', 'partner')
                ->where('is_active', true)
                ->whereHas('partnerProfile', function ($query) {
                    $query->where('status', 'active');
                })
                ->count(),
            'inactive_partners' => User::where('user_type', 'partner')
                ->where(function ($query) {
                    $query->where('is_active', false)
                        ->orWhereHas('partnerProfile', function ($subQuery) {
                            $subQuery->where('status', '!=', 'active');
                        })
                        ->orWhereDoesntHave('partnerProfile');
                })
                ->count(),
            'verified_partners' => User::where('user_type', 'partner')
                ->whereHas('partnerProfile', function ($query) {
                    $query->whereNotNull('kyc_verified_at');
                })
                ->count(),
            'negative_balance_partners' => User::where('user_type', 'partner')
                ->whereHas('partnerBalance', function ($query) {
                    $query->where('available_balance', '<', 0);
                })
                ->count(),
            'high_earning_partners' => User::where('user_type', 'partner')
                ->whereHas('partnerBalance', function ($query) {
                    $query->where('total_earnings', '>', 10000);
                })
                ->count(),
            'new_partners' => User::where('user_type', 'partner')
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),
            'active_customers' => User::where('user_type', 'customer')
                ->where('is_active', true)
                ->whereHas('orders', function ($query) {
                    $query->where('created_at', '>=', now()->subDays(90));
                })
                ->count(),
            'inactive_customers' => User::where('user_type', 'customer')
                ->where(function ($query) {
                    $query->where('is_active', false)
                        ->orWhereDoesntHave('orders', function ($subQuery) {
                            $subQuery->where('created_at', '>=', now()->subDays(90));
                        });
                })
                ->count(),
        ];
        return view('admin.notifications.campaigns.create', compact('templates', 'recipientCounts'));
    }

    /**
     * Store a newly created campaign via web form
     */
    public function storeCampaign(Request $request)
    {
        try {
            // Check if user is authenticated
            if (!Auth::check()) {
                return redirect()->route('admin.login')->with('error', 'Please log in to continue.');
            }

            // The web form posts a date and a time separately, but the campaign
            // stores one scheduled_at. Without this the request had no
            // scheduled_at at all, so "Schedule for Later" was silently saved
            // as an immediate campaign.
            if ($request->input('schedule_type') === 'scheduled') {
                $date = $request->input('scheduled_date');
                $time = $request->input('scheduled_time');
                if ($date && $time) {
                    $request->merge(['scheduled_at' => trim($date . ' ' . $time)]);
                }
            }

            $validatedData = $request->validate([
                'name' => 'required|string|max:100',
                'template_id' => 'required|exists:notification_templates,template_id',
                'target_type' => 'required|in:all_users,all_partners,active_partners,inactive_partners,verified_partners,negative_balance_partners,high_earning_partners,new_partners,active_customers,inactive_customers,custom,specific_users',
                'target_users' => 'nullable|array',
                'target_users.*' => 'exists:users,user_id',
                'user_types' => 'nullable|array',
                'user_types.*' => 'in:customers,partners',
                'partner_status' => 'nullable|array',
                'partner_status.*' => 'in:active,inactive,verified',
                'min_balance' => 'nullable|numeric',
                'max_balance' => 'nullable|numeric',
                'description' => 'nullable|string|max:500',
                'schedule_type' => 'required|in:immediate,scheduled',
                'scheduled_at' => 'nullable|date|after:now',
            ]);

            $campaignData = [
                'name' => $validatedData['name'],
                'template_id' => $validatedData['template_id'],
                'target_type' => $validatedData['target_type'],
                'target_criteria' => [
                    'target_users' => $validatedData['target_users'] ?? null,
                    'user_types' => $request->input('user_types', []),
                    'partner_status' => $request->input('partner_status', []),
                    'min_balance' => $request->input('min_balance'),
                    'max_balance' => $request->input('max_balance'),
                ],
                'status' => $validatedData['schedule_type'] === 'immediate' ? 'pending' : 'scheduled',
                'scheduled_at' => $validatedData['scheduled_at'] ?? null,
                'created_by' => Auth::user()->user_id,
            ];

            $campaign = NotificationCampaign::create($campaignData);

            // The API endpoint sends an immediate campaign right away. Nothing
            // ever dispatches 'pending' campaigns, so the web form used to
            // create a row that was never delivered.
            if ($campaign->status === 'pending') {
                $this->pushService->sendCampaign($campaign);
            }

            return redirect()->route('admin.notifications.index')
                ->with('success', 'Notification campaign created successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('NotificationController::storeCampaign failed', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'request_data' => $request->all()
            ]);

            return back()->with('error', 'An error occurred while creating the campaign: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified campaign
     */
    public function showCampaign(NotificationCampaign $campaign): View
    {
        $campaign->load(['template', 'creator', 'logs']);
        return view('admin.notifications.campaigns.show', compact('campaign'));
    }

    /**
     * Dashboard - Get notification overview
     */
    public function dashboard(): JsonResponse
    {
        $stats = [
            'total_templates' => NotificationTemplate::count(),
            'active_templates' => NotificationTemplate::active()->count(),
            'total_campaigns' => NotificationCampaign::count(),
            'pending_campaigns' => NotificationCampaign::pending()->count(),
            'scheduled_campaigns' => NotificationCampaign::scheduled()->count(),
            'completed_campaigns' => NotificationCampaign::completed()->count(),
            'total_logs_today' => NotificationLog::whereDate('created_at', today())->count(),
            'successful_sends_today' => NotificationLog::successful()->whereDate('created_at', today())->count(),
            'failed_sends_today' => NotificationLog::failed()->whereDate('created_at', today())->count(),
        ];

        $recentCampaigns = NotificationCampaign::with(['template', 'creator'])
            ->latest()
            ->limit(5)
            ->get();

        $deliveryStats = NotificationLog::selectRaw('
            platform,
            COUNT(*) as total,
            SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed
        ')
            ->whereDate('created_at', today())
            ->groupBy('platform')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'recent_campaigns' => $recentCampaigns,
                'delivery_stats' => $deliveryStats
            ]
        ]);
    }

    /**
     * Get all notification templates
     */
    public function getTemplates(Request $request): JsonResponse
    {
        $query = NotificationTemplate::with(['creator', 'updater']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $templates = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }

    /**
     * Create notification template
     */
    public function createTemplate(Request $request): JsonResponse
    {
        try {
            Log::info('NotificationController::createTemplate started', [
                'request_data' => $request->all(),
                'user_id' => $request->user()?->user_id ?? 'null',
                'content_type' => $request->header('Content-Type'),
                'method' => $request->method()
            ]);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100|unique:notification_templates',
                'type' => 'required|in:booking_confirmed,booking_updated,booking_cancelled,partner_assigned,partner_on_way,service_started,service_completed,payment_due,payment_received,review_request,promotional,system_update,reminder,custom',
                'title_template' => 'required|string|max:200',
                'message_template' => 'required|string',
                'variables' => 'sometimes|array',
                'is_active' => 'boolean'
            ]);

            Log::info('Validation rules applied', [
                'fails' => $validator->fails(),
                'errors' => $validator->errors()->toArray()
            ]);

            if ($validator->fails()) {
                Log::warning('Validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'request_data' => $request->all()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Set default available variables for templates
            $defaultVariables = [
                'customer_name' => 'Customer full name',
                'first_name' => 'Customer first name',
                'last_name' => 'Customer last name',
                'partner_name' => 'Partner name',
                'service_name' => 'Service name',
                'order_id' => 'Order/Booking ID',
                'amount' => 'Payment amount',
                'date' => 'Current date',
                'time' => 'Current time',
            ];

            // Request::validated() only exists on FormRequests - this is a
            // plain Request, so take the clean data from the validator.
            $templateData = $validator->validated();
            $templateData['variables'] = array_merge($defaultVariables, $request->input('variables', []));

            Log::info('Template data prepared', [
                'template_data' => $templateData,
                'user_id' => $request->user()->user_id
            ]);

            $template = NotificationTemplate::create([
                ...$templateData,
                'created_by' => $request->user()->user_id
            ]);

            Log::info('Template created successfully', [
                'template_id' => $template->template_id,
                'name' => $template->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template created successfully',
                'data' => $template->load(['creator'])
            ]);
        } catch (\Exception $e) {
            Log::error('NotificationController::createTemplate failed', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    /**
     * Update notification template
     */
    public function updateTemplate(Request $request, NotificationTemplate $template): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:notification_templates,name,' . $template->template_id . ',template_id',
            'type' => 'required|in:booking_confirmed,booking_updated,booking_cancelled,partner_assigned,partner_on_way,service_started,service_completed,payment_due,payment_received,review_request,promotional,system_update,reminder,custom',
            'title_template' => 'required|string|max:200',
            'message_template' => 'required|string',
            'variables' => 'sometimes|array',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $template->update([
            ...$validator->validated(),
            'updated_by' => $request->user()->user_id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Template updated successfully',
            'data' => $template->load(['creator', 'updater'])
        ]);
    }

    /**
     * Delete notification template
     */
    public function deleteTemplate(NotificationTemplate $template): JsonResponse
    {
        if ($template->campaigns()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete template that has associated campaigns'
            ], 400);
        }

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully'
        ]);
    }

    /**
     * Get target user counts for campaign planning
     */
    public function getTargetCounts(Request $request): JsonResponse
    {
        $targetType = $request->input('target_type');
        $criteria = $request->input('criteria', []);

        $count = $this->getTargetUserCount($targetType, $criteria);

        return response()->json([
            'success' => true,
            'data' => [
                'target_type' => $targetType,
                'criteria' => $criteria,
                'estimated_recipients' => $count
            ]
        ]);
    }

    /**
     * Create notification campaign
     */
    public function createCampaign(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'template_id' => 'required|exists:notification_templates,template_id',
            'target_type' => 'required|in:all_users,all_customers,all_partners,specific_users,partners_with_negative_balance,inactive_users,high_value_customers,new_users,custom_criteria',
            'target_criteria' => 'sometimes|array',
            'scheduled_at' => 'sometimes|date|after:now',
            'template_variables' => 'sometimes|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Request::validated() only exists on FormRequests - this is a plain
        // Request, so take the clean data from the validator.
        $data = $validator->validated();
        $recipientCount = $this->getTargetUserCount($data['target_type'], $data['target_criteria'] ?? []);

        $campaign = NotificationCampaign::create([
            'name' => $data['name'],
            'template_id' => $data['template_id'],
            'target_type' => $data['target_type'],
            'target_criteria' => $data['target_criteria'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'status' => isset($data['scheduled_at']) ? 'scheduled' : 'pending',
            'created_by' => $request->user()->user_id,
            'total_recipients' => $recipientCount
        ]);

        // If not scheduled, send immediately
        if (!isset($data['scheduled_at'])) {
            $this->pushService->sendCampaign($campaign, $data['template_variables'] ?? []);
        }

        return response()->json([
            'success' => true,
            'message' => 'Campaign created successfully',
            'data' => $campaign->load(['template', 'creator'])
        ]);
    }

    /**
     * Send quick notification to specific users
     */
    public function sendQuickNotification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:200',
            'message' => 'required|string',
            'target_type' => 'required|in:all_users,all_customers,all_partners,specific_users,partners_with_negative_balance',
            'user_ids' => 'required_if:target_type,specific_users|array',
            'user_ids.*' => 'exists:users,user_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $targetType = $request->input('target_type');
        $title = $request->input('title');
        $message = $request->input('message');

        $successCount = 0;
        $failCount = 0;

        if ($targetType === 'specific_users') {
            $users = User::whereIn('user_id', $request->input('user_ids'))->get();
        } else {
            $users = $this->getTargetUsers($targetType, []);
        }

        foreach ($users as $user) {
            if ($this->pushService->sendToUser($user, $title, $message, ['type' => 'admin_broadcast'])) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Quick notification sent',
            'data' => [
                'total_recipients' => $users->count(),
                'successful_sends' => $successCount,
                'failed_sends' => $failCount
            ]
        ]);
    }

    /**
     * Send notification to partners with negative balance
     */
    public function notifyNegativeBalancePartners(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:200',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $partners = User::where('user_type', 'partner')
            ->where('is_active', true)
            ->whereHas('partnerProfile.balance', function ($query) {
                $query->where('available_balance', '<', 0);
            })
            ->get();

        $successCount = 0;
        $failCount = 0;

        foreach ($partners as $partner) {
            $balance = $partner->partnerProfile->balance->available_balance ?? 0;
            $personalizedMessage = str_replace('{{balance}}', abs($balance), $request->input('message'));

            if ($this->pushService->sendToUser($partner, $request->input('title'), $personalizedMessage, ['type' => 'balance_reminder'])) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Negative balance notifications sent',
            'data' => [
                'total_partners' => $partners->count(),
                'successful_sends' => $successCount,
                'failed_sends' => $failCount
            ]
        ]);
    }

    /**
     * Get campaign details with logs
     */
    public function getCampaign(NotificationCampaign $campaign): JsonResponse
    {
        $campaign->load([
            'template',
            'creator',
            'logs' => function ($query) {
                $query->with(['user', 'device'])->latest()->limit(100);
            }
        ]);

        return response()->json([
            'success' => true,
            'data' => $campaign
        ]);
    }

    /**
     * Delivery logs for one campaign, shaped for the admin campaign page.
     *
     * The page used to call a /logs endpoint that never existed, so the table
     * stayed empty even when the campaign had run.
     */
    public function getCampaignLogs(NotificationCampaign $campaign, Request $request): JsonResponse
    {
        $query = NotificationLog::with('user')
            ->where('campaign_id', $campaign->campaign_id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $logs = $query->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $logs->getCollection()->map(fn (NotificationLog $log) => [
                'log_id' => $log->log_id,
                'user_id' => $log->user_id,
                'recipient_email' => $log->user?->email,
                'recipient_phone' => $log->user?->phone,
                'platform' => $log->platform,
                'status' => $log->status,
                'error_message' => $log->error_message,
                'sent_at' => $log->sent_at?->toIso8601String(),
            ])->all(),
            'total' => $logs->total(),
            'per_page' => $logs->perPage(),
            'current_page' => $logs->currentPage(),
        ]);
    }

    /**
     * A single template, for the edit form to populate itself.
     */
    public function getTemplate(NotificationTemplate $template): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }

    /**
     * Get all campaigns with filtering
     */
    public function getCampaigns(Request $request): JsonResponse
    {
        $query = NotificationCampaign::with(['template', 'creator']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('target_type')) {
            $query->where('target_type', $request->target_type);
        }

        $campaigns = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $campaigns
        ]);
    }

    /**
     * Get notification analytics
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $dateFrom = $request->input('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        $analytics = [
            'delivery_stats' => NotificationLog::selectRaw('
                DATE(created_at) as date,
                COUNT(*) as total_sent,
                SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed
            ')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->groupBy('date')
                ->orderBy('date')
                ->get(),

            'platform_stats' => NotificationLog::selectRaw('
                platform,
                COUNT(*) as total,
                SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed
            ')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->groupBy('platform')
                ->get(),

            'campaign_performance' => NotificationCampaign::selectRaw('
                status,
                COUNT(*) as count,
                AVG(successful_sends) as avg_success_rate,
                SUM(total_recipients) as total_recipients
            ')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->groupBy('status')
                ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Helper method to get target user count
     */
    private function getTargetUserCount(string $targetType, array $criteria): int
    {
        switch ($targetType) {
            case 'all_users':
                return User::where('is_active', true)->count();

            case 'all_customers':
                return User::where('user_type', 'customer')->where('is_active', true)->count();

            case 'all_partners':
                return User::where('user_type', 'partner')->where('is_active', true)->count();

            case 'partners_with_negative_balance':
                return User::where('user_type', 'partner')
                    ->where('is_active', true)
                    ->whereHas('partnerProfile.balance', function ($query) {
                        $query->where('available_balance', '<', 0);
                    })
                    ->count();

            case 'inactive_users':
                return User::where('is_active', true)
                    ->where('last_login_at', '<', now()->subDays(30))
                    ->count();

            case 'new_users':
                return User::where('is_active', true)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count();

            case 'specific_users':
                return isset($criteria['user_ids']) ? count($criteria['user_ids']) : 0;

            default:
                return 0;
        }
    }

    /**
     * Helper method to get target users
     */
    private function getTargetUsers(string $targetType, array $criteria)
    {
        switch ($targetType) {
            case 'all_users':
                return User::where('is_active', true)->get();

            case 'all_customers':
                return User::where('user_type', 'customer')->where('is_active', true)->get();

            case 'all_partners':
                return User::where('user_type', 'partner')->where('is_active', true)->get();

            case 'partners_with_negative_balance':
                return User::where('user_type', 'partner')
                    ->where('is_active', true)
                    ->whereHas('partnerProfile.balance', function ($query) {
                        $query->where('available_balance', '<', 0);
                    })
                    ->get();

            default:
                return collect();
        }
    }

    /**
     * Get recipient count for a specific target type
     */
    public function getRecipientCount(Request $request, string $target_type): JsonResponse
    {
        try {
            $count = 0;

            switch ($target_type) {
                case 'all_users':
                    $count = User::count();
                    break;

                case 'all_partners':
                    $count = User::where('user_type', 'partner')->count();
                    break;

                case 'active_partners':
                    $count = User::where('user_type', 'partner')
                        ->whereHas('partnerProfile', function ($query) {
                            $query->where('is_verified', true)
                                ->where('status', 'active');
                        })
                        ->count();
                    break;

                case 'inactive_partners':
                    $count = User::where('user_type', 'partner')
                        ->whereHas('partnerProfile', function ($query) {
                            $query->where('status', '!=', 'active')
                                ->orWhere('is_verified', false);
                        })
                        ->count();
                    break;

                case 'verified_partners':
                    $count = User::where('user_type', 'partner')
                        ->whereHas('partnerProfile', function ($query) {
                            $query->where('is_verified', true);
                        })
                        ->count();
                    break;

                case 'negative_balance_partners':
                    $count = User::where('user_type', 'partner')
                        ->whereHas('partnerBalance', function ($query) {
                            $query->where('available_balance', '<', 0);
                        })
                        ->count();
                    break;

                case 'high_earning_partners':
                    $count = User::where('user_type', 'partner')
                        ->whereHas('partnerBalance', function ($query) {
                            $query->where('total_earnings', '>', 10000); // Define high earning threshold
                        })
                        ->count();
                    break;

                case 'new_partners':
                    $count = User::where('user_type', 'partner')
                        ->where('created_at', '>=', now()->subDays(30))
                        ->count();
                    break;

                case 'active_customers':
                    $count = User::where('user_type', 'customer')
                        ->whereHas('orders', function ($query) {
                            $query->where('created_at', '>=', now()->subDays(90));
                        })
                        ->count();
                    break;

                case 'inactive_customers':
                    $count = User::where('user_type', 'customer')
                        ->whereDoesntHave('orders', function ($query) {
                            $query->where('created_at', '>=', now()->subDays(90));
                        })
                        ->count();
                    break;

                case 'custom':
                    // For custom, we'll handle this in the frontend with additional parameters
                    $count = 0; // Will be calculated based on custom filters
                    break;

                default:
                    return response()->json(['error' => 'Invalid target type'], 400);
            }

            return response()->json([
                'success' => true,
                'count' => $count,
                'target_type' => $target_type
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting recipient count', [
                'target_type' => $target_type,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get recipient count'
            ], 500);
        }
    }
}
