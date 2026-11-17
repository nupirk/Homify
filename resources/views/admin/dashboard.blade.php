@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-slate-900">Dashboard</h1>
        <p class="text-sm text-slate-600 mt-1">Welcome back! Here's what's happening with your business today.</p>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <!-- Total Customers -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total Customers</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($stats['total_customers']) }}</p>
                </div>
                <div class="w-10 h-10 bg-indigo-50 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-users text-indigo-600"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2">
                <span
                    class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-600">
                    <i class="fas fa-arrow-up mr-1"></i>{{ $userGrowth > 0 ? '+' : '' }}{{ $userGrowth }}%
                </span>
                <span class="text-xs text-slate-500">from last month</span>
            </div>
        </div>

        <!-- Active Partners -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Active Partners</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($stats['partners']) }}</p>
                </div>
                <div class="w-10 h-10 bg-indigo-50 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-user-check text-indigo-600"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center">
                <span class="text-xs text-slate-500">{{ number_format($stats['total_partners']) }} total partners</span>
            </div>
        </div>

        <!-- Total Orders -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total Orders</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($stats['total_orders']) }}</p>
                </div>
                <div class="w-10 h-10 bg-indigo-50 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-calendar-check text-indigo-600"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2">
                <span
                    class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-600">
                    {{ number_format($stats['pending_orders']) }} pending</span>
                <span
                    class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-600">
                    {{ number_format($stats['completed_orders']) }} completed</span>
            </div>
        </div>

        <!-- Total Revenue -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total Revenue</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">Rs {{ $stats['total_revenue'] }}</p>
                </div>
                <div class="w-10 h-10 bg-indigo-50 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-dollar-sign text-indigo-600"></i>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2">
                <span
                    class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-600">
                    <i class="fas fa-arrow-up mr-1"></i>{{ $revenueGrowth > 0 ? '+' : '' }}{{ $revenueGrowth }}%
                </span>
                <span class="text-xs text-slate-500">from last month</span>
            </div>
        </div>
    </div>

    <!-- Secondary Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Active Services</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($stats['total_services']) }}</p>
                </div>
                <div class="w-10 h-10 bg-indigo-50 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-cogs text-indigo-600"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Categories</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($stats['total_categories']) }}</p>
                </div>
                <div class="w-10 h-10 bg-indigo-50 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-tags text-indigo-600"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Average Rating</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($stats['average_rating'], 1) }}</p>
                </div>
                <div class="w-10 h-10 bg-indigo-50 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-star text-indigo-600"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Tables -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        <!-- Booking Trends Chart -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-900">Booking Trends (Last 30 Days)</h3>
                <div class="flex items-center space-x-2">
                    <div class="w-2.5 h-2.5 bg-indigo-600 rounded-full"></div>
                    <span class="text-xs text-slate-500">Bookings</span>
                </div>
            </div>
            <div class="h-64">
                <canvas id="bookingTrendsChart"></canvas>
            </div>
        </div>

        <!-- Top Services -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
            <h3 class="text-sm font-semibold text-slate-900 mb-1">Top Services</h3>
            <div class="divide-y divide-slate-100">
                @foreach ($topServices as $service)
                    <div class="py-3 first:pt-3 last:pb-0 flex items-center justify-between">
                        <div class="flex items-center space-x-3 min-w-0">
                            <div
                                class="w-9 h-9 bg-slate-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-wrench text-slate-500 text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-900 truncate">{{ $service->name }}</p>
                                @php
                                    $servicePrice = $service->base_price ?? $service->pricing_min;
                                @endphp
                                @if ($servicePrice !== null)
                                    <p class="text-xs text-slate-500">Rs {{ number_format((float) $servicePrice, 2) }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0 ml-3">
                            <p class="text-sm font-medium text-slate-900">{{ $service->orders_count }}</p>
                            <p class="text-xs text-slate-500">orders</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200">
            <h3 class="text-sm font-semibold text-slate-900">Recent Orders</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100">
                <thead class="bg-slate-50">
                    <tr>
                        <th
                            class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                            Customer</th>
                        <th
                            class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                            Service</th>
                        <th
                            class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                            Partner</th>
                        <th
                            class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                            Status</th>
                        <th
                            class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Date
                        </th>
                        <th
                            class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                            Amount</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-slate-100">
                    @foreach ($recentOrders as $order)
                        <tr class="hover:bg-slate-50 transition-colors duration-150">
                            <td class="px-5 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-slate-200 rounded-full overflow-hidden mr-3">
                                        <img src="https://ui-avatars.com/api/?name={{ urlencode($order->user->full_name) }}&background=random"
                                            alt="{{ $order->user->full_name }}" class="w-full h-full object-cover">
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-slate-900">{{ $order->user->full_name }}
                                        </div>
                                        <div class="text-xs text-slate-500">{{ $order->user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-slate-900">
                                    {{ $order->orderItems->first()->service->name ?? 'N/A' }}
                                </div>
                                <div class="text-xs text-slate-500">{{ $order->orderItems->count() }} item(s)</div>
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap">
                                @if ($order->serviceRequests->first() && $order->serviceRequests->first()->partner)
                                    @php $partner = $order->serviceRequests->first()->partner->user @endphp
                                    <div class="text-sm font-medium text-slate-900">{{ $partner->full_name }}</div>
                                    <div class="text-xs text-slate-500">{{ $partner->email }}</div>
                                @else
                                    <span class="text-sm text-slate-400">Not assigned</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if ($order->status === 'completed') bg-emerald-50 text-emerald-600
                                @elseif($order->status === 'pending') bg-amber-50 text-amber-600
                                @elseif($order->status === 'confirmed') bg-emerald-50 text-emerald-600
                                @elseif($order->status === 'cancelled') bg-rose-50 text-rose-600
                                @else bg-slate-100 text-slate-600 @endif">
                                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-sm text-slate-600">
                                {{ $order->created_at->format('M j, Y g:i A') }}
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-sm font-medium text-slate-900">
                                Rs {{ $order->total_amount }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Order Trends Chart
                const ctx = document.getElementById('bookingTrendsChart').getContext('2d');
                const orderData = @json($orderTrends);

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: orderData.map(item => new Date(item.date).toLocaleDateString()),
                        datasets: [{
                            label: 'Orders',
                            data: orderData.map(item => item.count),
                            borderColor: 'rgb(79, 70, 229)',
                            backgroundColor: 'rgba(79, 70, 229, 0.08)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            });
        </script>
    @endpush
@endsection
