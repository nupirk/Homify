@extends('layouts.app')

@section('title', 'Ledger Analytics')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-4 sm:mb-0">Ledger Analytics</h1>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.ledger.index') }}" class="inline-flex items-center px-3 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-md transition-colors">
                    <i class="fas fa-list mr-2"></i> View Ledger
                </a>
                <a href="{{ route('admin.ledger.balances') }}" class="inline-flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors">
                    <i class="fas fa-wallet mr-2"></i> User Balances
                </a>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Date Range Filter</h3>
            </div>
            <div class="p-4">
                <form method="GET" action="{{ route('admin.ledger.analytics') }}">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                            <input type="date" name="date_from" id="date_from" 
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                value="{{ $dateFrom }}">
                        </div>
                        <div>
                            <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                            <input type="date" name="date_to" id="date_to" 
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                value="{{ $dateTo }}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">&nbsp;</label>
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors">
                                Update Analytics
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Platform Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-dollar-sign text-2xl text-green-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Revenue</dt>
                                <dd class="text-lg font-medium text-gray-900">Rs {{ number_format($platformMetrics['total_revenue'], 2) }}</dd>
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
                                <dt class="text-sm font-medium text-gray-500 truncate">Partner Earnings</dt>
                                <dd class="text-lg font-medium text-gray-900">Rs {{ number_format($platformMetrics['total_partner_earnings'], 2) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-receipt text-2xl text-yellow-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Tax Collected</dt>
                                <dd class="text-lg font-medium text-gray-900">Rs {{ number_format($platformMetrics['total_tax_collected'], 2) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-shopping-cart text-2xl text-purple-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Orders</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ number_format($totalOrders) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-chart-line text-2xl text-indigo-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Average Order Value</dt>
                                <dd class="text-lg font-medium text-gray-900">Rs {{ number_format($platformMetrics['average_order_value'], 2) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-percentage text-2xl text-red-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Commission Rate</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ number_format($platformMetrics['platform_commission_rate'], 2) }}%</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue Breakdown -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Revenue Breakdown by Type</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction Type</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Count</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Average</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($revenueBreakdown as $breakdown)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ str_replace('_', ' ', $breakdown->type) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    {{ number_format($breakdown->count) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    Rs {{ number_format($breakdown->total, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    Rs {{ number_format($breakdown->average, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500">No revenue data found for selected period</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Users -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Top Users by Transaction Volume</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($topUsers as $user)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($user->user)
                                        <div class="font-medium text-gray-900">{{ $user->user->first_name }} {{ $user->user->last_name }}</div>
                                        <div class="text-gray-500">{{ $user->user->email }}</div>
                                    @else
                                        <div class="text-gray-400">User #{{ $user->user_id }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    {{ number_format($user->transaction_count) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    Rs {{ number_format($user->total_amount, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-12 text-center text-sm text-gray-500">No user data found for selected period</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Daily Statistics -->
        @if($dailyStats->count() > 0)
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Daily Transaction Statistics</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                @foreach ($dailyStats->groupBy('type')->keys() as $type)
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ str_replace('_', ' ', $type) }}</th>
                                @endforeach
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($dailyStats->groupBy('date') as $date => $dayStats)
                                @php
                                    $dayTotal = $dayStats->sum('total_amount');
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ \Carbon\Carbon::parse($date)->format('M j, Y') }}</td>
                                    @foreach ($dailyStats->groupBy('type')->keys() as $type)
                                        @php
                                            $typeData = $dayStats->firstWhere('type', $type);
                                        @endphp
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            @if ($typeData)
                                                Rs {{ number_format($typeData->total_amount, 2) }}
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 text-right">
                                        Rs {{ number_format($dayTotal, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection

@section('scripts')
    <script>
        // Auto-submit form when date changes
        document.querySelectorAll('input[name="date_from"], input[name="date_to"]').forEach(input => {
            input.addEventListener('change', function() {
                // Optional: Add debounce or auto-submit logic here
            });
        });
    </script>
@endsection
