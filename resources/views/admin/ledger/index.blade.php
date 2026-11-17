@extends('layouts.app')

@section('title', 'Ledger Entries')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-4 sm:mb-0">Ledger Entries</h1>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.ledger.reconciliation') }}"
                    class="inline-flex items-center px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md transition-colors">
                    <i class="fas fa-balance-scale mr-2"></i> Reconciliation
                </a>
                <a href="{{ route('admin.ledger.analytics') }}"
                    class="inline-flex items-center px-3 py-2 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium rounded-md transition-colors">
                    <i class="fas fa-chart-bar mr-2"></i> Analytics
                </a>
                <a href="{{ route('admin.ledger.export', request()->query()) }}"
                    class="inline-flex items-center px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md transition-colors">
                    <i class="fas fa-download mr-2"></i> Export CSV
                </a>
                <a href="{{ route('admin.ledger.balances') }}"
                    class="inline-flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors">
                    <i class="fas fa-wallet mr-2"></i> User Balances
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Filters</h3>
            </div>
            <div class="p-4">
                <form method="GET" action="{{ route('admin.ledger.index') }}">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4 mb-4">
                        <div>
                            <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1">User</label>
                            <select name="user_id" id="user_id"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Users</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->user_id }}"
                                        {{ request('user_id') == $user->user_id ? 'selected' : '' }}>
                                        {{ $user->first_name }} {{ $user->last_name }} ({{ $user->email }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="booking_id" class="block text-sm font-medium text-gray-700 mb-1">Booking ID</label>
                            <input type="text" name="booking_id" id="booking_id"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                value="{{ request('booking_id') }}" placeholder="Order ID">
                        </div>

                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Entry Type</label>
                            <select name="type" id="type"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Types</option>
                                @foreach ($entryTypes as $type)
                                    <option value="{{ $type }}" {{ request('type') == $type ? 'selected' : '' }}>
                                        {{ str_replace('_', ' ', $type) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                            <input type="date" name="date_from" id="date_from"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                value="{{ request('date_from') }}">
                        </div>

                        <div>
                            <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                            <input type="date" name="date_to" id="date_to"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                value="{{ request('date_to') }}">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">&nbsp;</label>
                            <button type="submit"
                                class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors">Filter</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label for="amount_min" class="block text-sm font-medium text-gray-700 mb-1">Min Amount</label>
                            <input type="number" name="amount_min" id="amount_min"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                value="{{ request('amount_min') }}" step="0.01">
                        </div>

                        <div>
                            <label for="amount_max" class="block text-sm font-medium text-gray-700 mb-1">Max Amount</label>
                            <input type="number" name="amount_max" id="amount_max"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                value="{{ request('amount_max') }}" step="0.01">
                        </div>

                        <div>
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search
                                Narration</label>
                            <input type="text" name="search" id="search"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                value="{{ request('search') }}" placeholder="Search in descriptions">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">&nbsp;</label>
                            <a href="{{ route('admin.ledger.index') }}"
                                class="w-full inline-flex items-center justify-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-md transition-colors">Clear</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-list text-2xl text-blue-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Entries</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ number_format($stats['total_entries']) }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-dollar-sign text-2xl text-green-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Amount</dt>
                                <dd class="text-lg font-medium text-gray-900">Rs
                                    {{ number_format($stats['total_amount'], 2) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-users text-2xl text-blue-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Unique Users</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ number_format($stats['unique_users']) }}
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-shopping-cart text-2xl text-yellow-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Unique Orders</dt>
                                <dd class="text-lg font-medium text-gray-900">
                                    {{ number_format($stats['unique_bookings']) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Entry Types Breakdown -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Entry Types Breakdown</h3>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($stats['by_type'] as $typeStats)
                        <div class="bg-gray-50 rounded-lg p-4 border-l-4 border-blue-500">
                            <div class="text-sm font-medium text-gray-900 mb-1">
                                {{ str_replace('_', ' ', $typeStats->type) }}
                            </div>
                            <div class="text-sm text-gray-600">
                                <strong>{{ number_format($typeStats->count) }}</strong> entries
                                • <strong>Rs {{ number_format($typeStats->total, 2) }}</strong>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Ledger Entries Table -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Ledger Entries</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Booking ID</th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Amount</th>
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Balance After</th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Narration</th>
                            <th scope="col"
                                class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($ledgerEntries as $entry)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $entry->id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $entry->created_at->format('Y-m-d H:i:s') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if ($entry->booking_id)
                                        <a href="{{ route('admin.ledger.order', $entry->booking_id) }}"
                                            class="text-blue-600 hover:text-blue-900">{{ $entry->booking_id }}</a>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if ($entry->user)
                                        <a href="{{ route('admin.ledger.user', $entry->user_id) }}"
                                            class="text-blue-600 hover:text-blue-900">
                                            {{ $entry->user->first_name }} {{ $entry->user->last_name }}
                                        </a>
                                        <div class="text-xs text-gray-500">{{ $entry->user->email }}</div>
                                    @else
                                        <span class="text-gray-400">User #{{ $entry->user_id }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                        @if ($entry->type == 'CUSTOMER_DEBIT') bg-red-100 text-red-800
                                        @elseif($entry->type == 'PARTNER_EARNING') bg-green-100 text-green-800
                                        @elseif($entry->type == 'PLATFORM_FEE') bg-blue-100 text-blue-800
                                        @else bg-gray-100 text-gray-800 @endif">
                                        {{ str_replace('_', ' ', $entry->type) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium">Rs
                                    {{ number_format($entry->amount, 2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    @if ($entry->balance_after !== null)
                                        Rs {{ number_format($entry->balance_after, 2) }}
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div class="max-w-xs truncate">{{ $entry->narration }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <a href="{{ route('admin.ledger.show', $entry->id) }}"
                                        class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-12 text-center text-sm text-gray-500">No ledger entries
                                    found</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($ledgerEntries->count() > 0)
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="5" class="px-6 py-3 text-right text-sm font-medium text-gray-900">Page
                                    Total:</td>
                                <td class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                    Rs {{ number_format($pageTotals['page_total'], 2) }}
                                </td>
                                <td colspan="3"></td>
                            </tr>
                            @if ($stats['total_amount'] != $pageTotals['page_total'])
                                <tr>
                                    <td colspan="5" class="px-6 py-3 text-right text-sm font-medium text-gray-900">
                                        Filtered Total:</td>
                                    <td class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                        Rs {{ number_format($stats['total_amount'], 2) }}
                                    </td>
                                    <td colspan="3"></td>
                                </tr>
                            @endif
                        </tfoot>
                    @endif
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                <div class="flex-1 flex justify-between sm:hidden">
                    {{ $ledgerEntries->links() }}
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing {{ $ledgerEntries->firstItem() ?? 0 }} to {{ $ledgerEntries->lastItem() ?? 0 }}
                            of {{ $ledgerEntries->total() }} entries
                        </p>
                    </div>
                    <div>
                        {{ $ledgerEntries->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        // Auto-submit form when select changes
        document.querySelectorAll('select[name="user_id"], select[name="type"]').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
@endsection
