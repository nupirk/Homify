@extends('layouts.app')

@section('title', 'Order Management - UrbanServe Admin')

@section('content')
    <div class="space-y-6 max-w-full overflow-x-hidden">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Order Management</h1>
                <p class="text-gray-600 mt-1">Manage customer orders and service requests</p>
            </div>
            <div class="flex space-x-2">
                <button onclick="openNewOrderModal()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-md flex items-center space-x-1.5 text-sm">
                    <i class="fas fa-plus text-xs"></i>
                    <span>New Order</span>
                </button>
                <button onclick="openBulkActionsModal()"
                    class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-1.5 rounded-md flex items-center space-x-1.5 text-sm">
                    <i class="fas fa-list-check text-xs"></i>
                    <span>Bulk Actions</span>
                </button>
            </div>
        </div>

        <!-- Enhanced Statistics Dashboard -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <!-- Today's Overview -->
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl shadow-sm border border-blue-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-blue-600 mb-1">Today's Orders</p>
                        <p class="text-3xl font-bold text-blue-900">{{ number_format($stats['orders_today'] ?? 0) }}</p>
                        <div class="flex items-center mt-2">
                            <span class="text-xs text-blue-700">Revenue:</span>
                            <span class="text-sm font-semibold text-blue-800 ml-1">Rs
                                {{ number_format($stats['revenue_today'] ?? 0, 2) }}</span>
                        </div>
                    </div>
                    <div class="p-3 bg-blue-200 rounded-lg">
                        <i class="fas fa-calendar-day text-blue-700 text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Total Orders & Growth -->
            <div
                class="bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-xl shadow-sm border border-emerald-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-emerald-600 mb-1">Total Orders</p>
                        <p class="text-3xl font-bold text-emerald-900">{{ number_format($stats['total_orders'] ?? 0) }}</p>
                        <div class="flex items-center mt-2">
                            @php
                                $growthRate = $stats['growth_rate'] ?? 0;
                                $isPositive = $growthRate >= 0;
                            @endphp
                            <i
                                class="fas fa-arrow-{{ $isPositive ? 'up' : 'down' }} text-{{ $isPositive ? 'emerald' : 'red' }}-600 text-xs mr-1"></i>
                            <span
                                class="text-xs text-{{ $isPositive ? 'emerald' : 'red' }}-700">{{ $isPositive ? '+' : '' }}{{ number_format($growthRate, 1) }}%
                                this month</span>
                        </div>
                    </div>
                    <div class="p-3 bg-emerald-200 rounded-lg">
                        <i class="fas fa-shopping-cart text-emerald-700 text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Revenue & Performance -->
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl shadow-sm border border-purple-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-purple-600 mb-1">Total Revenue</p>
                        <p class="text-3xl font-bold text-purple-900">Rs
                            {{ number_format($stats['total_revenue'] ?? 0, 2) }}
                        </p>
                        <div class="flex items-center mt-2">
                            <span class="text-xs text-purple-700">Avg Order:</span>
                            <span class="text-sm font-semibold text-purple-800 ml-1">Rs
                                {{ number_format($stats['avg_order_value'] ?? 0, 2) }}</span>
                        </div>
                    </div>
                    <div class="p-3 bg-purple-200 rounded-lg">
                        <i class="fas fa-coins text-purple-700 text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Success Metrics -->
            <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl shadow-sm border border-amber-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-amber-600 mb-1">Success Rate</p>
                        <p class="text-3xl font-bold text-amber-900">
                            {{ number_format($stats['completion_rate'] ?? 0, 1) }}%
                        </p>
                        <div class="flex items-center mt-2">
                            <span class="text-xs text-amber-700">Completed:</span>
                            <span
                                class="text-sm font-semibold text-amber-800 ml-1">{{ number_format($stats['completed_orders'] ?? 0) }}</span>
                        </div>
                    </div>
                    <div class="p-3 bg-amber-200 rounded-lg">
                        <i class="fas fa-chart-line text-amber-700 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Status Overview -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Order Status Overview</h3>

            @php
                // Use StatusEnumHelper to get all order status values
                use App\Helpers\StatusEnumHelper;

                // Define status configuration with icons, colors, and labels
                $statusConfig = [
                    StatusEnumHelper::ORDER_STATUS_CREATED => [
                        'icon' => 'fas fa-plus',
                        'color' => 'gray',
                        'label' => 'Created',
                    ],
                    StatusEnumHelper::ORDER_STATUS_PENDING => [
                        'icon' => 'fas fa-clock',
                        'color' => 'yellow',
                        'label' => 'Pending',
                    ],
                    StatusEnumHelper::ORDER_STATUS_AWAITING_PARTNER => [
                        'icon' => 'fas fa-user-clock',
                        'color' => 'orange',
                        'label' => 'Awaiting Partner',
                    ],
                    StatusEnumHelper::ORDER_STATUS_PARTNER_ASSIGNED => [
                        'icon' => 'fas fa-user-check',
                        'color' => 'blue',
                        'label' => 'Partner Assigned',
                    ],
                    StatusEnumHelper::ORDER_STATUS_ACCEPTED => [
                        'icon' => 'fas fa-handshake',
                        'color' => 'indigo',
                        'label' => 'Accepted',
                    ],
                    StatusEnumHelper::ORDER_STATUS_CONFIRMED => [
                        'icon' => 'fas fa-check-double',
                        'color' => 'indigo',
                        'label' => 'Confirmed',
                    ],
                    StatusEnumHelper::ORDER_STATUS_ENROUTE => [
                        'icon' => 'fas fa-route',
                        'color' => 'purple',
                        'label' => 'En Route',
                    ],
                    StatusEnumHelper::ORDER_STATUS_IN_PROGRESS => [
                        'icon' => 'fas fa-tools',
                        'color' => 'teal',
                        'label' => 'In Progress',
                    ],
                    StatusEnumHelper::ORDER_STATUS_WORKING => [
                        'icon' => 'fas fa-wrench',
                        'color' => 'teal',
                        'label' => 'Working',
                    ],
                    StatusEnumHelper::ORDER_STATUS_COMPLETED => [
                        'icon' => 'fas fa-check-circle',
                        'color' => 'green',
                        'label' => 'Completed',
                    ],
                    StatusEnumHelper::ORDER_STATUS_CLOSED => [
                        'icon' => 'fas fa-archive',
                        'color' => 'slate',
                        'label' => 'Closed',
                    ],
                    StatusEnumHelper::ORDER_STATUS_CANCELLED => [
                        'icon' => 'fas fa-times',
                        'color' => 'red',
                        'label' => 'Cancelled',
                    ],
                    StatusEnumHelper::ORDER_STATUS_CANCELLED_BY_CUSTOMER => [
                        'icon' => 'fas fa-user-times',
                        'color' => 'red',
                        'label' => 'Cancelled (Client)',
                    ],
                    StatusEnumHelper::ORDER_STATUS_CANCELLED_BY_ADMIN => [
                        'icon' => 'fas fa-user-shield',
                        'color' => 'red',
                        'label' => 'Cancelled (Admin)',
                    ],
                    StatusEnumHelper::ORDER_STATUS_CANCELLED_BY_PARTNER => [
                        'icon' => 'fas fa-user-minus',
                        'color' => 'red',
                        'label' => 'Cancelled (Partner)',
                    ],
                    StatusEnumHelper::ORDER_STATUS_REJECTED => [
                        'icon' => 'fas fa-ban',
                        'color' => 'orange',
                        'label' => 'Rejected',
                    ],
                    StatusEnumHelper::ORDER_STATUS_REFUNDED => [
                        'icon' => 'fas fa-undo',
                        'color' => 'yellow',
                        'label' => 'Refunded',
                    ],
                ];

                // Get all status counts from the stats, filtering out non-status keys
                $activeStatuses = collect($stats['status_counts'] ?? [])->filter(function ($count) {
                    return $count > 0;
                });

                // Sort by importance (completed, in progress, pending, others, cancelled)
                $statusOrder = [
                    StatusEnumHelper::ORDER_STATUS_COMPLETED,
                    StatusEnumHelper::ORDER_STATUS_IN_PROGRESS,
                    StatusEnumHelper::ORDER_STATUS_WORKING,
                    StatusEnumHelper::ORDER_STATUS_ENROUTE,
                    StatusEnumHelper::ORDER_STATUS_CONFIRMED,
                    StatusEnumHelper::ORDER_STATUS_ACCEPTED,
                    StatusEnumHelper::ORDER_STATUS_PARTNER_ASSIGNED,
                    StatusEnumHelper::ORDER_STATUS_AWAITING_PARTNER,
                    StatusEnumHelper::ORDER_STATUS_PENDING,
                    StatusEnumHelper::ORDER_STATUS_CREATED,
                    StatusEnumHelper::ORDER_STATUS_CLOSED,
                    StatusEnumHelper::ORDER_STATUS_REJECTED,
                    StatusEnumHelper::ORDER_STATUS_REFUNDED,
                    StatusEnumHelper::ORDER_STATUS_CANCELLED,
                    StatusEnumHelper::ORDER_STATUS_CANCELLED_BY_CUSTOMER,
                    StatusEnumHelper::ORDER_STATUS_CANCELLED_BY_ADMIN,
                    StatusEnumHelper::ORDER_STATUS_CANCELLED_BY_PARTNER,
                ];

                $sortedStatuses = collect($statusOrder)
                    ->intersect($activeStatuses->keys())
                    ->merge($activeStatuses->keys()->diff($statusOrder));
            @endphp

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 2xl:grid-cols-8 gap-4">
                @foreach ($sortedStatuses as $status)
                    @php
                        $config = $statusConfig[$status] ?? [
                            'icon' => 'fas fa-circle',
                            'color' => 'gray',
                            'label' => ucfirst(str_replace('_', ' ', $status)),
                        ];
                        $count = $activeStatuses[$status];
                    @endphp
                    <div class="text-center">
                        <div
                            class="w-12 h-12 bg-{{ $config['color'] }}-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                            <i class="{{ $config['icon'] }} text-{{ $config['color'] }}-600"></i>
                        </div>
                        <div class="text-2xl font-bold text-gray-900">{{ number_format($count) }}</div>
                        <div class="text-xs text-gray-500 leading-tight">{{ $config['label'] }}</div>
                    </div>
                @endforeach
            </div>

            @if ($activeStatuses->where(fn($count, $status) => str_contains($status, 'cancelled'))->count() > 1)
                <!-- Detailed Cancellation Breakdown -->
                <div class="mt-6 pt-4 border-t border-gray-200">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Cancellation Breakdown</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        @foreach ([StatusEnumHelper::ORDER_STATUS_CANCELLED_BY_CUSTOMER, StatusEnumHelper::ORDER_STATUS_CANCELLED_BY_ADMIN, StatusEnumHelper::ORDER_STATUS_CANCELLED_BY_PARTNER, StatusEnumHelper::ORDER_STATUS_CANCELLED] as $cancelStatus)
                            @if (isset($stats['status_counts'][$cancelStatus]) && $stats['status_counts'][$cancelStatus] > 0)
                                @php
                                    $config = $statusConfig[$cancelStatus];
                                    $labels = [
                                        StatusEnumHelper::ORDER_STATUS_CANCELLED_BY_CUSTOMER => 'By Client',
                                        StatusEnumHelper::ORDER_STATUS_CANCELLED_BY_ADMIN => 'By Admin',
                                        StatusEnumHelper::ORDER_STATUS_CANCELLED_BY_PARTNER => 'By Partner',
                                        StatusEnumHelper::ORDER_STATUS_CANCELLED => 'General',
                                    ];
                                @endphp
                                <div class="text-center p-3 bg-red-50 rounded-lg">
                                    <div class="text-lg font-bold text-red-600">
                                        {{ $stats['status_counts'][$cancelStatus] }}</div>
                                    <div class="text-xs text-red-500">{{ $labels[$cancelStatus] }}</div>
                                </div>
                            @endif
                        @endforeach

                        @if (isset($stats['status_counts'][StatusEnumHelper::ORDER_STATUS_REJECTED]) &&
                                $stats['status_counts'][StatusEnumHelper::ORDER_STATUS_REJECTED] > 0)
                            <div class="text-center p-3 bg-orange-50 rounded-lg">
                                <div class="text-lg font-bold text-orange-600">
                                    {{ $stats['status_counts'][StatusEnumHelper::ORDER_STATUS_REJECTED] }}
                                </div>
                                <div class="text-xs text-orange-500">Rejected</div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Payment & Partner Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Overview</h3>
                @php
                    // Define payment status configuration with colors and labels
                    $paymentConfig = [
                        'pending' => ['color' => 'yellow', 'label' => 'Pending Payments', 'icon' => 'fas fa-clock'],
                        'processing' => ['color' => 'blue', 'label' => 'Processing', 'icon' => 'fas fa-spinner'],
                        'captured' => ['color' => 'green', 'label' => 'Captured', 'icon' => 'fas fa-check-circle'],
                        'paid' => ['color' => 'green', 'label' => 'Paid', 'icon' => 'fas fa-check-circle'],
                        'authorized' => ['color' => 'teal', 'label' => 'Authorized', 'icon' => 'fas fa-shield-check'],
                        'completed' => ['color' => 'green', 'label' => 'Completed', 'icon' => 'fas fa-check-double'],
                        'success' => ['color' => 'green', 'label' => 'Successful', 'icon' => 'fas fa-check'],
                        'failed' => ['color' => 'red', 'label' => 'Failed Payments', 'icon' => 'fas fa-times-circle'],
                        'cancelled' => ['color' => 'gray', 'label' => 'Cancelled', 'icon' => 'fas fa-ban'],
                        'refunded' => ['color' => 'orange', 'label' => 'Refunded', 'icon' => 'fas fa-undo'],
                        'declined' => ['color' => 'red', 'label' => 'Declined', 'icon' => 'fas fa-times'],
                        'expired' => ['color' => 'gray', 'label' => 'Expired', 'icon' => 'fas fa-hourglass-end'],
                    ];

                    $paymentCounts = $stats['payment_status_counts'] ?? [];
                    $totalPayments = array_sum($paymentCounts);

                    // Calculate success rate dynamically
                    $successfulStatuses = ['captured', 'authorized', 'completed', 'success', 'paid'];
                    $successCount = collect($successfulStatuses)->sum(fn($status) => $paymentCounts[$status] ?? 0);
                    $successRate = $totalPayments > 0 ? ($successCount / $totalPayments) * 100 : 0;
                @endphp

                <div class="space-y-3">
                    <!-- Success Rate -->
                    <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg">
                        <span class="text-sm font-medium text-green-700">Success Rate</span>
                        <span class="text-lg font-bold text-green-600">{{ number_format($successRate, 1) }}%</span>
                    </div>

                    <!-- Dynamic Payment Status Counts -->
                    @foreach ($paymentCounts as $status => $count)
                        @if ($count > 0)
                            @php
                                $config = $paymentConfig[$status] ?? [
                                    'color' => 'gray',
                                    'label' => ucfirst(str_replace('_', ' ', $status)),
                                    'icon' => 'fas fa-circle',
                                ];
                            @endphp
                            <div class="flex justify-between items-center">
                                <div class="flex items-center">
                                    <i class="{{ $config['icon'] }} text-{{ $config['color'] }}-500 mr-2"></i>
                                    <span class="text-sm text-gray-600">{{ $config['label'] }}</span>
                                </div>
                                <span
                                    class="text-lg font-bold text-{{ $config['color'] }}-600">{{ number_format($count) }}</span>
                            </div>
                        @endif
                    @endforeach

                    @if (empty($paymentCounts))
                        <div class="text-center py-4 text-gray-500">
                            <i class="fas fa-info-circle mb-2"></i>
                            <p class="text-sm">No payment data available</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Partner Overview</h3>
                @php
                    // Calculate additional partner metrics
                    $totalActivePartners = $stats['active_partners'] ?? 0;
                    $partnersOnline = $stats['partners_online'] ?? 0;
                    $unassignedOrders = $stats['unassigned_orders'] ?? 0;
                    $acceptanceRate = $stats['partner_acceptance_rate'] ?? 0;
                    $avgResponseTime = $stats['avg_response_time'] ?? 0;

                    $onlinePercentage = $totalActivePartners > 0 ? ($partnersOnline / $totalActivePartners) * 100 : 0;
                @endphp

                <div class="space-y-3">
                    <!-- Active Partners -->
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <i class="fas fa-users text-blue-500 mr-2"></i>
                            <span class="text-sm text-gray-600">Active Partners</span>
                        </div>
                        <span class="text-lg font-bold text-blue-600">{{ number_format($totalActivePartners) }}</span>
                    </div>

                    <!-- Partners Online -->
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <i class="fas fa-circle text-green-500 mr-2"></i>
                            <span class="text-sm text-gray-600">Partners Online</span>
                        </div>
                        <div class="text-right">
                            <span class="text-lg font-bold text-teal-600">{{ number_format($partnersOnline) }}</span>
                            <div class="text-xs text-gray-500">{{ number_format($onlinePercentage, 1) }}% online</div>
                        </div>
                    </div>

                    <!-- Unassigned Orders -->
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <i class="fas fa-clock text-orange-500 mr-2"></i>
                            <span class="text-sm text-gray-600">Unassigned Orders</span>
                        </div>
                        <span
                            class="text-lg font-bold {{ $unassignedOrders > 0 ? 'text-orange-600' : 'text-green-600' }}">{{ number_format($unassignedOrders) }}</span>
                    </div>

                    <!-- Partner Acceptance Rate -->
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <i class="fas fa-handshake text-green-500 mr-2"></i>
                            <span class="text-sm text-gray-600">Acceptance Rate</span>
                        </div>
                        <span class="text-lg font-bold text-green-600">{{ number_format($acceptanceRate, 1) }}%</span>
                    </div>

                    <!-- Average Response Time -->
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <i class="fas fa-stopwatch text-indigo-500 mr-2"></i>
                            <span class="text-sm text-gray-600">Avg Response Time</span>
                        </div>
                        <span class="text-lg font-bold text-indigo-600">{{ $avgResponseTime }}min</span>
                    </div>

                    @if ($totalActivePartners === 0)
                        <div class="text-center py-4 text-gray-500">
                            <i class="fas fa-user-plus mb-2"></i>
                            <p class="text-sm">No active partners available</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Filters and Search -->

        <!-- Filters and Search -->
        <div class="bg-white rounded-lg shadow p-6">
            <form method="GET" action="{{ route('admin.orders.index') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-6 lg:grid-cols-8 gap-4">
                    <!-- Search -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <div class="relative">
                            <input type="text" name="search" value="{{ request('search') }}"
                                placeholder="Order ID, customer name, phone..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                    </div>

                    <!-- Date Range -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                        <select name="date_range" onchange="toggleCustomDateInputs(this.value)"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Time</option>
                            <option value="today" {{ request('date_range') === 'today' ? 'selected' : '' }}>Today
                            </option>
                            <option value="yesterday" {{ request('date_range') === 'yesterday' ? 'selected' : '' }}>
                                Yesterday</option>
                            <option value="this_week" {{ request('date_range') === 'this_week' ? 'selected' : '' }}>This
                                Week</option>
                            <option value="this_month" {{ request('date_range') === 'this_month' ? 'selected' : '' }}>This
                                Month</option>
                            <option value="custom" {{ request('date_range') === 'custom' ? 'selected' : '' }}>Custom Range
                            </option>
                        </select>
                    </div>

                    <!-- Order Status -->
                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Order Status</label>
                        <div class="relative">
                            <button type="button" id="statusDropdownBtn"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white text-left flex items-center justify-between"
                                onclick="toggleStatusDropdown()">
                                <span id="statusDropdownText" class="text-gray-700">
                                    @if (request('status'))
                                        {{ count(request('status')) }}
                                        status{{ count(request('status')) > 1 ? 'es' : '' }}
                                        selected
                                    @else
                                        All Statuses
                                    @endif
                                </span>
                                <i class="fas fa-chevron-down text-gray-400"></i>
                            </button>
                            <div id="statusDropdownMenu"
                                class="absolute top-full left-0 right-0 mt-1 bg-white border border-gray-300 rounded-lg shadow-lg z-10 hidden max-h-60 overflow-y-auto">
                                <div class="p-2">
                                    <label class="flex items-center px-3 py-2 hover:bg-gray-50 rounded cursor-pointer">
                                        <input type="checkbox" name="status[]" value="created"
                                            {{ in_array('created', request('status', [])) ? 'checked' : '' }}
                                            class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            onchange="updateStatusDisplay()">
                                        <span class="text-sm">Created</span>
                                    </label>
                                    <label class="flex items-center px-3 py-2 hover:bg-gray-50 rounded cursor-pointer">
                                        <input type="checkbox" name="status[]" value="awaiting_partner"
                                            {{ in_array('awaiting_partner', request('status', [])) ? 'checked' : '' }}
                                            class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            onchange="updateStatusDisplay()">
                                        <span class="text-sm">Awaiting Partner</span>
                                    </label>
                                    <label class="flex items-center px-3 py-2 hover:bg-gray-50 rounded cursor-pointer">
                                        <input type="checkbox" name="status[]" value="confirmed"
                                            {{ in_array('confirmed', request('status', [])) ? 'checked' : '' }}
                                            class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            onchange="updateStatusDisplay()">
                                        <span class="text-sm">Confirmed</span>
                                    </label>
                                    <label class="flex items-center px-3 py-2 hover:bg-gray-50 rounded cursor-pointer">
                                        <input type="checkbox" name="status[]" value="enroute"
                                            {{ in_array('enroute', request('status', [])) ? 'checked' : '' }}
                                            class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            onchange="updateStatusDisplay()">
                                        <span class="text-sm">En Route</span>
                                    </label>
                                    <label class="flex items-center px-3 py-2 hover:bg-gray-50 rounded cursor-pointer">
                                        <input type="checkbox" name="status[]" value="in_progress"
                                            {{ in_array('in_progress', request('status', [])) ? 'checked' : '' }}
                                            class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            onchange="updateStatusDisplay()">
                                        <span class="text-sm">In Progress</span>
                                    </label>
                                    <label class="flex items-center px-3 py-2 hover:bg-gray-50 rounded cursor-pointer">
                                        <input type="checkbox" name="status[]" value="completed"
                                            {{ in_array('completed', request('status', [])) ? 'checked' : '' }}
                                            class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            onchange="updateStatusDisplay()">
                                        <span class="text-sm">Completed</span>
                                    </label>
                                    <label class="flex items-center px-3 py-2 hover:bg-gray-50 rounded cursor-pointer">
                                        <input type="checkbox" name="status[]" value="closed"
                                            {{ in_array('closed', request('status', [])) ? 'checked' : '' }}
                                            class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            onchange="updateStatusDisplay()">
                                        <span class="text-sm">Closed</span>
                                    </label>
                                    <label class="flex items-center px-3 py-2 hover:bg-gray-50 rounded cursor-pointer">
                                        <input type="checkbox" name="status[]" value="cancelled_by_customer"
                                            {{ in_array('cancelled_by_customer', request('status', [])) ? 'checked' : '' }}
                                            class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            onchange="updateStatusDisplay()">
                                        <span class="text-sm">Cancelled (Customer)</span>
                                    </label>
                                    <label class="flex items-center px-3 py-2 hover:bg-gray-50 rounded cursor-pointer">
                                        <input type="checkbox" name="status[]" value="cancelled_by_partner"
                                            {{ in_array('cancelled_by_partner', request('status', [])) ? 'checked' : '' }}
                                            class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            onchange="updateStatusDisplay()">
                                        <span class="text-sm">Cancelled (Partner)</span>
                                    </label>
                                    <label class="flex items-center px-3 py-2 hover:bg-gray-50 rounded cursor-pointer">
                                        <input type="checkbox" name="status[]" value="cancelled_by_admin"
                                            {{ in_array('cancelled_by_admin', request('status', [])) ? 'checked' : '' }}
                                            class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            onchange="updateStatusDisplay()">
                                        <span class="text-sm">Cancelled (Admin)</span>
                                    </label>
                                    <label class="flex items-center px-3 py-2 hover:bg-gray-50 rounded cursor-pointer">
                                        <input type="checkbox" name="status[]" value="refunded"
                                            {{ in_array('refunded', request('status', [])) ? 'checked' : '' }}
                                            class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            onchange="updateStatusDisplay()">
                                        <span class="text-sm">Refunded</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                        <select name="payment_status"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Payment Status</option>
                            <option value="pending" {{ request('payment_status') === 'pending' ? 'selected' : '' }}>
                                Pending</option>
                            <option value="paid" {{ request('payment_status') === 'paid' ? 'selected' : '' }}>
                                Paid</option>
                            <option value="authorized" {{ request('payment_status') === 'authorized' ? 'selected' : '' }}>
                                Authorized</option>
                            <option value="captured" {{ request('payment_status') === 'captured' ? 'selected' : '' }}>
                                Captured</option>
                            <option value="failed" {{ request('payment_status') === 'failed' ? 'selected' : '' }}>Failed
                            </option>
                            <option value="refunded" {{ request('payment_status') === 'refunded' ? 'selected' : '' }}>
                                Refunded</option>
                            <option value="partial_refund"
                                {{ request('payment_status') === 'partial_refund' ? 'selected' : '' }}>Partial Refund
                            </option>
                        </select>
                    </div>

                    <!-- Partner Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Partner</label>
                        <select name="partner_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Partners</option>
                            <option value="unassigned" {{ request('partner_id') === 'unassigned' ? 'selected' : '' }}>
                                Unassigned</option>
                            @foreach ($partners ?? [] as $partner)
                                <option value="{{ $partner->partner_id }}"
                                    {{ (string) request('partner_id') === (string) $partner->partner_id ? 'selected' : '' }}>
                                    {{ $partner->user->full_name ?? ('Partner #' . $partner->partner_id) }}
                                    @if ($partner->is_busy)
                                        (busy)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Service Type Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Service Type</label>
                        <select name="service_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Services</option>
                            <option value="multiple" {{ request('service_id') === 'multiple' ? 'selected' : '' }}>
                                Multiple Services</option>
                            @foreach ($services ?? [] as $service)
                                <option value="{{ $service->service_id }}"
                                    {{ request('service_id') == $service->service_id ? 'selected' : '' }}>
                                    {{ $service->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Assignment Status Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assignment Status</label>
                        <select name="assignment_status"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All</option>
                            <option value="fully_assigned"
                                {{ request('assignment_status') === 'fully_assigned' ? 'selected' : '' }}>
                                Fully Assigned</option>
                            <option value="partially_assigned"
                                {{ request('assignment_status') === 'partially_assigned' ? 'selected' : '' }}>
                                Partially Assigned</option>
                            <option value="unassigned"
                                {{ request('assignment_status') === 'unassigned' ? 'selected' : '' }}>
                                Unassigned</option>
                        </select>
                    </div>
                </div>

                <!-- Custom Date Range Inputs -->
                <div id="customDateInputs" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4"
                    style="{{ request('date_range') === 'custom' ? '' : 'display: none;' }}">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>

                <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mt-4">
                    <div class="flex flex-wrap gap-2">
                        <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-md font-medium text-sm">
                            Apply Filters
                        </button>
                        <a href="{{ route('admin.orders.index') }}"
                            class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1.5 rounded-md font-medium text-sm">
                            Clear All
                        </a>
                        <button type="button" onclick="exportOrders()"
                            class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-md font-medium text-sm">
                            Export
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <select name="sort_by"
                            class="px-2.5 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                            <option value="created_at" {{ request('sort_by') === 'created_at' ? 'selected' : '' }}>Date
                                Created</option>
                            <option value="total_amount" {{ request('sort_by') === 'total_amount' ? 'selected' : '' }}>
                                Order Value</option>
                            <option value="status" {{ request('sort_by') === 'status' ? 'selected' : '' }}>Status</option>
                            <option value="updated_at" {{ request('sort_by') === 'updated_at' ? 'selected' : '' }}>Last
                                Updated</option>
                        </select>
                        <select name="sort_order"
                            class="px-2.5 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                            <option value="desc" {{ request('sort_order') === 'desc' ? 'selected' : '' }}>Newest First
                            </option>
                            <option value="asc" {{ request('sort_order') === 'asc' ? 'selected' : '' }}>Oldest First
                            </option>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            @if (isset($orders) && $orders->count() > 0)
                <!-- Table Header -->
                <div class="px-4 py-3 bg-gray-50 border-b">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-medium text-gray-900">
                            Orders ({{ $orders->total() }})
                        </h3>
                        <div class="flex items-center space-x-3">
                            <span class="text-sm text-gray-600">
                                Showing {{ $orders->firstItem() }} - {{ $orders->lastItem() }}
                            </span>
                            <div class="flex items-center space-x-2">
                                <input type="checkbox" id="selectAll" onchange="toggleAllOrders()"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <label for="selectAll" class="text-sm text-gray-700">Select All</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden shadow-sm border border-gray-200 rounded-lg">
                    <table class="w-full divide-y divide-gray-200">
                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                            <tr>
                                <th class="w-8 px-2 py-3 text-left text-xs font-semibold text-gray-600">
                                    <input type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" id="selectAll">
                                </th>
                                <th
                                    class="w-24 px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Order
                                </th>
                                <th
                                    class="w-32 px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Customer
                                </th>
                                <th
                                    class="w-52 px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Service & Partner
                                </th>
                                <th
                                    class="w-20 px-2 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Status
                                </th>
                                <th
                                    class="w-16 px-2 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Payment
                                </th>
                                <th
                                    class="w-20 px-2 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Total
                                </th>
                                <th
                                    class="w-24 px-2 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @foreach ($orders as $order)
                                <tr class="hover:bg-blue-50/30 transition-colors duration-150"
                                    data-order-id="{{ $order->order_id }}">
                                    <!-- Checkbox -->
                                    <td class="px-2 py-3">
                                        <input type="checkbox"
                                            class="order-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500 focus:ring-offset-1"
                                            value="{{ $order->order_id }}">
                                    </td>

                                    <!-- Order Details -->
                                    <td class="px-3 py-3">
                                        <div class="space-y-1">
                                            <div class="text-xs font-semibold text-gray-900">
                                                #{{ substr($order->order_id, 0, 8) }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                {{ $order->created_at->format('M d, g:i A') }}
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Customer -->
                                    <td class="px-3 py-3">
                                        <div class="flex items-center space-x-2">
                                            <div class="flex-shrink-0">
                                                <div
                                                    class="h-7 w-7 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white text-xs font-semibold">
                                                    {{ strtoupper(substr($order->user->first_name ?? $order->user->name, 0, 1) . substr($order->user->last_name ?? '', 0, 1)) }}
                                                </div>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <a href="{{ route('admin.clients.show', ['client' => $order->user]) }}"
                                                    class="text-xs font-semibold text-blue-600 hover:text-blue-800 truncate block">
                                                    {{ ($order->user->first_name ?? '') . ' ' . ($order->user->last_name ?? ($order->user->name ?? '')) }}
                                                </a>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Service & Partner -->
                                    <td class="px-3 py-3">
                                        <div class="space-y-2">
                                            <!-- Service Info -->
                                            @if ($order->orderItems->count() > 1)
                                                <div class="text-xs font-semibold text-blue-700 truncate">
                                                    Multiple Services ({{ $order->orderItems->count() }})
                                                </div>
                                                <div class="text-xs text-gray-500 truncate">
                                                    {{ $order->orderItems->first()->service->name ?? 'N/A' }}
                                                    +{{ $order->orderItems->count() - 1 }} more
                                                </div>
                                            @else
                                                <div class="text-xs font-semibold text-gray-900 truncate">
                                                    {{ $order->orderItems->first()->service->name ?? 'N/A' }}
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    Qty: {{ $order->orderItems->first()->quantity ?? 1 }}
                                                </div>
                                            @endif

                                            <!-- Partner Info -->
                                            @php
                                                $assignedRequests = $order->serviceRequests->where(
                                                    'partner_id',
                                                    '!=',
                                                    null,
                                                );
                                                $totalRequests = $order->serviceRequests->count();
                                                $pendingRequests = $order->serviceRequests
                                                    ->where('partner_id', null)
                                                    ->count();
                                            @endphp

                                            @if ($assignedRequests->count() > 0)
                                                @if ($order->orderItems->count() > 1)
                                                    <!-- Multiple Services Partner Summary -->
                                                    <div class="text-xs text-gray-600">
                                                        <div class="flex items-center space-x-1">
                                                            <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                                                            <span>{{ $assignedRequests->count() }}/{{ $totalRequests }}
                                                                Partners Assigned</span>
                                                        </div>
                                                        @if ($pendingRequests > 0)
                                                            <div class="flex items-center space-x-1 mt-0.5">
                                                                <div class="w-2 h-2 bg-yellow-500 rounded-full"></div>
                                                                <span>{{ $pendingRequests }} Pending</span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @else
                                                    <!-- Single Service Partner Info -->
                                                    @php
                                                        $partner = $assignedRequests->first()->partner->user;
                                                        $serviceRequest = $assignedRequests->first();
                                                        $partnerStatusConfig = match (
                                                            $serviceRequest->status ?? 'pending'
                                                        ) {
                                                            'pending' => [
                                                                'color' => 'bg-yellow-500',
                                                                'text' => 'Pending',
                                                            ],
                                                            'accepted' => [
                                                                'color' => 'bg-green-500',
                                                                'text' => 'Accepted',
                                                            ],
                                                            'rejected' => [
                                                                'color' => 'bg-red-500',
                                                                'text' => 'Rejected',
                                                            ],
                                                            'in_progress' => [
                                                                'color' => 'bg-indigo-500',
                                                                'text' => 'Working',
                                                            ],
                                                            'completed' => [
                                                                'color' => 'bg-emerald-500',
                                                                'text' => 'Completed',
                                                            ],
                                                            'cancelled' => [
                                                                'color' => 'bg-gray-500',
                                                                'text' => 'Cancelled',
                                                            ],
                                                            default => ['color' => 'bg-gray-400', 'text' => 'Unknown'],
                                                        };
                                                    @endphp
                                                    <div class="flex items-center space-x-1">
                                                        <div
                                                            class="w-5 h-5 bg-green-100 rounded-full overflow-hidden flex-shrink-0">
                                                            <img src="https://ui-avatars.com/api/?name={{ urlencode($partner->full_name) }}&background=10b981&color=fff&size=20"
                                                                alt="{{ $partner->full_name }}"
                                                                class="w-full h-full object-cover">
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <div class="text-xs text-gray-600 truncate">
                                                                {{ $partner->full_name }}
                                                            </div>
                                                            <div class="flex items-center space-x-1 mt-0.5">
                                                                <div
                                                                    class="w-2 h-2 {{ $partnerStatusConfig['color'] }} rounded-full">
                                                                </div>
                                                                <span
                                                                    class="text-xs text-gray-500">{{ $partnerStatusConfig['text'] }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                            @else
                                                <div class="text-xs text-gray-500">
                                                    <div>No Partner Assigned</div>
                                                    <div class="flex items-center space-x-1 mt-0.5">
                                                        <div class="w-2 h-2 bg-gray-400 rounded-full"></div>
                                                        <span class="text-xs text-gray-400">{{ $totalRequests }}
                                                            Unassigned</span>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </td>

                                    <!-- Status -->
                                    <td class="px-2 py-3">
                                        @php
                                            $statusConfig = match ($order->status) {
                                                'created' => ['color' => 'bg-gray-500', 'text' => 'Created'],
                                                'pending' => ['color' => 'bg-yellow-500', 'text' => 'Pending'],
                                                'awaiting_partner' => ['color' => 'bg-orange-500', 'text' => 'Waiting'],
                                                'partner_assigned' => [
                                                    'color' => 'bg-blue-500',
                                                    'text' => 'Partner Assigned',
                                                ],
                                                'accepted' => ['color' => 'bg-indigo-500', 'text' => 'Accepted'],
                                                'confirmed' => ['color' => 'bg-indigo-500', 'text' => 'Confirmed'],
                                                'enroute' => ['color' => 'bg-purple-500', 'text' => 'En Route'],
                                                'in_progress', 'working' => [
                                                    'color' => 'bg-teal-500',
                                                    'text' => 'Working',
                                                ],
                                                'completed', 'closed' => [
                                                    'color' => 'bg-green-500',
                                                    'text' => 'Completed',
                                                ],
                                                'cancelled_by_customer' => [
                                                    'color' => 'bg-red-500',
                                                    'text' => 'Cancelled (Client)',
                                                ],
                                                'cancelled_by_admin' => [
                                                    'color' => 'bg-red-600',
                                                    'text' => 'Cancelled (Admin)',
                                                ],
                                                'cancelled_by_partner' => [
                                                    'color' => 'bg-red-400',
                                                    'text' => 'Cancelled (Partner)',
                                                ],
                                                'cancelled' => ['color' => 'bg-red-500', 'text' => 'Cancelled'],
                                                'rejected' => ['color' => 'bg-red-500', 'text' => 'Rejected'],
                                                'refunded' => ['color' => 'bg-orange-500', 'text' => 'Refunded'],
                                                default => ['color' => 'bg-gray-500', 'text' => 'Unknown'],
                                            };
                                        @endphp

                                        <div class="flex items-center space-x-1">
                                            <div class="w-2 h-2 {{ $statusConfig['color'] }} rounded-full"></div>
                                            <span
                                                class="text-xs text-gray-700 font-medium">{{ $statusConfig['text'] }}</span>
                                        </div>
                                    </td>

                                    <!-- Payment -->
                                    <td class="px-2 py-3">
                                        @if (isset($order->payment_status))
                                            @php
                                                $paymentConfig = match ($order->payment_status) {
                                                    'pending' => ['color' => 'bg-yellow-500', 'text' => 'Pending'],
                                                    'authorized' => ['color' => 'bg-blue-500', 'text' => 'Auth'],
                                                    'captured' => ['color' => 'bg-green-500', 'text' => 'Paid'],
                                                    'paid' => ['color' => 'bg-green-500', 'text' => 'Paid'],
                                                    'failed' => ['color' => 'bg-red-500', 'text' => 'Failed'],
                                                    'refunded' => ['color' => 'bg-orange-500', 'text' => 'Refund'],
                                                    'partial_refund' => [
                                                        'color' => 'bg-orange-400',
                                                        'text' => 'Partial',
                                                    ],
                                                    default => ['color' => 'bg-gray-500', 'text' => 'Unknown'],
                                                };
                                            @endphp

                                            <div class="flex items-center space-x-1">
                                                <div class="w-2 h-2 {{ $paymentConfig['color'] }} rounded-full"></div>
                                                <span
                                                    class="text-xs text-gray-700 font-medium">{{ $paymentConfig['text'] }}</span>
                                            </div>
                                        @else
                                            <div class="flex items-center space-x-1">
                                                <div class="w-2 h-2 bg-gray-400 rounded-full"></div>
                                                <span class="text-xs text-gray-500">N/A</span>
                                            </div>
                                        @endif
                                    </td>

                                    <!-- Total -->
                                    <td class="px-2 py-3">
                                        <div class="space-y-1">
                                            <div class="text-xs font-semibold text-gray-900">
                                                Rs {{ number_format($order->total_amount, 2) }}
                                            </div>
                                            @if ($order->subtotal != $order->total_amount || $order->tax_amount > 0)
                                                <div class="text-xs text-gray-500">
                                                    @if ($order->subtotal != $order->total_amount)
                                                        <div>Sub: Rs {{ number_format($order->subtotal, 2) }}</div>
                                                    @endif
                                                    @if ($order->tax_amount > 0)
                                                        <div>Tax: Rs {{ number_format($order->tax_amount, 2) }}</div>
                                                    @endif
                                                    @if ($order->platform_fee > 0)
                                                        <div>Platform: Rs {{ number_format($order->platform_fee, 2) }}
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-2 py-3">
                                        <div class="flex space-x-1">
                                            <a href="{{ route('admin.orders.show', $order->order_id) }}"
                                                class="text-blue-600 hover:text-blue-900 p-1 rounded hover:bg-blue-50"
                                                title="View">
                                                <i class="fas fa-eye text-xs"></i>
                                            </a>
                                            <button onclick="toggleStatusMenu('{{ $order->order_id }}')"
                                                class="text-green-600 hover:text-green-900 p-1 rounded hover:bg-green-50"
                                                title="Status">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            @if ($order->serviceRequests->first() && !$order->serviceRequests->first()->partner)
                                                <button onclick="assignPartner('{{ $order->order_id }}')"
                                                    class="text-purple-600 hover:text-purple-900 p-1 rounded hover:bg-purple-50"
                                                    title="Assign">
                                                    <i class="fas fa-user-plus text-xs"></i>
                                                </button>
                                            @elseif ($order->status == 'partner_assigned')
                                                <button onclick="reassignPartner('{{ $order->order_id }}')"
                                                    class="text-orange-600 hover:text-orange-900 p-1 rounded hover:bg-orange-50"
                                                    title="Reassign">
                                                    <i class="fas fa-exchange-alt text-xs"></i>
                                                </button>
                                            @endif
                                            @if (in_array($order->status, ['created', 'pending', 'awaiting_partner', 'confirmed', 'partner_assigned']))
                                                <button onclick="cancelOrder('{{ $order->order_id }}')"
                                                    class="text-red-600 hover:text-red-900 p-1 rounded hover:bg-red-50"
                                                    title="Cancel">
                                                    <i class="fas fa-times text-xs"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-4">
                    <div class="flex flex-col sm:flex-row items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing {{ $orders->firstItem() }} to {{ $orders->lastItem() }} of
                            {{ number_format($orders->total()) }} orders
                        </div>
                        <div class="mt-2 sm:mt-0">
                            {{ $orders->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            @else
                <!-- Empty State -->
                <div class="text-center py-12">
                    <i class="fas fa-shopping-cart text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Orders Found</h3>
                    @if (request()->hasAny(['search', 'status', 'payment_status', 'date_range']))
                        <p class="text-gray-500 mb-4">
                            No orders match your current filters. Try adjusting your search criteria.
                        </p>
                        <a href="{{ route('admin.orders.index') }}"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                            Clear All Filters
                        </a>
                    @else
                        <p class="text-gray-500 mb-4">
                            Orders will appear here once customers place them.
                        </p>
                        <button onclick="openNewOrderModal()"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                            Create First Order
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </div>
    </div>

    <!-- New Order Modal -->
    <div id="newOrderModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900">Create New Order</h3>
                <button onclick="closeNewOrderModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="newOrderForm" method="POST" action="{{ route('admin.orders.store') }}">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Customer *</label>
                        <select name="customer_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Customer</option>
                            <!-- Populated via AJAX -->
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Service *</label>
                        <select name="service_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Service</option>
                            @foreach ($services ?? [] as $service)
                                <option value="{{ $service->service_id }}" data-price="{{ $service->base_price }}">
                                    {{ $service->name }} - Rs {{ $service->base_price }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                        <input type="number" name="quantity" value="1" min="1"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Schedule Date</label>
                        <input type="datetime-local" name="scheduled_at"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Service Address</label>
                        <textarea name="service_address" rows="2"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="Enter the full service address"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Discount Amount</label>
                        <input type="number" name="discount_amount" step="0.01" min="0" value="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Admin Notes</label>
                        <input type="text" name="admin_notes"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            placeholder="Internal notes (optional)">
                    </div>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeNewOrderModal()"
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg">
                        Cancel
                    </button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        Create Order
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Actions Modal -->
    <div id="bulkActionsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900">Bulk Actions</h3>
                <button onclick="closeBulkActionsModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="space-y-3">
                <button onclick="bulkUpdateStatus('confirmed')"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-left">
                    <i class="fas fa-check mr-2"></i>Mark as Confirmed
                </button>
                <button onclick="bulkUpdateStatus('cancelled_by_admin')"
                    class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-left">
                    <i class="fas fa-times mr-2"></i>Cancel Selected Orders
                </button>
                <button onclick="bulkAssignPartner()"
                    class="w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-left">
                    <i class="fas fa-user-plus mr-2"></i>Assign Partner
                </button>
                <button onclick="bulkExport()"
                    class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-left">
                    <i class="fas fa-download mr-2"></i>Export Selected
                </button>
            </div>
        </div>
    </div>

    <!-- Partner Assignment Modal -->
    <div id="partnerAssignModal"
        class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-xl bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-900">Assign Partner to Order</h3>
                <button onclick="closePartnerAssignModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div id="partnerAssignContent">
                <div class="mb-4 p-4 bg-blue-50 rounded-lg">
                    <h4 class="font-medium text-blue-900 mb-2">Order Details</h4>
                    <div id="orderDetailsForAssignment" class="text-sm text-blue-800">
                        <!-- Order details will be populated here -->
                    </div>
                </div>

                <div class="mb-6">
                    <div class="flex justify-between items-center mb-3">
                        <label class="block text-sm font-medium text-gray-700">Available Partners</label>
                        <button type="button" onclick="loadSuitablePartners()"
                            class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-lg hover:bg-blue-200">
                            <i class="fas fa-magic mr-1"></i>Show Best Matches
                        </button>
                    </div>
                    <div id="availablePartners" class="space-y-3 max-h-64 overflow-y-auto">
                        <!-- Partners will be dynamically loaded here -->
                    </div>

                    <!-- Loading indicator -->
                    <div id="partnersLoading" class="hidden text-center py-8">
                        <i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i>
                        <p class="text-gray-500 mt-2">Finding best partners...</p>
                    </div>

                    <!-- No partners found -->
                    <div id="noPartnersFound" class="hidden text-center py-8">
                        <i class="fas fa-user-slash text-gray-400 text-2xl"></i>
                        <p class="text-gray-500 mt-2">No suitable partners found for this order</p>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Assignment Notes (Optional)</label>
                    <textarea id="assignmentNotes" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Add any special instructions or notes for the partner..."></textarea>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closePartnerAssignModal()"
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg">
                        Cancel
                    </button>
                    <button type="button" id="assignPartnerBtn" onclick="confirmPartnerAssignment()" disabled
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
                        Assign Partner
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusUpdateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-xl bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-900">Update Order Status</h3>
                <button onclick="closeStatusUpdateModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="mb-4 p-4 bg-blue-50 rounded-lg">
                <h4 class="font-medium text-blue-900 mb-2">Order Details</h4>
                <div id="orderDetailsForStatus" class="text-sm text-blue-800">
                    <!-- Order details will be populated here -->
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">New Status</label>
                <select id="newStatusSelect"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="awaiting_partner">Awaiting Partner</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="enroute">En Route</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled_by_admin">Cancelled by Admin</option>
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Special Instructions (Optional)</label>
                <textarea id="statusUpdateNotes" rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Add any special instructions or notes..."></textarea>
            </div>

            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeStatusUpdateModal()"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg">
                    Cancel
                </button>
                <button type="button" onclick="confirmStatusUpdate()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    Update Status
                </button>
            </div>
        </div>
    </div>

    <!-- Cancellation Modal -->
    <div id="cancellationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-xl bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-900">Cancel Order</h3>
                <button onclick="closeCancellationModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="mb-4 p-4 bg-red-50 rounded-lg">
                <h4 class="font-medium text-red-900 mb-2">Order Details</h4>
                <div id="orderDetailsForCancellation" class="text-sm text-red-800">
                    <!-- Order details will be populated here -->
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Cancellation Reason <span
                        class="text-red-500">*</span></label>
                <textarea id="cancellationReason" rows="3" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                    placeholder="Please provide a reason for cancellation..."></textarea>
            </div>

            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeCancellationModal()"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg">
                    Cancel
                </button>
                <button type="button" onclick="confirmCancellation()"
                    class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg">
                    Cancel Order
                </button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-xl bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 id="confirmationTitle" class="text-xl font-bold text-gray-900">Confirm Action</h3>
                <button onclick="closeConfirmationModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="mb-6">
                <p id="confirmationMessage" class="text-gray-700">Are you sure you want to proceed?</p>
            </div>

            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeConfirmationModal()"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg">
                    Cancel
                </button>
                <button type="button" id="confirmationActionBtn" onclick="executeConfirmationAction()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    Confirm
                </button>
            </div>
        </div>
    </div>

    <!-- Notification Modal (Success/Error) -->
    <div id="notificationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-xl bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 id="notificationTitle" class="text-xl font-bold">Notification</h3>
                <button onclick="closeNotificationModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="mb-6">
                <div id="notificationIcon" class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full">
                    <!-- Icon will be populated here -->
                </div>
                <p id="notificationMessage" class="text-center text-gray-700">Message will appear here</p>
            </div>

            <div class="flex justify-center">
                <button type="button" onclick="closeNotificationModal()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    OK
                </button>
            </div>
        </div>
    </div>

    <!-- NEW MODALS - Replace old ones with modern components -->

    <!-- Notification Modal -->
    <div id="notificationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-xl bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 id="notificationTitle" class="text-xl font-bold">Notification</h3>
                <button onclick="closeNotificationModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="text-center">
                <div id="notificationIcon" class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full">
                    <!-- Icon will be populated here -->
                </div>
                <p id="notificationMessage" class="text-gray-700 mb-6">Message will appear here</p>
                <button type="button" onclick="closeNotificationModal()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    OK
                </button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-xl bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 id="confirmationTitle" class="text-xl font-bold text-gray-900">Confirm Action</h3>
                <button onclick="closeConfirmationModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="mb-6">
                <p id="confirmationMessage" class="text-gray-700">Are you sure you want to proceed?</p>
            </div>

            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeConfirmationModal()"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg">
                    Cancel
                </button>
                <button type="button" id="confirmationActionBtn" onclick="executeConfirmationAction()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    Confirm
                </button>
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusUpdateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-xl bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-900">Update Order Status</h3>
                <button onclick="closeStatusUpdateModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">New Status</label>
                <select id="statusSelect"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Select Status</option>
                    <option value="created">Created</option>
                    <option value="pending">Pending</option>
                    <option value="awaiting_partner">Awaiting Partner</option>
                    <option value="partner_assigned">Partner Assigned</option>
                    <option value="assigned">Assigned</option>
                    <option value="accepted">Accepted</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="enroute">En Route</option>
                    <option value="in_progress">In Progress</option>
                    <option value="working">Working</option>
                    <option value="completed">Completed</option>
                    <option value="closed">Closed</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="cancelled_by_customer">Cancelled by Customer</option>
                    <option value="cancelled_by_partner">Cancelled by Partner</option>
                    <option value="cancelled_by_admin">Cancelled by Admin</option>
                    <option value="rejected">Rejected</option>
                    <option value="refunded">Refunded</option>
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                <textarea id="statusNotes" rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Add any notes about this status change..."></textarea>
            </div>

            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeStatusUpdateModal()"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg">
                    Cancel
                </button>
                <button type="button" onclick="updateStatusWithNotes()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    Update Status
                </button>
            </div>
        </div>
    </div>

    <!-- Cancellation Modal -->
    <div id="cancellationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-xl bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-900">Cancel Order</h3>
                <button onclick="closeCancellationModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="mb-4 p-4 bg-red-50 rounded-lg">
                <h4 class="font-medium text-red-900 mb-2">Order Details</h4>
                <div id="orderDetailsForCancellation" class="text-sm text-red-800">
                    <!-- Order details will be populated here -->
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Cancellation Reason <span class="text-red-500">*</span>
                </label>
                <textarea id="cancellationReason" rows="3" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                    placeholder="Please provide a reason for cancellation..."></textarea>
            </div>

            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeCancellationModal()"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg">
                    Cancel
                </button>
                <button type="button" onclick="confirmCancellation()"
                    class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg">
                    Cancel Order
                </button>
            </div>
        </div>
    </div>

    <script>
        let selectedOrderId = null;
        let selectedPartnerId = null;
        let confirmationCallback = null;

        // Modal utility functions
        function showNotification(type, title, message, callback = null) {
            document.getElementById('notificationTitle').textContent = title;
            document.getElementById('notificationMessage').textContent = message;

            const iconDiv = document.getElementById('notificationIcon');
            const titleElement = document.getElementById('notificationTitle');

            if (type === 'success') {
                iconDiv.className = 'flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-green-100';
                iconDiv.innerHTML = '<i class="fas fa-check text-2xl text-green-600"></i>';
                titleElement.className = 'text-xl font-bold text-green-800';
            } else if (type === 'error') {
                iconDiv.className = 'flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100';
                iconDiv.innerHTML = '<i class="fas fa-times text-2xl text-red-600"></i>';
                titleElement.className = 'text-xl font-bold text-red-800';
            } else {
                iconDiv.className = 'flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-blue-100';
                iconDiv.innerHTML = '<i class="fas fa-info text-2xl text-blue-600"></i>';
                titleElement.className = 'text-xl font-bold text-blue-800';
            }

            document.getElementById('notificationModal').classList.remove('hidden');

            if (callback) {
                setTimeout(() => {
                    closeNotificationModal();
                    callback();
                }, 2000);
            }
        }

        function closeNotificationModal() {
            document.getElementById('notificationModal').classList.add('hidden');
        }

        function showConfirmation(title, message, callback, buttonText = 'Confirm', buttonClass =
            'bg-blue-600 hover:bg-blue-700') {
            document.getElementById('confirmationTitle').textContent = title;
            document.getElementById('confirmationMessage').textContent = message;

            const actionBtn = document.getElementById('confirmationActionBtn');
            actionBtn.textContent = buttonText;
            actionBtn.className = `${buttonClass} text-white px-6 py-2 rounded-lg`;

            confirmationCallback = callback;
            document.getElementById('confirmationModal').classList.remove('hidden');
        }

        function closeConfirmationModal() {
            document.getElementById('confirmationModal').classList.add('hidden');
            confirmationCallback = null;
        }

        function executeConfirmationAction() {
            if (confirmationCallback) {
                confirmationCallback();
            }
            closeConfirmationModal();
        }

        // Status dropdown functions
        function toggleStatusDropdown() {
            const menu = document.getElementById('statusDropdownMenu');
            menu.classList.toggle('hidden');
        }

        function updateStatusDisplay() {
            const checkboxes = document.querySelectorAll('input[name="status[]"]:checked');
            const text = document.getElementById('statusDropdownText');

            if (checkboxes.length === 0) {
                text.textContent = 'All Statuses';
            } else if (checkboxes.length === 1) {
                text.textContent = checkboxes[0].nextElementSibling.textContent;
            } else {
                text.textContent = `${checkboxes.length} statuses selected`;
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('statusDropdownMenu');
            const button = document.getElementById('statusDropdownBtn');

            if (!dropdown.contains(event.target) && !button.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });

        // Status update functions
        function openStatusUpdateModal(orderId) {
            selectedOrderId = orderId;
            document.getElementById('orderDetailsForStatus').innerHTML = `
                <div class="flex items-center space-x-2">
                    <strong>Order ID:</strong> <span>#${orderId.substring(0, 8)}</span>
                </div>
            `;
            document.getElementById('statusUpdateModal').classList.remove('hidden');
        }

        function closeStatusUpdateModal() {
            document.getElementById('statusUpdateModal').classList.add('hidden');
            selectedOrderId = null;
            document.getElementById('statusNotes').value = '';
        }

        function confirmStatusUpdate() {
            if (!selectedOrderId) return;

            const status = document.getElementById('statusSelect').value;
            const notes = document.getElementById('statusNotes').value;

            fetch(`/admin/orders/${selectedOrderId}/update-status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        status: status,
                        notes: notes
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        throw new Error('Response is not JSON');
                    }
                    return response.json();
                })
                .then(data => {
                    closeStatusUpdateModal();
                    if (data.success) {
                        showNotification('success', 'Success', data.message, () => location.reload());
                    } else {
                        showNotification('error', 'Error', data.message);
                    }
                })
                .catch(error => {
                    closeStatusUpdateModal();
                    showNotification('error', 'Error', 'Failed to update status: ' + error.message);
                });
        }

        // Cancellation functions
        function openCancellationModal(orderId) {
            selectedOrderId = orderId;
            document.getElementById('orderDetailsForCancellation').innerHTML = `
                <div class="flex items-center space-x-2">
                    <strong>Order ID:</strong> <span>#${orderId.substring(0, 8)}</span>
                </div>
            `;
            document.getElementById('cancellationModal').classList.remove('hidden');
        }

        function closeCancellationModal() {
            document.getElementById('cancellationModal').classList.add('hidden');
            selectedOrderId = null;
            document.getElementById('cancellationReason').value = '';
        }

        function confirmCancellation() {
            if (!selectedOrderId) return;

            const reason = document.getElementById('cancellationReason').value.trim();
            if (!reason) {
                showNotification('error', 'Error', 'Please provide a cancellation reason');
                return;
            }

            fetch(`/admin/orders/${selectedOrderId}/cancel`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        reason: reason
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        throw new Error('Response is not JSON');
                    }
                    return response.json();
                })
                .then(data => {
                    closeCancellationModal();
                    if (data.success) {
                        showNotification('success', 'Success', data.message, () => location.reload());
                    } else {
                        showNotification('error', 'Error', data.message);
                    }
                })
                .catch(error => {
                    closeCancellationModal();
                    showNotification('error', 'Error', 'Failed to cancel order: ' + error.message);
                });
        }

        // Partner assignment functions
        function openPartnerAssignModal(orderId) {
            selectedOrderId = orderId;
            selectedPartnerId = null;

            // Reset UI
            document.getElementById('assignPartnerBtn').disabled = true;

            // Show modal
            document.getElementById('partnerAssignModal').classList.remove('hidden');

            // Load order details (you can make an AJAX call here to get fresh data)
            document.getElementById('orderDetailsForAssignment').innerHTML = `
                <div class="flex items-center space-x-2">
                    <strong>Order ID:</strong> <span>#${orderId.substring(0, 8)}</span>
                </div>
            `;

            // Automatically load suitable partners
            loadSuitablePartners();
        }

        function closePartnerAssignModal() {
            document.getElementById('partnerAssignModal').classList.add('hidden');
            selectedOrderId = null;
            selectedPartnerId = null;
        }

        function selectPartner(element) {
            // Remove previous selection
            document.querySelectorAll('.partner-option').forEach(option => {
                option.classList.remove('border-blue-500', 'bg-blue-100');
                option.classList.add('border-gray-200');
            });

            // Add selection to clicked element
            element.classList.remove('border-gray-200');
            element.classList.add('border-blue-500', 'bg-blue-100');

            // Store selected partner ID
            selectedPartnerId = element.dataset.partnerId;

            // Enable assign button
            document.getElementById('assignPartnerBtn').disabled = false;
        }

        function confirmPartnerAssignment() {
            if (!selectedOrderId || !selectedPartnerId) {
                showNotification('error', 'Error', 'Please select a partner first');
                return;
            }

            const notes = document.getElementById('assignmentNotes').value;

            showConfirmation(
                'Assign Partner',
                'Are you sure you want to assign this partner to the order?',
                () => {
                    // Make AJAX call to assign partner
                    const requestData = {
                        partner_id: selectedPartnerId,
                        notes: notes
                    };

                    fetch(`/admin/orders/${selectedOrderId}/assign-partner`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                                    'content'),
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(requestData)
                        })
                        .then(response => {
                            if (!response.ok) {
                                // Try to get error details from response
                                return response.text().then(text => {
                                    let errorMessage = `HTTP error! status: ${response.status}`;
                                    try {
                                        const errorData = JSON.parse(text);
                                        errorMessage = errorData.message || errorMessage;
                                        if (errorData.errors) {
                                            errorMessage += '\n' + Object.values(errorData.errors).flat()
                                                .join('\n');
                                        }
                                    } catch (e) {
                                        // Response is not JSON, use default error message
                                    }
                                    throw new Error(errorMessage);
                                });
                            }
                            const contentType = response.headers.get('content-type');
                            if (!contentType || !contentType.includes('application/json')) {
                                throw new Error('Response is not JSON');
                            }
                            return response.json();
                        })
                        .then(data => {
                            closePartnerAssignModal();
                            if (data.success) {
                                showNotification('success', 'Success', data.message, () => location.reload());
                            } else {
                                showNotification('error', 'Error', data.message);
                            }
                        })
                        .catch(error => {
                            closePartnerAssignModal();
                            showNotification('error', 'Error', 'Failed to assign partner: ' + error.message);
                        });
                }
            );
        }

        function reassignPartner(orderId) {
            showConfirmation(
                'Reassign Partner',
                'Are you sure you want to reassign this order to a different partner?',
                () => openPartnerAssignModal(orderId)
            );
        }

        // Toggle custom date inputs
        function toggleCustomDateInputs(value) {
            const customInputs = document.getElementById('customDateInputs');
            if (value === 'custom') {
                customInputs.style.display = 'grid';
            } else {
                customInputs.style.display = 'none';
            }
        }

        // Bulk selection functions
        function toggleAllOrders() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.order-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        function getSelectedOrders() {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            return Array.from(checkboxes).map(cb => cb.value);
        }

        // Modal functions
        function openNewOrderModal() {
            document.getElementById('newOrderModal').classList.remove('hidden');
        }

        function closeNewOrderModal() {
            document.getElementById('newOrderModal').classList.add('hidden');
        }

        function openBulkActionsModal() {
            const selected = getSelectedOrders();
            if (selected.length === 0) {
                showNotification('error', 'Error', 'Please select at least one order');
                return;
            }
            document.getElementById('bulkActionsModal').classList.remove('hidden');
        }

        function closeBulkActionsModal() {
            document.getElementById('bulkActionsModal').classList.add('hidden');
        }

        // Order actions
        function assignPartner(orderId) {
            // Use the new modal instead of redirecting
            openPartnerAssignModal(orderId);
        }

        function toggleStatusMenu(orderId) {
            // Use the new status modal
            openStatusUpdateModal(orderId);
        }

        function updateOrderStatus(orderId, status) {
            // This is now handled by the status modal
            openStatusUpdateModal(orderId);
        }

        function cancelOrder(orderId) {
            // Use the new cancellation modal
            openCancellationModal(orderId);
        }

        function viewCommunications(orderId) {
            window.location.href = `/admin/orders/${orderId}/communications`;
        }

        // Enhanced Partner Assignment Functions
        function loadSuitablePartners() {
            if (!selectedOrderId) return;

            const partnersContainer = document.getElementById('availablePartners');
            const loadingIndicator = document.getElementById('partnersLoading');
            const noPartnersFound = document.getElementById('noPartnersFound');

            // Show loading
            partnersContainer.innerHTML = '';
            loadingIndicator.classList.remove('hidden');
            noPartnersFound.classList.add('hidden');

            fetch(`/admin/orders/${selectedOrderId}/suitable-partners`)
                .then(response => response.json())
                .then(partners => {
                    loadingIndicator.classList.add('hidden');

                    if (partners.length === 0) {
                        noPartnersFound.classList.remove('hidden');
                        return;
                    }

                    // Render partners with enhanced UI
                    partnersContainer.innerHTML = partners.map(partner => renderPartnerCard(partner)).join('');
                })
                .catch(error => {
                    console.error('Error loading partners:', error);
                    loadingIndicator.classList.add('hidden');
                    partnersContainer.innerHTML =
                        '<div class="text-red-500 text-center py-4">Error loading partners</div>';
                });
        }

        function renderPartnerCard(partner) {
            const statusConfig = getPartnerStatusConfig(partner);
            const compatibilityColor = partner.compatibility_score >= 80 ? 'text-green-600' :
                partner.compatibility_score >= 60 ? 'text-yellow-600' : 'text-red-600';

            return `
                <div class="partner-option border border-gray-200 rounded-lg p-4 hover:border-blue-300 hover:bg-blue-50 cursor-pointer transition-colors ${partner.is_busy ? 'opacity-75' : ''}"
                     data-partner-id="${partner.partner_id}" onclick="selectPartner(this)">
                    <div class="flex items-center space-x-3">
                        <div class="relative">
                            <div class="w-12 h-12 bg-blue-500 rounded-full overflow-hidden">
                                <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(partner.user.full_name)}&background=3b82f6&color=fff&size=48"
                                     alt="${partner.user.full_name}" class="w-full h-full object-cover">
                            </div>
                            <div class="absolute -bottom-1 -right-1 w-5 h-5 ${statusConfig.bgColor} rounded-full border-2 border-white flex items-center justify-center">
                                <i class="fas ${statusConfig.icon} text-xs text-white"></i>
                            </div>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center space-x-2">
                                <div class="font-medium text-gray-900">${partner.user.full_name}</div>
                                ${partner.priority_score >= 100 ? '<span class="bg-green-100 text-green-800 text-xs px-2 py-0.5 rounded-full">Best Match</span>' : ''}
                            </div>
                            <div class="text-sm text-gray-500">${partner.user.email || 'No email'}</div>
                            <div class="flex items-center space-x-3 mt-1">
                                <div class="text-xs text-gray-400">
                                    ⭐ ${partner.rating_avg || 'N/A'} • ${partner.total_ratings || 0} orders
                                </div>
                                <div class="text-xs ${compatibilityColor} font-medium">
                                    ${Math.round(partner.compatibility_score)}% Match
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-1 mt-2">
                                ${partner.services.slice(0, 3).map(service =>
                                    `<span class="bg-blue-100 text-blue-800 text-xs px-2 py-0.5 rounded">${service.name}</span>`
                                ).join('')}
                                ${partner.services.length > 3 ? `<span class="text-xs text-gray-500">+${partner.services.length - 3} more</span>` : ''}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs text-gray-500">Status</div>
                            <div class="text-sm font-medium ${statusConfig.textColor}">${statusConfig.text}</div>
                            ${partner.current_workload > 0 ?
                                `<div class="text-xs text-gray-400">${partner.current_workload} active</div>` : ''}
                        </div>
                    </div>
                </div>
            `;
        }

        function getPartnerStatusConfig(partner) {
            if (partner.is_busy) {
                return {
                    text: 'Busy',
                    textColor: 'text-red-600',
                    bgColor: 'bg-red-500',
                    icon: 'fa-times'
                };
            }

            // availability_status comes from AvailabilityService for the
            // order's actual date/time, so it can explain *why* a partner is
            // not selectable instead of a generic "Unavailable".
            const slotStatus = (partner.availability_status || '').toLowerCase();

            if (slotStatus === 'limited') {
                return {
                    text: 'Limited',
                    textColor: 'text-yellow-600',
                    bgColor: 'bg-yellow-500',
                    icon: 'fa-clock'
                };
            }

            if (slotStatus === 'past' || slotStatus === 'outside hours') {
                return {
                    text: slotStatus === 'past' ? 'Slot passed' : 'Outside hours',
                    textColor: 'text-orange-600',
                    bgColor: 'bg-orange-500',
                    icon: 'fa-clock'
                };
            }

            if (!partner.is_available_today) {
                return {
                    text: 'Unavailable',
                    textColor: 'text-orange-600',
                    bgColor: 'bg-orange-500',
                    icon: 'fa-clock'
                };
            } else {
                return {
                    text: 'Available',
                    textColor: 'text-green-600',
                    bgColor: 'bg-green-500',
                    icon: 'fa-check'
                };
            }
        }

        function showOrderItems(orderId) {
            // Implement order items modal
            showNotification('info', 'Coming Soon', 'Order items modal - to be implemented');
        }

        // Bulk actions
        function bulkUpdateStatus(status) {
            const selected = getSelectedOrders();
            if (selected.length === 0) {
                showNotification('error', 'Error', 'Please select orders first');
                return;
            }

            showConfirmation(
                'Bulk Update Status',
                `Update ${selected.length} orders to "${status.replace('_', ' ')}"?`,
                () => {
                    fetch('/admin/orders/bulk-update', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                                    'content'),
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                order_ids: selected,
                                status: status
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showNotification('success', 'Success', data.message, () => location.reload());
                            } else {
                                showNotification('error', 'Error', 'Error updating orders: ' + data.message);
                            }
                        });
                }
            );
        }

        function bulkAssignPartner() {
            const selected = getSelectedOrders();
            if (selected.length === 0) {
                showNotification('error', 'Error', 'Please select orders first');
                return;
            }
            // Implement bulk partner assignment
            showNotification('info', 'Coming Soon', 'Bulk partner assignment - to be implemented');
        }

        function bulkExport() {
            const selected = getSelectedOrders();
            if (selected.length === 0) {
                showNotification('error', 'Error', 'Please select orders first');
                return;
            }

            const params = new URLSearchParams();
            selected.forEach(id => params.append('order_ids[]', id));
            window.open(`/admin/orders/export?${params.toString()}`, '_blank');
        }

        // Clear search function
        function clearSearch() {
            document.querySelector('input[name="search"]').value = '';
            document.querySelector('form').submit();
        }

        // Export orders function
        function exportOrders() {
            const form = document.querySelector('form');
            const formData = new FormData(form);
            const params = new URLSearchParams();

            for (let [key, value] of formData.entries()) {
                if (value) {
                    params.append(key, value);
                }
            }

            window.open(`{{ route('admin.orders.export') }}?${params.toString()}`, '_blank');
        }
    </script>
@endsection
