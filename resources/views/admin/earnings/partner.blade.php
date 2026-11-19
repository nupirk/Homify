@extends('layouts.app')

@section('title', 'Partner Earnings')

@section('content')
    <div class="p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    Earnings — {{ $partner->first_name }} {{ $partner->last_name }}
                </h1>
                <p class="text-gray-600 mt-1">{{ $partner->email }} · Partner #{{ $partner->user_id }}</p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('admin.partners.show', $partner->user_id) }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                    <i class="fas fa-user mr-2"></i>
                    Partner Profile
                </a>
                <a href="{{ route('admin.earnings.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                    <i class="fas fa-arrow-left mr-2"></i>
                    All Earnings
                </a>
            </div>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm font-medium text-gray-600">Total Earned (ledger)</p>
                <p class="text-2xl font-bold text-gray-900 mt-2">NPR {{ number_format($summary['total_earned'], 2) }}</p>
                <p class="text-xs text-gray-500 mt-1">Online + COD receipts, net of commission and reversals</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm font-medium text-gray-600">Available Balance</p>
                <p class="text-2xl font-bold text-green-600 mt-2">NPR {{ number_format($summary['available_balance'], 2) }}</p>
                <p class="text-xs text-gray-500 mt-1">Cached partner balance</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm font-medium text-gray-600">Partner Earnings (page)</p>
                <p class="text-2xl font-bold text-gray-900 mt-2">NPR {{ number_format($summary['total_earnings'], 2) }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $summary['total_transactions'] }} entries on this page</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm font-medium text-gray-600">Profile Status</p>
                <p class="text-2xl font-bold text-gray-900 mt-2">
                    {{ ucfirst(str_replace('_', ' ', $partner->partnerProfile?->status ?? 'no profile')) }}
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    Rating {{ number_format($partner->partnerProfile?->rating_avg ?? 0, 1) }}
                    ({{ $partner->partnerProfile?->total_ratings ?? 0 }})
                </p>
            </div>
        </div>

        <!-- Ledger entries -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Partner Earnings Ledger</h3>
                <p class="text-sm text-gray-600 mt-1">Every PARTNER_EARNING posted for this partner, newest first</p>
            </div>

            @if ($earnings->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($earnings as $entry)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $entry->created_at?->format('M d, Y H:i') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if ($entry->order)
                                            <a href="{{ route('admin.orders.show', $entry->order) }}" class="text-blue-600 hover:underline">
                                                #{{ $entry->order->order_number ?? $entry->order_id }}
                                            </a>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900">
                                        NPR {{ number_format($entry->amount, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <a href="{{ route('admin.ledger.show', $entry->id) }}"
                                            class="text-blue-600 hover:underline">Ledger entry</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $earnings->links() }}
                </div>
            @else
                <div class="px-6 py-12 text-center">
                    <i class="fas fa-wallet text-4xl text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No partner earnings recorded for this partner yet.</p>
                </div>
            @endif
        </div>
    </div>
@endsection
