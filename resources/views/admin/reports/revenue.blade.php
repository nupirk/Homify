@extends('layouts.app')

@section('title', 'Revenue Report')

@section('content')
    <div class="p-6">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Revenue</h1>
                <p class="text-gray-600 mt-1">
                    {{ $from->format('M d, Y') }} – {{ $to->format('M d, Y') }} · platform fees, tax owed and promo spend from the ledger
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('admin.reports.revenue.export', ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]) }}"
                    class="inline-flex items-center px-4 py-2 bg-green-600 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 transition">
                    <i class="fas fa-download mr-2"></i>
                    Export CSV
                </a>
                <a href="{{ route('admin.reports.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Reports
                </a>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.reports.revenue') }}"
            class="bg-white rounded-lg shadow p-6 mb-6 flex flex-wrap items-end gap-4">
            <div>
                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">From</label>
                <input type="date" id="date_from" name="date_from" value="{{ $from->toDateString() }}"
                    class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">To</label>
                <input type="date" id="date_to" name="date_to" value="{{ $to->toDateString() }}"
                    class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="submit"
                class="inline-flex items-center px-4 py-2 bg-blue-600 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                <i class="fas fa-filter mr-2"></i>
                Apply
            </button>
        </form>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ledger type</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Entries</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($rows as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @switch($row->type)
                                        @case('PLATFORM_FEE')
                                            Platform fee
                                            @break
                                        @case('TAX_PAYABLE')
                                            Tax owed
                                            @break
                                        @default
                                            Promo discount funded
                                    @endswitch
                                    <span class="text-gray-400 text-xs">({{ $row->type }})</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-700">{{ $row->entries }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900">
                                    NPR {{ number_format((float) $row->total, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-10 text-center text-sm text-gray-500">
                                    No revenue posted in this range.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @php
                        $revenue = (float) $rows->whereIn('type', ['PLATFORM_FEE', 'TAX_PAYABLE'])->sum('total');
                        $discounts = (float) $rows->where('type', 'DISCOUNT_EXPENSE')->sum('total');
                    @endphp
                    <tfoot class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Total</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold text-gray-900">{{ $rows->sum('entries') }}</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold text-gray-900">
                                NPR {{ number_format((float) $rows->sum('total'), 2) }}
                            </th>
                        </tr>
                        <tr>
                            <th colspan="2" class="px-6 py-2 text-left text-sm font-medium text-gray-700">Revenue (fee + tax)</th>
                            <th class="px-6 py-2 text-right text-sm font-medium text-gray-700">NPR {{ number_format($revenue, 2) }}</th>
                        </tr>
                        <tr>
                            <th colspan="2" class="px-6 py-2 text-left text-sm font-medium text-gray-700">Promo discounts funded</th>
                            <th class="px-6 py-2 text-right text-sm font-medium text-gray-700">NPR {{ number_format($discounts, 2) }}</th>
                        </tr>
                        <tr>
                            <th colspan="2" class="px-6 py-4 text-left text-sm font-bold text-gray-900">Net revenue</th>
                            <th class="px-6 py-4 text-right text-sm font-bold text-gray-900">NPR {{ number_format($revenue - $discounts, 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
@endsection
