@extends('layouts.app')

@section('title', 'Order Management - UrbanServe Admin')

@section('content')
    <div class="space-y-6">
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
                        <p class="text-3xl font-bold text-blue-900">{{ $stats['orders_today'] ?? 0 }}</p>
                        <div class="flex items-center mt-2">
                            <span class="text-xs text-blue-700">Revenue:</span>
                            <span
                                class="text-sm font-semibold text-blue-800 ml-1">${{ number_format($stats['revenue_today'] ?? 0, 0) }}</span>
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
                        <p class="text-3xl font-bold text-emerald-900">{{ number_format($stats['total_orders']) }}</p>
                        <div class="flex items-center mt-2">
                            <i class="fas fa-arrow-up text-emerald-600 text-xs mr-1"></i>
                            <span class="text-xs text-emerald-700">{{ $stats['growth_rate'] ?? '+12.5' }}% this month</span>
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
                        <p class="text-3xl font-bold text-purple-900">${{ number_format($stats['total_revenue'], 0) }}</p>
                        <div class="flex items-center mt-2">
                            <span class="text-xs text-purple-700">Avg:</span>
                            <span
                                class="text-sm font-semibold text-purple-800 ml-1">${{ number_format($stats['avg_order_value'], 0) }}</span>
                        </div>
                    </div>
                    <div class="p-3 bg-purple-200 rounded-lg">
                        <i class="fas fa-dollar-sign text-purple-700 text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Success Metrics -->
            <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl shadow-sm border border-amber-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-amber-600 mb-1">Success Rate</p>
                        <p class="text-3xl font-bold text-amber-900">{{ number_format($stats['completion_rate'], 1) }}%</p>
                        <div class="flex items-center mt-2">
                            <span class="text-xs text-amber-700">Completed:</span>
                            <span
                                class="text-sm font-semibold text-amber-800 ml-1">{{ number_format($stats['completed_orders']) }}</span>
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
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
                <div class="text-center">
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-plus text-gray-600"></i>
                    </div>
                    <div class="text-2xl font-bold text-gray-900">{{ $stats['status_created'] ?? 0 }}</div>
                    <div class="text-xs text-gray-500">Created</div>
                </div>
                <div class="text-center">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-hourglass-half text-yellow-600"></i>
                    </div>
                    <div class="text-2xl font-bold text-gray-900">{{ $stats['status_awaiting'] ?? 0 }}</div>
                    <div class="text-xs text-gray-500">Awaiting</div>
                </div>
                <div class="text-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-check text-blue-600"></i>
                    </div>
                    <div class="text-2xl font-bold text-gray-900">{{ $stats['status_confirmed'] ?? 0 }}</div>
                    <div class="text-xs text-gray-500">Confirmed</div>
                </div>
                <div class="text-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-route text-purple-600"></i>
                    </div>
                    <div class="text-2xl font-bold text-gray-900">{{ $stats['status_enroute'] ?? 0 }}</div>
                    <div class="text-xs text-gray-500">En Route</div>
                </div>
                <div class="text-center">
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-cog text-indigo-600"></i>
                    </div>
                    <div class="text-2xl font-bold text-gray-900">{{ $stats['status_in_progress'] ?? 0 }}</div>
                    <div class="text-xs text-gray-500">In Progress</div>
                </div>
                <div class="text-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                    <div class="text-2xl font-bold text-gray-900">{{ $stats['status_completed'] ?? 0 }}</div>
                    <div class="text-xs text-gray-500">Completed</div>
                </div>
                <div class="text-center">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-times text-red-600"></i>
                    </div>
                    <div class="text-2xl font-bold text-gray-900">{{ $stats['status_cancelled'] ?? 0 }}</div>
                    <div class="text-xs text-gray-500">Cancelled</div>
                </div>
            </div>
        </div>

        <!-- Payment & Partner Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Overview</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Success Rate</span>
                        <span
                            class="text-lg font-bold text-green-600">{{ number_format($stats['payment_success_rate'], 1) }}%</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Failed Payments</span>
                        <span class="text-lg font-bold text-red-600">{{ $stats['failed_payments'] }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Pending Payments</span>
                        <span class="text-lg font-bold text-yellow-600">{{ $stats['pending_payments'] ?? 0 }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Partner Overview</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Active Partners</span>
                        <span class="text-lg font-bold text-blue-600">{{ $stats['active_partners'] ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Unassigned Orders</span>
                        <span class="text-lg font-bold text-orange-600">{{ $stats['unassigned_orders'] ?? 0 }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Avg Response Time</span>
                        <span class="text-lg font-bold text-green-600">{{ $stats['avg_response_time'] ?? '15' }}min</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->

        <!-- Filters and Search -->
        <div class="bg-white rounded-lg shadow p-6">
            <form method="GET" action="{{ route('admin.orders.index') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
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
                                <option value="{{ $partner->user_id }}"
                                    {{ request('partner_id') == $partner->user_id ? 'selected' : '' }}>
                                    {{ $partner->first_name }} {{ $partner->last_name }}
                                </option>
                            @endforeach
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

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Select
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Order Details
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Customer
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Service & Partner
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Payment
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Total
                                </th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Partner Management
                                </th>
                                <th
                                    class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($orders as $order)
                                <tr class="hover:bg-gray-50" data-order-id="{{ $order->order_id }}">
                                    <!-- Checkbox -->
                                    <td class="px-4 py-3">
                                        <input type="checkbox"
                                            class="order-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            value="{{ $order->order_id }}">
                                    </td>

                                    <!-- Order Details -->
                                    <td class="px-4 py-3">
                                        <div class="text-sm font-medium text-gray-900">
                                            #{{ substr($order->order_id, 0, 8) }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ $order->created_at->format('M j, Y g:i A') }}
                                        </div>
                                        @if ($order->updated_at != $order->created_at)
                                            <div class="text-xs text-gray-500">
                                                Updated {{ $order->updated_at->diffForHumans() }}
                                            </div>
                                        @endif
                                    </td>

                                    <!-- Customer -->
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <img class="h-10 w-10 rounded-full"
                                                    src="https://ui-avatars.com/api/?name={{ urlencode($order->user->full_name) }}&background=random&size=40"
                                                    alt="{{ $order->user->full_name }}">
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $order->user->full_name }}
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    {{ $order->user->email }}
                                                </div>
                                                @if ($order->user->phone)
                                                    <div class="text-xs text-gray-500">
                                                        {{ $order->user->phone }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Service & Partner -->
                                    <td class="px-4 py-3">
                                        <div class="space-y-2">
                                            <!-- Service Info -->
                                            <div class="flex items-center space-x-2">
                                                <div
                                                    class="w-6 h-6 bg-blue-100 rounded flex items-center justify-center flex-shrink-0">
                                                    <i class="fas fa-concierge-bell text-blue-600 text-xs"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-sm font-medium text-gray-900 truncate">
                                                        {{ $order->orderItems->first()->service->name ?? 'N/A' }}
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        {{ $order->orderItems->count() }}
                                                        item{{ $order->orderItems->count() > 1 ? 's' : '' }}
                                                        @if ($order->orderItems->count() > 1)
                                                            <button
                                                                class="text-blue-600 hover:text-blue-800 ml-1 underline"
                                                                onclick="showOrderItems('{{ $order->order_id }}')">
                                                                (view all)
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Partner Info -->
                                            @if ($order->serviceRequests->first() && $order->serviceRequests->first()->partner)
                                                @php $partner = $order->serviceRequests->first()->partner->user @endphp
                                                <div class="flex items-center space-x-2">
                                                    <div
                                                        class="w-6 h-6 bg-green-500 rounded-full overflow-hidden flex-shrink-0">
                                                        <img src="https://ui-avatars.com/api/?name={{ urlencode($partner->full_name) }}&background=10b981&color=fff&size=24"
                                                            alt="{{ $partner->full_name }}"
                                                            class="w-full h-full object-cover">
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="text-sm font-medium text-gray-900 truncate">
                                                            {{ $partner->full_name }}
                                                        </div>
                                                        <div class="text-xs text-gray-500">
                                                            ⭐
                                                            {{ $order->serviceRequests->first()->partner->average_rating ?? 'N/A' }}
                                                            •
                                                            {{ $order->serviceRequests->first()->partner->completed_orders ?? 0 }}
                                                            orders
                                                        </div>
                                                    </div>
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                        Assigned
                                                    </span>
                                                </div>
                                            @else
                                                <div class="flex items-center space-x-2">
                                                    <div
                                                        class="w-6 h-6 bg-gray-300 rounded-full flex items-center justify-center flex-shrink-0">
                                                        <i class="fas fa-user-slash text-gray-600 text-xs"></i>
                                                    </div>
                                                    <div class="flex-1">
                                                        <div class="text-sm text-gray-600">No Partner</div>
                                                        <div class="text-xs text-gray-500">Unassigned</div>
                                                    </div>
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                                                        Pending
                                                    </span>
                                                </div>
                                            @endif
                                        </div>
                                    </td>

                                    <!-- Status -->
                                    <td class="px-4 py-3">
                                        @php
                                            $statusDisplay = match ($order->status) {
                                                'created' => 'Created',
                                                'pending' => 'Pending',
                                                'awaiting_partner' => 'Awaiting Partner',
                                                'partner_assigned' => 'Partner Assigned',
                                                'assigned' => 'Assigned',
                                                'accepted' => 'Accepted',
                                                'confirmed' => 'Confirmed',
                                                'enroute' => 'En Route',
                                                'in_progress' => 'In Progress',
                                                'working' => 'Working',
                                                'completed' => 'Completed',
                                                'closed' => 'Closed',
                                                'cancelled_by_customer' => 'Cancelled (Customer)',
                                                'cancelled_by_partner' => 'Cancelled (Partner)',
                                                'cancelled_by_admin' => 'Cancelled (Admin)',
                                                'cancelled' => 'Cancelled',
                                                'rejected' => 'Rejected',
                                                'refunded' => 'Refunded',
                                                default => ucfirst(str_replace('_', ' ', $order->status)),
                                            };

                                            $statusColor = match ($order->status) {
                                                'created' => 'bg-gray-100 text-gray-800',
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'awaiting_partner' => 'bg-yellow-100 text-yellow-800',
                                                'partner_assigned' => 'bg-blue-100 text-blue-800',
                                                'assigned' => 'bg-blue-100 text-blue-800',
                                                'accepted' => 'bg-blue-100 text-blue-800',
                                                'confirmed' => 'bg-blue-100 text-blue-800',
                                                'enroute' => 'bg-purple-100 text-purple-800',
                                                'in_progress' => 'bg-indigo-100 text-indigo-800',
                                                'working' => 'bg-indigo-100 text-indigo-800',
                                                'completed' => 'bg-green-100 text-green-800',
                                                'closed' => 'bg-green-100 text-green-800',
                                                'cancelled_by_customer' => 'bg-red-100 text-red-800',
                                                'cancelled_by_partner' => 'bg-red-100 text-red-800',
                                                'cancelled_by_admin' => 'bg-red-100 text-red-800',
                                                'cancelled' => 'bg-red-100 text-red-800',
                                                'rejected' => 'bg-red-100 text-red-800',
                                                'refunded' => 'bg-orange-100 text-orange-800',
                                                default => 'bg-gray-100 text-gray-800',
                                            };
                                        @endphp
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                            {{ $statusDisplay }}
                                        </span>
                                    </td>

                                    <!-- Payment -->
                                    <td class="px-4 py-3">
                                        @if (isset($order->payment_status))
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    @switch($order->payment_status)
                                                        @case('pending') bg-yellow-100 text-yellow-800 @break
                                                        @case('authorized') bg-blue-100 text-blue-800 @break
                                                        @case('captured') bg-green-100 text-green-800 @break
                                                        @case('failed') bg-red-100 text-red-800 @break
                                                        @case('refunded') bg-orange-100 text-orange-800 @break
                                                        @case('partial_refund') bg-orange-100 text-orange-800 @break
                                                        @default bg-gray-100 text-gray-800
                                                    @endswitch">
                                                {{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}
                                            </span>
                                        @else
                                            <span class="text-sm text-gray-500">N/A</span>
                                        @endif
                                    </td>

                                    <!-- Total -->
                                    <td class="px-4 py-3">
                                        <div class="text-sm font-medium text-gray-900">
                                            ${{ number_format($order->total_amount, 2) }}
                                        </div>

                                        @if ($order->subtotal != $order->total_amount)
                                            <div class="text-xs text-gray-500">
                                                Subtotal: ${{ number_format($order->subtotal, 2) }}
                                            </div>
                                        @endif
                                        @if ($order->discount_amount > 0)
                                            <div class="text-xs text-gray-500">
                                                Discount: -${{ number_format($order->discount_amount, 2) }}
                                            </div>
                                        @endif
                                        @if ($order->tax_amount > 0)
                                            <div class="text-xs text-gray-500">
                                                Tax: ${{ number_format($order->tax_amount, 2) }}
                                            </div>
                                        @endif
                                        @if ($order->tip_amount > 0)
                                            <div class="text-xs text-gray-500">
                                                Tip: ${{ number_format($order->tip_amount, 2) }}
                                            </div>
                                        @endif
                                    </td>

                                    <!-- Partner Management -->
                                    <td class="px-4 py-3">
                                        @if ($order->serviceRequests->first() && $order->serviceRequests->first()->partner)
                                            <div class="flex flex-col space-y-1">
                                                <a href="{{ route('admin.partners.show', $order->serviceRequests->first()->partner->user) }}"
                                                    class="inline-flex items-center px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded hover:bg-green-200 transition-colors">
                                                    <i class="fas fa-user mr-1 text-xs"></i>View Partner
                                                </a>
                                                <button onclick="reassignPartner('{{ $order->order_id }}')"
                                                    class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-700 text-xs font-medium rounded hover:bg-blue-200 transition-colors">
                                                    <i class="fas fa-exchange-alt mr-1 text-xs"></i>Reassign
                                                </button>
                                            </div>
                                        @else
                                            <button onclick="openPartnerAssignModal('{{ $order->order_id }}')"
                                                class="inline-flex items-center px-2 py-1 bg-orange-100 text-orange-700 text-xs font-medium rounded hover:bg-orange-200 transition-colors">
                                                <i class="fas fa-plus mr-1 text-xs"></i>Assign Partner
                                            </button>
                                        @endif
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-4 py-3 text-right text-sm font-medium">
                                        <div class="flex justify-end space-x-1">
                                            <a href="{{ route('admin.orders.show', $order->order_id) }}"
                                                class="text-blue-600 hover:text-blue-900 p-1.5 rounded hover:bg-blue-50"
                                                title="View Order">
                                                <i class="fas fa-eye text-xs"></i>
                                            </a>
                                            <button onclick="toggleStatusMenu('{{ $order->order_id }}')"
                                                class="text-green-600 hover:text-green-900 p-1.5 rounded hover:bg-green-50"
                                                title="Change Status">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            <a href="{{ route('admin.clients.show', $order->user) }}"
                                                class="text-purple-600 hover:text-purple-900 p-1.5 rounded hover:bg-purple-50"
                                                title="View Customer">
                                                <i class="fas fa-user text-xs"></i>
                                            </a>
                                            @if (in_array($order->status, ['created', 'awaiting_partner', 'confirmed']))
                                                <button onclick="cancelOrder('{{ $order->order_id }}')"
                                                    class="text-red-600 hover:text-red-900 p-1.5 rounded hover:bg-red-50"
                                                    title="Cancel Order">
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
                                    {{ $service->name }} - ${{ number_format($service->base_price, 2) }}
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
                    <label class="block text-sm font-medium text-gray-700 mb-3">Available Partners</label>
                    <div id="availablePartners" class="space-y-3 max-h-64 overflow-y-auto">
                        @foreach ($partners ?? [] as $partner)
                            <div class="partner-option border border-gray-200 rounded-lg p-4 hover:border-blue-300 hover:bg-blue-50 cursor-pointer transition-colors"
                                data-partner-id="{{ $partner->user_id }}" onclick="selectPartner(this)">
                                <div class="flex items-center space-x-3">
                                    <div class="w-12 h-12 bg-blue-500 rounded-full overflow-hidden">
                                        <img src="https://ui-avatars.com/api/?name={{ urlencode($partner->first_name . ' ' . $partner->last_name) }}&background=3b82f6&color=fff&size=48"
                                            alt="{{ $partner->first_name }} {{ $partner->last_name }}"
                                            class="w-full h-full object-cover">
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-900">
                                            {{ $partner->first_name }} {{ $partner->last_name }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ $partner->user->email ?? 'No email' }}
                                        </div>
                                        <div class="text-xs text-gray-400 mt-1">
                                            Rating: ⭐ {{ $partner->average_rating ?? 'N/A' }} •
                                            Completed: {{ $partner->completed_orders ?? 0 }} orders
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs text-gray-500">Status</div>
                                        <div
                                            class="text-sm font-medium {{ $partner->is_available ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $partner->is_available ? 'Available' : 'Busy' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
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
                <select id="newStatusSelect" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
                <textarea id="statusUpdateNotes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Add any special instructions or notes..."></textarea>
            </div>

            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeStatusUpdateModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg">
                    Cancel
                </button>
                <button type="button" onclick="confirmStatusUpdate()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
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
                <label class="block text-sm font-medium text-gray-700 mb-2">Cancellation Reason <span class="text-red-500">*</span></label>
                <textarea id="cancellationReason" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent" placeholder="Please provide a reason for cancellation..."></textarea>
            </div>

            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeCancellationModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg">
                    Cancel
                </button>
                <button type="button" onclick="confirmCancellation()" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg">
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
                <button type="button" onclick="closeConfirmationModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg">
                    Cancel
                </button>
                <button type="button" id="confirmationActionBtn" onclick="executeConfirmationAction()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
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
                <button type="button" onclick="closeNotificationModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    OK
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

        function showConfirmation(title, message, callback, buttonText = 'Confirm', buttonClass = 'bg-blue-600 hover:bg-blue-700') {
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
            document.getElementById('statusUpdateNotes').value = '';
        }

        function confirmStatusUpdate() {
            if (!selectedOrderId) return;
            
            const status = document.getElementById('newStatusSelect').value;
            const notes = document.getElementById('statusUpdateNotes').value;
            
            fetch(`/admin/orders/${selectedOrderId}/status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    status: status,
                    notes: notes
                })
            })
            .then(response => response.json())
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
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    reason: reason
                })
            })
            .then(response => response.json())
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

            // Reset partner selection
            document.querySelectorAll('.partner-option').forEach(option => {
                option.classList.remove('border-blue-500', 'bg-blue-100');
                option.classList.add('border-gray-200');
            });

            // Disable assign button
            document.getElementById('assignPartnerBtn').disabled = true;

            // Show modal
            document.getElementById('partnerAssignModal').classList.remove('hidden');

            // Load order details (you can make an AJAX call here to get fresh data)
            document.getElementById('orderDetailsForAssignment').innerHTML = `
                <div class="flex items-center space-x-2">
                    <strong>Order ID:</strong> <span>#${orderId.substring(0, 8)}</span>
                </div>
            `;
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
                    fetch(`/admin/orders/${selectedOrderId}/assign-partner`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                partner_id: selectedPartnerId,
                                notes: notes
                            })
                        })
                        .then(response => response.json())
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
                alert('Please select at least one order');
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

        function showOrderItems(orderId) {
            // Implement order items modal
            alert('Order items modal - to be implemented');
        }

        // Bulk actions
        function bulkUpdateStatus(status) {
            const selected = getSelectedOrders();
            if (selected.length === 0) {
                alert('Please select orders first');
                return;
            }

            if (confirm(`Update ${selected.length} orders to "${status.replace('_', ' ')}"?`)) {
                fetch('/admin/orders/bulk-update-status', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            order_ids: selected,
                            status: status
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error updating orders: ' + data.message);
                        }
                    });
            }
        }

        function bulkAssignPartner() {
            const selected = getSelectedOrders();
            if (selected.length === 0) {
                alert('Please select orders first');
                return;
            }
            // Implement bulk partner assignment
            alert('Bulk partner assignment - to be implemented');
        }

        function bulkExport() {
            const selected = getSelectedOrders();
            if (selected.length === 0) {
                alert('Please select orders first');
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
