@extends('layouts.app')

@section('title', 'Partner Balance Report')

@section('content')
    <div class="p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Partner Balance Report</h1>
                <p class="text-gray-600 mt-1">
                    Cached partner balances compared against the ledger. Differences over NPR 0.01 are listed below.
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('admin.ledger.reconciliation') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                    <i class="fas fa-balance-scale mr-2"></i>
                    Reconciliation
                </a>
                <a href="{{ route('admin.ledger.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Ledger
                </a>
            </div>
        </div>

        @if (count($inconsistencies) > 0)
            <div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p class="text-sm text-yellow-800">
                    <strong>{{ count($inconsistencies) }}</strong> partner balance(s) disagree with the ledger.
                    Recalculate the affected balances before paying out.
                </p>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Partner</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ledger Earnings</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cached Earnings</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ledger Withdrawn</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cached Withdrawn</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ledger Available</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cached Available</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($inconsistencies as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-4 whitespace-nowrap text-sm">
                                        <div class="font-medium text-gray-900">
                                            {{ $row['partner']->first_name }} {{ $row['partner']->last_name }}
                                        </div>
                                        <div class="text-gray-500">{{ $row['partner']->email }}</div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                                        {{ number_format($row['actual_earnings'], 2) }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-right {{ abs($row['actual_earnings'] - $row['cached_earnings']) > 0.01 ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                        {{ number_format($row['cached_earnings'], 2) }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                                        {{ number_format($row['actual_withdrawals'], 2) }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-right {{ abs($row['actual_withdrawals'] - $row['cached_withdrawals']) > 0.01 ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                        {{ number_format($row['cached_withdrawals'], 2) }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                                        {{ number_format($row['actual_available'], 2) }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-right {{ abs($row['actual_available'] - $row['cached_available']) > 0.01 ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                        {{ number_format($row['cached_available'], 2) }}
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm">
                                        <a href="{{ route('admin.ledger.user', $row['partner']->user_id) }}"
                                            class="text-blue-600 hover:underline">Ledger</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="bg-white rounded-lg shadow px-6 py-12 text-center">
                <i class="fas fa-check-circle text-4xl text-green-400 mb-3"></i>
                <p class="text-gray-700 font-medium">Every cached partner balance matches the ledger.</p>
                <p class="text-sm text-gray-500 mt-1">No action needed.</p>
            </div>
        @endif
    </div>
@endsection
