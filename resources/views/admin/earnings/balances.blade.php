{{-- filepath: c:\Users\kchnp\Desktop\Urban\be\resources\views\admin\earnings\balances.blade.php --}}
@extends('layouts.app')

@section('title', 'Partner Balances')

@section('content')
    <div class="p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Partner Balances</h1>
                <p class="text-gray-600 mt-1">Manage partner earnings balances and settlements</p>
            </div>
            <div class="flex space-x-2">
                <form action="{{ route('admin.earnings.balances.recalculate-all') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit"
                        onclick="return confirm('Are you sure you want to recalculate all partner balances? This may take a while.')"
                        class="inline-flex items-center px-4 py-2 bg-orange-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-orange-700 transition">
                        <i class="fas fa-calculator mr-2"></i>
                        Recalculate All
                    </button>
                </form>
                <a href="{{ route('admin.earnings.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Earnings
                </a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-users text-blue-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <div class="text-2xl font-bold text-gray-900">{{ $balances->count() }}</div>
                        <div class="text-sm text-gray-600">Total Partners</div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-wallet text-green-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <div class="text-2xl font-bold text-green-600">
                            Rs {{ $balances->sum('available_balance') }}</div>
                        <div class="text-sm text-gray-600">Available Balance</div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-clock text-yellow-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <div class="text-2xl font-bold text-yellow-600">
                            Rs {{ $balances->sum('pending_balance') }}</div>
                        <div class="text-sm text-gray-600">Pending Balance</div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-coins text-purple-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <div class="text-2xl font-bold text-purple-600">
                            Rs {{ $balances->sum('total_earnings') }}</div>
                        <div class="text-sm text-gray-600">Total Earnings</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="p-6">
                <form method="GET" action="{{ route('admin.earnings.balances') }}"
                    class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700">Search Partner</label>
                        <input type="text" id="search" name="search" value="{{ request('search') }}"
                            placeholder="Partner name..."
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="min_balance" class="block text-sm font-medium text-gray-700">Min Available
                            Balance</label>
                        <input type="number" id="min_balance" name="min_balance" value="{{ request('min_balance') }}"
                            min="0" step="0.01" placeholder="0.00"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="status_filter" class="block text-sm font-medium text-gray-700">Status</label>
                        <select id="status_filter" name="status_filter"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All Statuses</option>
                            <option value="has_available"
                                {{ request('status_filter') === 'has_available' ? 'selected' : '' }}>Has Available Balance
                            </option>
                            <option value="has_pending" {{ request('status_filter') === 'has_pending' ? 'selected' : '' }}>
                                Has Pending Balance</option>
                            <option value="zero_balance"
                                {{ request('status_filter') === 'zero_balance' ? 'selected' : '' }}>Zero Balance</option>
                        </select>
                    </div>

                    <div class="flex items-end">
                        <button type="submit"
                            class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                            <i class="fas fa-search mr-2"></i>
                            Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Balances Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Partner Balances</h3>
            </div>

            @if ($balances->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Partner</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Available Balance</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Pending Balance</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Total Earnings</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Total Settled</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Last Updated</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($balances as $balance)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    <a href="{{ route('admin.partners.show', $balance->partner->user) }}"
                                                        class="text-blue-600 hover:text-blue-800">
                                                        {{ $balance->partner->user->name }}
                                                    </a>
                                                </div>
                                                <div class="text-sm text-gray-500">{{ $balance->partner->user->email }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div
                                            class="text-sm font-medium {{ $balance->available_balance < 0 ? 'text-red-600' : 'text-green-600' }}">
                                            Rs {{ $balance->available_balance }}</div>
                                        @if ($balance->available_balance < 0)
                                            <div class="text-xs text-red-500">Owed to platform (COD commission)</div>
                                        @elseif ($balance->available_balance > 0)
                                            <div class="text-xs text-gray-500">Ready for settlement</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-yellow-600">
                                            Rs {{ $balance->pending_balance }}</div>
                                        @if ($balance->pending_balance > 0)
                                            <div class="text-xs text-gray-500">Awaiting completion</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            Rs {{ $balance->total_earnings }}</div>
                                        <div class="text-xs text-gray-500">Lifetime earnings</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            Rs {{ $balance->total_withdrawn }}</div>
                                        <div class="text-xs text-gray-500">Already paid out</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $balance->updated_at->format('M d, Y') }}
                                        <div class="text-xs text-gray-400">{{ $balance->updated_at->diffForHumans() }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                        <form
                                            action="{{ route('admin.earnings.balances.recalculate', $balance->partner_id) }}"
                                            method="POST" class="inline">
                                            @csrf
                                            <button type="submit"
                                                onclick="return confirm('Are you sure you want to recalculate this partner\'s balance?')"
                                                class="text-orange-600 hover:text-orange-900" title="Recalculate Balance">
                                                <i class="fas fa-calculator"></i>
                                            </button>
                                        </form>
                                        <a href="{{ route('admin.earnings.partner', $balance->partner_id) }}"
                                            class="text-blue-600 hover:text-blue-900" title="View Earnings">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.partners.show', $balance->partner->user) }}"
                                            class="text-green-600 hover:text-green-900" title="View Partner">
                                            <i class="fas fa-user"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-3 border-t border-gray-200">
                    {{ $balances->withQueryString()->links() }}
                </div>
            @else
                <div class="p-6 text-center">
                    <i class="fas fa-wallet text-gray-400 text-3xl mb-2"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Partner Balances</h3>
                    <p class="text-gray-500 mb-4">No partner balances found matching your criteria.</p>
                </div>
            @endif
        </div>

        <!-- Information Cards -->
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">Available Balance</h3>
                        <p class="text-sm text-gray-600 mt-1">Earnings from completed orders that are ready for settlement
                            to partners.</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-clock text-yellow-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">Pending Balance</h3>
                        <p class="text-sm text-gray-600 mt-1">Earnings from orders that are still in progress or pending
                            completion.</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-sync text-green-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">Auto-Recalculation</h3>
                        <p class="text-sm text-gray-600 mt-1">Balances are automatically updated when orders change status
                            or new earnings are processed.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
