{{-- filepath: c:\Users\kchnp\Desktop\Homify\be\resources\views\admin\earnings\show.blade.php --}}
@extends('layouts.app')

@section('title', 'Earning Details')

@section('content')
    <div class="p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Earning Details</h1>
                <p class="text-gray-600 mt-1">Individual earning record information</p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('admin.earnings.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Earnings
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Information -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Basic Information -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Earning Information</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Entry ID</label>
                                <p class="mt-1 text-sm text-gray-900 font-mono">{{ $earning->id }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Created At</label>
                                <p class="mt-1 text-sm text-gray-900">
                                    {{ $earning->created_at ? $earning->created_at->format('M d, Y H:i:s') : 'Unknown' }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Partner</label>
                                <p class="mt-1 text-sm text-gray-900">
                                    @if ($earning->user)
                                        <a href="{{ route('admin.partners.show', $earning->user) }}"
                                            class="text-blue-600 hover:text-blue-800">
                                            {{ $earning->user->name }}
                                        </a>
                                    @else
                                        <span class="text-red-600">No partner found</span>
                                    @endif
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Order ID</label>
                                <p class="mt-1 text-sm text-gray-900">
                                    @if ($earning->order)
                                        <a href="{{ route('admin.orders.show', $earning->order) }}"
                                            class="text-blue-600 hover:text-blue-800 font-mono">
                                            #{{ substr($earning->booking_id, 0, 12) }}...
                                        </a>
                                    @else
                                        @if ($earning->booking_id)
                                            <span
                                                class="text-red-600 font-mono">#{{ substr($earning->booking_id, 0, 12) }}...
                                                (Not Found)</span>
                                        @else
                                            <span class="text-gray-500">No associated order</span>
                                        @endif
                                    @endif
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Service</label>
                                <p class="mt-1 text-sm text-gray-900">
                                    {{ $earning->order?->orderItems?->first()?->service?->name ?? 'N/A' }}
                                </p>
                                @if ($earning->order?->orderItems?->first()?->service?->category)
                                    <p class="text-xs text-gray-500">
                                        {{ $earning->order->orderItems->first()->service->category->name }}</p>
                                @endif
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Transaction Type</label>
                                <p class="mt-1 text-sm text-gray-900">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if ($earning->type == 'PARTNER_EARNING') bg-green-100 text-green-800
                                        @elseif($earning->type == 'WITHDRAWAL') bg-red-100 text-red-800
                                        @elseif($earning->type == 'REFUND') bg-yellow-100 text-yellow-800
                                        @else bg-gray-100 text-gray-800 @endif">
                                        {{ ucwords(str_replace('_', ' ', $earning->type)) }}
                                    </span>
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Currency</label>
                                <p class="mt-1 text-sm text-gray-900">{{ $earning->currency ?? 'NPR' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financial Breakdown -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Financial Details</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="text-center p-4 bg-green-50 rounded-lg">
                                <div class="text-2xl font-bold text-green-600">
                                    Rs. {{ number_format($earning->amount, 2) }}</div>
                                <div class="text-sm text-gray-600">Earning Amount</div>
                                <div class="text-xs text-gray-500 mt-1">Partner earning from this transaction</div>
                            </div>

                            <div class="text-center p-4 bg-blue-50 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600">
                                    Rs. {{ number_format($earning->balance_after ?? 0, 2) }}</div>
                                <div class="text-sm text-gray-600">Balance After</div>
                                <div class="text-xs text-gray-500 mt-1">Partner balance after this transaction</div>
                            </div>
                        </div>

                        <!-- Transaction Details -->
                        @if ($earning->narration)
                            <div class="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-800 mb-2">Transaction Notes</h4>
                                <div class="text-sm text-gray-700">
                                    <p>{{ $earning->narration }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Order Details -->
                @if ($earning->order)
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Related Order Details</h3>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Order Status</label>
                                    <span
                                        class="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $earning->order->status === 'completed'
                                            ? 'bg-green-100 text-green-800'
                                            : ($earning->order->status === 'pending'
                                                ? 'bg-yellow-100 text-yellow-800'
                                                : ($earning->order->status === 'cancelled'
                                                    ? 'bg-red-100 text-red-800'
                                                    : 'bg-blue-100 text-blue-800')) }}">
                                        {{ ucfirst($earning->order->status) }}
                                    </span>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Customer</label>
                                    <p class="mt-1 text-sm text-gray-900">
                                        @if ($earning->order?->user)
                                            <a href="{{ route('admin.clients.show', $earning->order->user) }}"
                                                class="text-blue-600 hover:text-blue-800">
                                                {{ $earning->order->user->name }}
                                            </a>
                                        @else
                                            N/A
                                        @endif
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Order Date</label>
                                    <p class="mt-1 text-sm text-gray-900">
                                        {{ $earning->order->created_at->format('M d, Y H:i:s') }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Payment Status</label>
                                    <span
                                        class="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        {{ $earning->order->payment_status === 'paid'
                                            ? 'bg-green-100 text-green-800'
                                            : ($earning->order->payment_status === 'pending'
                                                ? 'bg-yellow-100 text-yellow-800'
                                                : 'bg-red-100 text-red-800') }}">
                                        {{ ucfirst($earning->order->payment_status) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Actions -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Actions</h3>
                    </div>
                    <div class="p-6 space-y-3">
                        @if ($earning->order)
                            <a href="{{ route('admin.orders.show', $earning->order) }}"
                                class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                                <i class="fas fa-shopping-cart mr-2"></i>
                                View Order
                            </a>
                        @endif

                        @if ($earning->user)
                            <a href="{{ route('admin.partners.show', $earning->user) }}"
                                class="w-full inline-flex items-center justify-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition">
                                <i class="fas fa-user mr-2"></i>
                                View Partner
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Status Information -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Status</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Settlement Status</span>
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $earning->settlement_status === 'settled'
                                    ? 'bg-green-100 text-green-800'
                                    : ($earning->settlement_status === 'pending'
                                        ? 'bg-yellow-100 text-yellow-800'
                                        : 'bg-gray-100 text-gray-800') }}">
                                {{ ucfirst($earning->settlement_status ?? 'Pending') }}
                            </span>
                        </div>

                        @if ($earning->settlement_date)
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Settlement Date</span>
                                <span
                                    class="text-sm text-gray-900">{{ $earning->settlement_date->format('M d, Y') }}</span>
                            </div>
                        @endif

                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Last Updated</span>
                            <span class="text-sm text-gray-900">
                                {{ $earning->updated_at ? $earning->updated_at->format('M d, Y H:i') : 'N/A' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Related Earnings -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Partner's Recent Earnings</h3>
                    </div>
                    <div class="p-6">
                        @php
                            $recentEarnings = \App\Models\LedgerEntry::where('user_id', $earning->user_id)
                                ->where('type', 'PARTNER_EARNING')
                                ->where('id', '!=', $earning->id)
                                ->latest('created_at')
                                ->take(5)
                                ->get();
                        @endphp

                        @if ($recentEarnings->count() > 0)
                            <div class="space-y-3">
                                @foreach ($recentEarnings as $recentEarning)
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                Rs. {{ number_format($recentEarning->amount, 2) }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                {{ $recentEarning->created_at ? $recentEarning->created_at->format('M d, Y') : 'Not calculated' }}
                                            </div>
                                        </div>
                                        <a href="{{ route('admin.earnings.show', $recentEarning->id) }}"
                                            class="text-blue-600 hover:text-blue-800 text-sm">
                                            View
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-gray-500">No other recent earnings found for this partner.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
