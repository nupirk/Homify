@extends('layouts.app')

@section('title', 'Partner Statement Summary')

@section('content')
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-4 sm:mb-0">
                Statement Summary: {{ $data['partner']->first_name }} {{ $data['partner']->last_name }}
            </h1>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.ledger.partner.statement-form', $data['partner']->user_id) }}"
                    class="inline-flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors">
                    <i class="fas fa-file-pdf mr-2"></i> Generate PDF
                </a>
                <a href="{{ route('admin.ledger.user', $data['partner']->user_id) }}"
                    class="inline-flex items-center px-3 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-md transition-colors">
                    <i class="fas fa-list mr-2"></i> View Ledger
                </a>
            </div>
        </div>

        <!-- Period Information -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Statement Period</h3>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                    <div>
                        <div class="text-sm font-medium text-gray-500">From Date</div>
                        <div class="text-lg font-semibold text-gray-900">{{ $data['period']['from']->format('F j, Y') }}
                        </div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-500">To Date</div>
                        <div class="text-lg font-semibold text-gray-900">{{ $data['period']['to']->format('F j, Y') }}</div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-500">Total Days</div>
                        <div class="text-lg font-semibold text-gray-900">{{ $data['period']['days'] }} days</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Balance Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white shadow rounded-lg p-6 text-center">
                <div class="text-sm font-medium text-gray-500 mb-2">Opening Balance</div>
                <div class="text-2xl font-bold {{ $data['balances']['opening'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    NPR {{ number_format($data['balances']['opening'], 2) }}
                </div>
            </div>

            <div class="bg-white shadow rounded-lg p-6 text-center">
                <div class="text-sm font-medium text-gray-500 mb-2">Closing Balance</div>
                <div class="text-2xl font-bold {{ $data['balances']['closing'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    NPR {{ number_format($data['balances']['closing'], 2) }}
                </div>
            </div>

            <div class="bg-white shadow rounded-lg p-6 text-center">
                <div class="text-sm font-medium text-gray-500 mb-2">Net Change</div>
                <div
                    class="text-2xl font-bold {{ $data['balances']['net_change'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $data['balances']['net_change'] >= 0 ? '+' : '' }}NPR
                    {{ number_format($data['balances']['net_change'], 2) }}
                </div>
            </div>

            <div class="bg-white shadow rounded-lg p-6 text-center">
                <div class="text-sm font-medium text-gray-500 mb-2">Current Available</div>
                <div
                    class="text-2xl font-bold {{ $data['balances']['current_available'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    NPR {{ number_format($data['balances']['current_available'], 2) }}
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Period Activity Summary -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Period Activity Summary</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700">
                                Total Earnings ({{ $data['summary']['period']['earnings_count'] }} jobs)
                            </span>
                            <span class="text-lg font-semibold text-green-600">
                                +NPR {{ number_format($data['summary']['period']['total_earnings'], 2) }}
                            </span>
                        </div>

                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700">
                                COD Commission ({{ $data['summary']['period']['cod_jobs_count'] }} jobs)
                            </span>
                            <span class="text-lg font-semibold text-red-600">
                                -NPR {{ number_format($data['summary']['period']['total_cod_commission'], 2) }}
                            </span>
                        </div>

                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700">
                                Cash Settled (kept by partner)
                            </span>
                            <span class="text-lg font-semibold text-gray-600">
                                -NPR {{ number_format(abs($data['summary']['period']['total_cash_settled']), 2) }}
                            </span>
                        </div>

                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700">
                                Withdrawals ({{ $data['summary']['period']['withdrawal_count'] }} transactions)
                            </span>
                            <span class="text-lg font-semibold text-red-600">
                                -NPR {{ number_format($data['summary']['period']['total_withdrawals'], 2) }}
                            </span>
                        </div>

                        <div class="pt-4 border-t border-gray-200">
                            <div class="flex justify-between items-center">
                                <span class="text-base font-bold text-gray-900">Net Balance Change</span>
                                <span
                                    class="text-xl font-bold {{ $data['summary']['period']['net_earnings'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $data['summary']['period']['net_earnings'] >= 0 ? '+' : '' }}NPR
                                    {{ number_format($data['summary']['period']['net_earnings'], 2) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Method Breakdown -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Payment Method Breakdown</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-6">
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h4 class="font-medium text-green-800 mb-3">Online Jobs</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm text-green-700">Jobs Completed</span>
                                    <span
                                        class="font-semibold text-green-800">{{ $data['breakdown']['payment_methods']['online']['jobs_count'] }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-green-700">Total Earnings</span>
                                    <span class="font-semibold text-green-800">NPR
                                        {{ number_format($data['breakdown']['payment_methods']['online']['earnings_received'], 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-green-700">Avg per Job</span>
                                    <span class="font-semibold text-green-800">NPR
                                        {{ number_format($data['breakdown']['payment_methods']['online']['avg_earning'], 2) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-red-50 p-4 rounded-lg">
                            <h4 class="font-medium text-red-800 mb-3">COD Jobs</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm text-red-700">Jobs Completed</span>
                                    <span
                                        class="font-semibold text-red-800">{{ $data['breakdown']['payment_methods']['cod']['jobs_count'] }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-red-700">Commission Deducted</span>
                                    <span class="font-semibold text-red-800">NPR
                                        {{ number_format($data['breakdown']['payment_methods']['cod']['commission_deducted'], 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-red-700">Avg per Job</span>
                                    <span class="font-semibold text-red-800">NPR
                                        {{ number_format($data['breakdown']['payment_methods']['cod']['avg_commission'], 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Performance Analytics -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Performance Analytics</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700">Average Earning Per Job</span>
                            <span class="text-lg font-semibold text-blue-600">NPR
                                {{ number_format($data['analytics']['avg_earning_per_job'], 2) }}</span>
                        </div>

                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700">Effective Commission Rate</span>
                            <span
                                class="text-lg font-semibold text-orange-600">{{ number_format($data['analytics']['commission_rate'], 1) }}%</span>
                        </div>

                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700">Total Jobs Completed</span>
                            <span
                                class="text-lg font-semibold text-purple-600">{{ $data['summary']['period']['earnings_count'] + $data['summary']['period']['cod_jobs_count'] }}</span>
                        </div>

                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700">Transaction Count</span>
                            <span
                                class="text-lg font-semibold text-gray-600">{{ $data['transactions']['transaction_count'] }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- All-Time Summary -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">All-Time Summary</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700">Total Lifetime Earnings</span>
                            <span class="text-lg font-semibold text-green-600">NPR
                                {{ number_format($data['summary']['all_time']['total_earnings'], 2) }}</span>
                        </div>

                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700">Total COD Commission</span>
                            <span class="text-lg font-semibold text-red-600">NPR
                                {{ number_format($data['summary']['all_time']['total_cod_commission'], 2) }}</span>
                        </div>

                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700">Total Withdrawals</span>
                            <span class="text-lg font-semibold text-blue-600">NPR
                                {{ number_format($data['summary']['all_time']['total_withdrawals'], 2) }}</span>
                        </div>

                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700">Member Since</span>
                            <span class="text-lg font-semibold text-gray-600">
                                @if ($data['summary']['all_time']['first_transaction'])
                                    {{ \Carbon\Carbon::parse($data['summary']['all_time']['first_transaction'])->format('M j, Y') }}
                                @else
                                    No transactions yet
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($data['transactions']['recent_payouts']->count() > 0)
            <!-- Recent Payouts -->
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Recent Payouts (Period)</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Method</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Reference</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($data['transactions']['recent_payouts'] as $payout)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $payout->created_at->format('M j, Y') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-red-600">NPR
                                        {{ number_format($payout->amount, 2) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ ucfirst(str_replace('_', ' ', $payout->payment_method)) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    @if ($payout->status === 'completed') bg-green-100 text-green-800
                                    @elseif($payout->status === 'pending') bg-yellow-100 text-yellow-800
                                    @else bg-red-100 text-red-800 @endif">
                                            {{ ucfirst($payout->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $payout->transaction_reference ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
