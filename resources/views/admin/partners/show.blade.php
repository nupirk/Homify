@extends('layouts.app')

@section('title', 'Partner Details - ' . $partner->full_name)

@section('content')
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="flex-shrink-0">
                    @if ($partner->avatar_url)
                        <img class="h-16 w-16 rounded-full object-cover border-2 border-gray-200"
                            src="{{ $partner->avatar_url }}" alt="{{ $partner->full_name }}">
                    @else
                        <div
                            class="h-16 w-16 rounded-full bg-gray-300 flex items-center justify-center border-2 border-gray-200">
                            <i class="fas fa-user text-gray-600 text-xl"></i>
                        </div>
                    @endif
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">{{ $partner->full_name }}</h1>
                    <div class="flex items-center space-x-4 mt-2">
                        <!-- Activity Status -->
                        <div class="flex items-center space-x-1">
                            <span class="text-sm text-gray-500">Active:</span>
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium {{ $partner->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                <span
                                    class="w-2 h-2 {{ $partner->is_active ? 'bg-green-400' : 'bg-red-400' }} rounded-full mr-1"></span>
                                {{ $partner->is_active ? 'Yes' : 'No' }}
                            </span>
                        </div>

                        <!-- Verification Status -->
                        @if ($partner->partnerProfile)
                            @php
                                $status = $partner->partnerProfile->status;
                                $isVerified = $status === 'active';
                                $statusText = $isVerified ? 'Yes' : ucfirst(str_replace('_', ' ', $status));
                                $statusColors = [
                                    'active' => 'bg-green-100 text-green-800',
                                    'pending_verification' => 'bg-yellow-100 text-yellow-800',
                                    'suspended' => 'bg-red-100 text-red-800',
                                    'inactive' => 'bg-gray-100 text-gray-800',
                                ];
                            @endphp
                            <div class="flex items-center space-x-1">
                                <span class="text-sm text-gray-500">Verified:</span>
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium {{ $statusColors[$status] ?? 'bg-gray-100 text-gray-800' }}">
                                    @if ($isVerified)
                                        <i class="fas fa-check-circle mr-1"></i>
                                    @elseif($status === 'pending_verification')
                                        <i class="fas fa-clock mr-1"></i>
                                    @elseif($status === 'suspended')
                                        <i class="fas fa-ban mr-1"></i>
                                    @else
                                        <i class="fas fa-times-circle mr-1"></i>
                                    @endif
                                    {{ $statusText }}
                                </span>
                            </div>
                        @else
                            <div class="flex items-center space-x-1">
                                <span class="text-sm text-gray-500">Verified:</span>
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                    <i class="fas fa-times-circle mr-1"></i>
                                    No Profile
                                </span>
                            </div>
                        @endif
                        <span class="text-gray-500">ID: #{{ $partner->user_id }}</span>
                    </div>
                </div>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('admin.partners.edit', $partner) }}"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-edit"></i>
                    <span>Edit Partner</span>
                </a>
                <a href="{{ route('admin.partners.index') }}"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Partners</span>
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Earnings Card -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Earnings</p>
                        <p class="text-2xl font-bold text-gray-900">${{ number_format($stats['total_earnings'], 2) }}</p>
                        <p class="text-xs text-gray-500">Available: ${{ number_format($stats['available_balance'], 2) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Orders Card -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <i class="fas fa-clipboard-list text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Orders</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $stats['total_orders'] }}</p>
                        <p class="text-xs text-gray-500">Completed: {{ $stats['completed_orders'] }}</p>
                    </div>
                </div>
            </div>

            <!-- Rating Card -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-yellow-100 rounded-lg">
                        <i class="fas fa-star text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Average Rating</p>
                        <div class="flex items-center">
                            <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['average_rating'], 1) }}
                            </p>
                            <div class="ml-2 flex">
                                @for ($i = 1; $i <= 5; $i++)
                                    <i
                                        class="fas fa-star text-{{ $i <= $stats['average_rating'] ? 'yellow' : 'gray' }}-400 text-sm"></i>
                                @endfor
                            </div>
                        </div>
                        <p class="text-xs text-gray-500">{{ $stats['total_reviews'] }} reviews</p>
                    </div>
                </div>
            </div>

            <!-- Services Card -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 rounded-lg">
                        <i class="fas fa-tools text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Services Offered</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $stats['services_offered_count'] }}</p>
                        <p class="text-xs text-gray-500">Active: {{ $stats['active_services_count'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Tabs -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <button onclick="switchTab('overview')" id="tab-overview"
                        class="tab-button active border-blue-500 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Overview
                    </button>
                    <button onclick="switchTab('orders')" id="tab-orders"
                        class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Order History ({{ $stats['total_orders'] }})
                    </button>
                    <button onclick="switchTab('earnings')" id="tab-earnings"
                        class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Earnings ({{ $stats['earnings_count'] }})
                    </button>
                    <button onclick="switchTab('services')" id="tab-services"
                        class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Services ({{ $stats['services_offered_count'] }})
                    </button>
                    <button onclick="switchTab('activity')" id="tab-activity"
                        class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Activity Timeline
                    </button>
                </nav>
            </div>

            <div class="p-6">
                <!-- Overview Tab -->
                <div id="content-overview" class="tab-content">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Partner Information -->
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Partner Information</h3>
                            <dl class="space-y-3">
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Email:</dt>
                                    <dd class="text-sm text-gray-900">{{ $partner->email }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Phone:</dt>
                                    <dd class="text-sm text-gray-900">{{ $partner->phone }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Member Since:</dt>
                                    <dd class="text-sm text-gray-900">{{ $partner->created_at->format('M d, Y') }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-sm font-medium text-gray-500">Last Updated:</dt>
                                    <dd class="text-sm text-gray-900">{{ $partner->updated_at->diffForHumans() }}</dd>
                                </div>
                                @if ($partner->partnerProfile && $partner->partnerProfile->kyc_verified_at)
                                    <div class="flex justify-between">
                                        <dt class="text-sm font-medium text-gray-500">KYC Verified:</dt>
                                        <dd class="text-sm text-green-600">
                                            <i class="fas fa-check mr-1"></i>
                                            {{ $partner->partnerProfile->kyc_verified_at->format('M d, Y') }}
                                        </dd>
                                    </div>
                                @endif
                            </dl>
                        </div>

                        <!-- Working Hours (set by the partner in the app) -->
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Working Hours</h3>
                            <dl class="space-y-2">
                                @foreach ($workingHours as $day)
                                    @php
                                        $openWindows = collect($day['windows'])
                                            ->filter(fn ($window) => !$window['booked'] && $window['end'] > $window['start']);
                                    @endphp
                                    <div class="flex items-start justify-between gap-4 text-sm">
                                        <dt class="font-medium text-gray-500">{{ $day['date']->format('D, M j') }}</dt>
                                        <dd class="text-right {{ $openWindows->isEmpty() ? 'text-red-600' : 'text-gray-900' }}">
                                            {{ $openWindows->isEmpty() ? 'Closed' : $openWindows->map(fn ($window) => $window['start'] . ' - ' . $window['end'])->implode(', ') }}
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                            <p class="mt-3 text-xs text-gray-500">Days with no saved hours fall back to the platform default, 09:00 - 18:00.</p>
                        </div>

                        <!-- Performance Summary -->
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance Summary</h3>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Completion Rate</span>
                                    @php
                                        $completionRate =
                                            $stats['total_orders'] > 0
                                                ? ($stats['completed_orders'] / $stats['total_orders']) * 100
                                                : 0;
                                    @endphp
                                    <span
                                        class="text-sm font-medium text-gray-900">{{ number_format($completionRate, 1) }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-600 h-2 rounded-full" style="width: {{ $completionRate }}%">
                                    </div>
                                </div>

                                <div class="flex items-center justify-between mt-4">
                                    <span class="text-sm text-gray-600">Average Order Value</span>
                                    <span class="text-sm font-medium text-gray-900">
                                        Rs.{{ $stats['total_orders'] > 0 ? number_format($stats['total_gross_amount'] / $stats['total_orders'], 2) : '0.00' }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Monthly Average</span>
                                    <span class="text-sm font-medium text-gray-900">
                                        Rs.{{ number_format($stats['total_earnings'] / max(1, $partner->created_at->diffInMonths(now()) + 1), 2) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity Summary -->
                    <div class="mt-6 bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Activity</h3>
                        @if ($recentOrders && $recentOrders->count() > 0)
                            <div class="space-y-3">
                                @foreach ($recentOrders->take(5) as $order)
                                    <div class="flex items-center justify-between p-3 bg-white rounded border">
                                        <div class="flex items-center space-x-3">
                                            <div class="flex-shrink-0">
                                                <span
                                                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                    {{ $order->status === 'completed'
                                                        ? 'bg-green-100 text-green-800'
                                                        : ($order->status === 'in_progress'
                                                            ? 'bg-blue-100 text-blue-800'
                                                            : 'bg-yellow-100 text-yellow-800') }}">
                                                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                                </span>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">Order
                                                    #{{ $order->request_id }}</p>
                                                <p class="text-xs text-gray-500">{{ $order->created_at->diffForHumans() }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            @if ($order->order)
                                                <p class="text-sm font-medium text-gray-900">
                                                    Rs.{{ number_format($order->order->total_amount, 2) }}</p>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-4 text-center">
                                <button onclick="switchTab('orders')" class="text-blue-600 hover:text-blue-800 text-sm">
                                    View All Order History →
                                </button>
                            </div>
                        @else
                            <p class="text-gray-500 text-center py-4">No recent activity</p>
                        @endif
                    </div>
                </div>

                <!-- Orders Tab -->
                <div id="content-orders" class="tab-content hidden">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900">Complete Order History & Timeline</h3>
                            <div class="flex space-x-2">
                                <select onchange="filterOrders(this.value)"
                                    class="px-3 py-1 border border-gray-300 rounded text-sm">
                                    <option value="all">All Orders</option>
                                    <option value="completed">Completed</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="pending">Pending</option>
                                    <option value="confirmed">Confirmed</option>
                                </select>
                            </div>
                        </div>

                        @if ($allServiceRequests && $allServiceRequests->count() > 0)
                            <div class="bg-white border rounded-lg overflow-hidden">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Order Details</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Client Information</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Services Provided</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Schedule</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Amount</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Status & Progress</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @foreach ($allServiceRequests as $serviceRequest)
                                            <tr class="hover:bg-gray-50 order-row"
                                                data-status="{{ $serviceRequest->status }}">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        #{{ $serviceRequest->request_id }}</div>
                                                    <div class="text-xs text-gray-500">Created:
                                                        {{ $serviceRequest->created_at->format('M d, Y g:i A') }}</div>
                                                    @if ($serviceRequest->completed_at)
                                                        <div class="text-xs text-green-600">Completed:
                                                            {{ $serviceRequest->completed_at->format('M d, Y g:i A') }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    @if ($serviceRequest->order && $serviceRequest->order->client)
                                                        <div class="text-sm text-gray-900">
                                                            {{ $serviceRequest->order->client->first_name }}
                                                            {{ $serviceRequest->order->client->last_name }}</div>
                                                        <div class="text-xs text-gray-500">
                                                            {{ $serviceRequest->order->client->email }}</div>
                                                        <div class="text-xs text-gray-500">
                                                            {{ $serviceRequest->order->client->phone ?? 'No phone' }}</div>
                                                    @else
                                                        <span class="text-sm text-gray-500">Unknown Client</span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4">
                                                    @if ($serviceRequest->order && $serviceRequest->order->orderItems)
                                                        <div class="flex flex-wrap gap-1">
                                                            @foreach ($serviceRequest->order->orderItems as $item)
                                                                <span
                                                                    class="inline-block bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                                                    {{ $item->service->name ?? 'Unknown Service' }}
                                                                    @if ($item->quantity > 1)
                                                                        <span
                                                                            class="ml-1 font-medium">×{{ $item->quantity }}</span>
                                                                    @endif
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                        @if ($serviceRequest->special_instructions)
                                                            <div class="text-xs text-gray-600 mt-1 italic">
                                                                {{ Str::limit($serviceRequest->special_instructions, 50) }}
                                                            </div>
                                                        @endif
                                                    @else
                                                        <span class="text-sm text-gray-500">-</span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    @if ($serviceRequest->service_date)
                                                        <div class="font-medium">
                                                            {{ $serviceRequest->service_date->format('M d, Y') }}</div>
                                                        @if ($serviceRequest->start_time)
                                                            <div class="text-xs text-gray-500">
                                                                {{ $serviceRequest->start_time->format('g:i A') }}
                                                                @if ($serviceRequest->end_time)
                                                                    - {{ $serviceRequest->end_time->format('g:i A') }}
                                                                @endif
                                                            </div>
                                                        @endif
                                                    @else
                                                        <span class="text-gray-500">Not scheduled</span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    @if ($serviceRequest->order)
                                                        <div class="text-sm font-medium text-gray-900">
                                                            ${{ number_format($serviceRequest->order->total_amount, 2) }}
                                                        </div>
                                                        <div class="text-xs text-gray-500">Payment:
                                                            {{ ucfirst($serviceRequest->order->payment_status ?? 'pending') }}
                                                        </div>
                                                    @else
                                                        <span class="text-gray-500">-</span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span
                                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                        {{ $serviceRequest->status === 'completed'
                                                            ? 'bg-green-100 text-green-800'
                                                            : ($serviceRequest->status === 'in_progress'
                                                                ? 'bg-blue-100 text-blue-800'
                                                                : ($serviceRequest->status === 'confirmed'
                                                                    ? 'bg-purple-100 text-purple-800'
                                                                    : 'bg-yellow-100 text-yellow-800')) }}">
                                                        {{ ucfirst(str_replace('_', ' ', $serviceRequest->status)) }}
                                                    </span>
                                                    @if ($serviceRequest->partner_assigned_at)
                                                        <div class="text-xs text-gray-500 mt-1">Assigned:
                                                            {{ $serviceRequest->partner_assigned_at->format('M d') }}</div>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <div class="flex space-x-2">
                                                        @if ($serviceRequest->order)
                                                            <a href="{{ route('admin.orders.show', $serviceRequest->order) }}"
                                                                class="text-blue-600 hover:text-blue-900"
                                                                title="View Full Order Details">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        @endif
                                                        @if ($serviceRequest->special_instructions)
                                                            <button
                                                                onclick="showInstructions('{{ $serviceRequest->request_id }}', '{{ addslashes($serviceRequest->special_instructions) }}')"
                                                                class="text-green-600 hover:text-green-900"
                                                                title="View Instructions">
                                                                <i class="fas fa-sticky-note"></i>
                                                            </button>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-12">
                                <i class="fas fa-clipboard-list text-gray-400 text-4xl mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">No Order History</h3>
                                <p class="text-gray-500">This partner hasn't received any service orders yet.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Other tabs placeholders for comprehensive data -->
                <div id="content-earnings" class="tab-content hidden">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Comprehensive Earnings History & Analytics</h3>
                    <p class="text-gray-600 mb-4">Complete financial overview with detailed breakdown, payment history, and
                        performance analytics.</p>
                    <!-- Earnings details would be implemented here -->
                </div>

                <div id="content-services" class="tab-content hidden">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Services Portfolio & Performance</h3>
                    <p class="text-gray-600 mb-4">Complete list of services offered with individual performance metrics and
                        client feedback.</p>
                    <!-- Services details would be implemented here -->
                </div>

                <div id="content-activity" class="tab-content hidden">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Complete Activity Timeline & Milestones</h3>
                    <p class="text-gray-600 mb-4">Comprehensive chronological view of all partner activities, achievements,
                        and system interactions.</p>
                    <!-- Activity timeline would be implemented here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching functionality
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });

            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active', 'border-blue-500', 'text-blue-600');
                button.classList.add('border-transparent', 'text-gray-500');
            });

            // Show selected tab content
            document.getElementById('content-' + tabName).classList.remove('hidden');

            // Add active class to selected tab button
            const activeTab = document.getElementById('tab-' + tabName);
            activeTab.classList.add('active', 'border-blue-500', 'text-blue-600');
            activeTab.classList.remove('border-transparent', 'text-gray-500');
        }

        // Order filtering
        function filterOrders(status) {
            const rows = document.querySelectorAll('.order-row');
            rows.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Show special instructions
        function showInstructions(orderId, instructions) {
            alert(`Order #${orderId} Instructions:\n\n${instructions}`);
        }
    </script>
@endsection
