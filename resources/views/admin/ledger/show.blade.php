@extends('layouts.app')

@section('title', 'Ledger Entry Details')

@section('content')
    <div class="max-w-6xl mx-auto">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Ledger Entry #{{ $entry->id }}</h1>
                    <p class="text-gray-600 mt-2">Detailed view of ledger transaction</p>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="{{ route('admin.ledger.index') }}"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to List</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Entry Details -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6">Entry Details</h2>

                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Entry ID</label>
                                <p class="text-lg text-gray-900 font-mono">{{ $entry->id }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Created Date</label>
                                <p class="text-lg text-gray-900">{{ $entry->created_at->format('F j, Y \a\t g:i A') }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Updated Date</label>
                                <p class="text-lg text-gray-900">{{ $entry->updated_at->format('F j, Y \a\t g:i A') }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Booking ID</label>
                                @if ($entry->booking_id)
                                    <a href="{{ route('admin.ledger.order', $entry->booking_id) }}"
                                        class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium hover:bg-blue-200">
                                        View Order #{{ $entry->booking_id }}
                                    </a>
                                @else
                                    <p class="text-gray-400">Not associated with any booking</p>
                                @endif
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500">User</label>
                            @if ($entry->user)
                                <div class="flex items-center space-x-4 mt-2">
                                    <div>
                                        <p class="text-lg font-medium text-gray-900">
                                            {{ $entry->user->first_name ?? '' }} {{ $entry->user->last_name ?? '' }}
                                        </p>
                                        <p class="text-sm text-gray-600">{{ $entry->user->email ?? 'No email' }}</p>
                                        <a href="{{ route('admin.ledger.user', $entry->user_id) }}"
                                            class="inline-flex items-center px-2 py-1 bg-green-100 text-green-800 rounded text-sm hover:bg-green-200 mt-1">
                                            View User Ledger
                                        </a>
                                    </div>
                                </div>
                            @else
                                <p class="text-gray-400">User #{{ $entry->user_id }} (Deleted)</p>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Entry Type</label>
                                <span
                                    class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium mt-1
                                    @if ($entry->type == 'CUSTOMER_DEBIT') bg-red-100 text-red-800
                                    @elseif($entry->type == 'PARTNER_EARNING') bg-green-100 text-green-800
                                    @elseif($entry->type == 'PLATFORM_FEE') bg-blue-100 text-blue-800
                                    @elseif($entry->type == 'TAX_DEDUCTION') bg-yellow-100 text-yellow-800
                                    @else bg-gray-100 text-gray-800 @endif">
                                    {{ str_replace('_', ' ', $entry->type) }}
                                </span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Amount</label>
                                <p
                                    class="text-2xl font-bold mt-1 {{ $entry->amount < 0 ? 'text-red-600' : 'text-green-600' }}">
                                    Rs {{ number_format($entry->amount, 2) }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500">Balance After</label>
                            @if ($entry->balance_after !== null)
                                <p
                                    class="text-xl font-semibold mt-1 {{ $entry->balance_after < 0 ? 'text-red-600' : 'text-green-600' }}">
                                    Rs {{ number_format($entry->balance_after, 2) }}
                                </p>
                            @else
                                <p class="text-gray-400 mt-1">Not applicable</p>
                            @endif
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500">Narration</label>
                            <div class="bg-gray-50 rounded-lg p-4 mt-2">
                                <p class="text-gray-700">{{ $entry->narration ?: 'No description provided' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                @if ($entry->booking_id && isset($relatedEntries))
                    <!-- Related Order Entries -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Related Order Entries</h3>
                        @if ($relatedEntries->count() > 0)
                            <div class="space-y-3">
                                @foreach ($relatedEntries as $related)
                                    <div class="border-b border-gray-200 pb-3 last:border-b-0">
                                        <div class="flex justify-between items-start mb-1">
                                            <span
                                                class="inline-flex items-center px-2 py-1 rounded text-xs font-medium
                                                @if ($related->type == 'CUSTOMER_DEBIT') bg-red-100 text-red-800
                                                @elseif($related->type == 'PARTNER_EARNING') bg-green-100 text-green-800
                                                @elseif($related->type == 'PLATFORM_FEE') bg-blue-100 text-blue-800
                                                @else bg-gray-100 text-gray-800 @endif">
                                                {{ str_replace('_', ' ', $related->type) }}
                                            </span>
                                            <span class="font-semibold">Rs {{ number_format($related->amount, 2) }}</span>
                                        </div>
                                        <p class="text-sm text-gray-600">
                                            {{ $related->user ? $related->user->first_name . ' ' . $related->user->last_name : 'User #' . $related->user_id }}
                                        </p>
                                        @if ($related->id != $entry->id)
                                            <a href="{{ route('admin.ledger.show', $related->id) }}"
                                                class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs hover:bg-blue-200 mt-1">
                                                View
                                            </a>
                                        @else
                                            <span
                                                class="inline-flex items-center px-2 py-1 bg-indigo-100 text-indigo-800 rounded text-xs mt-1">
                                                Current Entry
                                            </span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-400">No other entries for this booking</p>
                        @endif
                    </div>
                @endif

                @if ($entry->user)
                    <!-- User Balance Summary -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">User Balance Summary</h3>
                        @php
                            $currentBalance = \App\Models\LedgerEntry::getUserBalance($entry->user_id) ?? 0;
                            $entryCount = \App\Models\LedgerEntry::where('user_id', $entry->user_id)->count();
                        @endphp

                        <div class="text-center mb-4">
                            <p class="text-3xl font-bold {{ $currentBalance < 0 ? 'text-red-600' : 'text-green-600' }}">
                                Rs {{ number_format($currentBalance, 2) }}
                            </p>
                            <p class="text-sm text-gray-600">Current Balance</p>
                        </div>

                        <div class="border-t border-gray-200 pt-4">
                            <div class="text-center mb-4">
                                <p class="text-xl font-semibold text-gray-900">{{ number_format($entryCount) }}</p>
                                <p class="text-sm text-gray-600">Total Entries</p>
                            </div>
                            <a href="{{ route('admin.ledger.user', $entry->user_id) }}"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-center font-medium">
                                View User Ledger
                            </a>
                        </div>
                    </div>
                @endif

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                    <div class="space-y-2">
                        @if ($entry->booking_id)
                            <a href="{{ route('admin.ledger.order', $entry->booking_id) }}"
                                class="flex items-center space-x-2 p-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition">
                                <i class="fas fa-shopping-cart"></i>
                                <span>View Order Ledger</span>
                            </a>
                        @endif

                        @if ($entry->user)
                            <a href="{{ route('admin.ledger.user', $entry->user_id) }}"
                                class="flex items-center space-x-2 p-3 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition">
                                <i class="fas fa-user"></i>
                                <span>View User Ledger</span>
                            </a>
                        @endif

                        <a href="{{ route('admin.ledger.index', ['type' => $entry->type]) }}"
                            class="flex items-center space-x-2 p-3 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition">
                            <i class="fas fa-filter"></i>
                            <span>View Similar Entries</span>
                        </a>

                        <a href="{{ route('admin.ledger.index', ['date_from' => $entry->created_at->format('Y-m-d'), 'date_to' => $entry->created_at->format('Y-m-d')]) }}"
                            class="flex items-center space-x-2 p-3 bg-orange-50 text-orange-700 rounded-lg hover:bg-orange-100 transition">
                            <i class="fas fa-calendar"></i>
                            <span>View Same Day Entries</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
