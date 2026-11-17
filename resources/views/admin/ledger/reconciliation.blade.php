@extends('layouts.app')

@section('title', 'Ledger Reconciliation')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-4 sm:mb-0">Ledger Reconciliation</h1>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.ledger.index') }}" class="inline-flex items-center px-3 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-md transition-colors">
                    <i class="fas fa-list mr-2"></i> View Ledger
                </a>
                <a href="{{ route('admin.ledger.analytics') }}" class="inline-flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors">
                    <i class="fas fa-chart-bar mr-2"></i> Analytics
                </a>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Date Range Filter</h3>
            </div>
            <div class="p-4">
                <form method="GET" action="{{ route('admin.ledger.reconciliation') }}">
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
                                Update Reconciliation
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-receipt text-2xl text-blue-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Bookings</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ number_format($summary['total_bookings']) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Discrepancies Found</dt>
                                <dd class="text-lg font-medium text-red-600">{{ number_format($summary['discrepancies_count']) }}</dd>
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
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Customer Payments</dt>
                                <dd class="text-lg font-medium text-gray-900">Rs {{ number_format($summary['total_customer_payments'], 2) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-chart-line text-2xl text-yellow-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Variance</dt>
                                <dd class="text-lg font-medium text-yellow-600">Rs {{ number_format($summary['total_variance'], 2) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Discrepancies Table -->
        @if(count($discrepancies) > 0)
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-red-600">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Found {{ count($discrepancies) }} Discrepancies
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-red-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booking ID</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Customer Paid</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Service Amount</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Partner Earned</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Platform Fee</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Expected Total</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Variance</th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($discrepancies as $discrepancy)
                                <tr class="hover:bg-red-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <a href="{{ route('admin.ledger.order', $discrepancy['booking_id']) }}" class="text-blue-600 hover:text-blue-900">
                                            {{ $discrepancy['booking_id'] }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        Rs {{ number_format($discrepancy['customer_paid'], 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        Rs {{ number_format($discrepancy['service_amount'], 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        Rs {{ number_format($discrepancy['partner_earned'], 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        Rs {{ number_format($discrepancy['platform_fee'], 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        Rs {{ number_format($discrepancy['expected_total'], 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-red-600 text-right">
                                        Rs {{ number_format($discrepancy['variance'], 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                        <a href="{{ route('admin.ledger.order', $discrepancy['booking_id']) }}" 
                                           class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-search"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-medium text-green-800">All Clear!</h3>
                        <p class="text-green-700">No discrepancies found in the selected date range. All transactions are properly balanced.</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- All Bookings Summary -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">All Bookings Summary</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booking ID</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Entries</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Customer Paid</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tax Collected</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Partner Earned</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Platform Fee</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Flow</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($bookingsWithEntries as $booking)
                            @php
                                $serviceAmount = $booking->customer_paid - $booking->tax_collected;
                                $expectedTotal = $booking->partner_earned + $booking->platform_fee;
                                $variance = abs($serviceAmount - $expectedTotal);
                                $isBalanced = $variance < 0.01;
                            @endphp
                            <tr class="hover:bg-gray-50 {{ !$isBalanced ? 'bg-red-50' : '' }}">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <a href="{{ route('admin.ledger.order', $booking->booking_id) }}" class="text-blue-600 hover:text-blue-900">
                                        {{ $booking->booking_id }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    {{ $booking->entry_count }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    Rs {{ number_format($booking->customer_paid, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    Rs {{ number_format($booking->tax_collected, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    Rs {{ number_format($booking->partner_earned, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    Rs {{ number_format($booking->platform_fee, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    Rs {{ number_format($booking->total_flow, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if($isBalanced)
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            <i class="fas fa-check mr-1"></i> Balanced
                                        </span>
                                    @else
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            <i class="fas fa-exclamation-triangle mr-1"></i> Variance: Rs {{ number_format($variance, 2) }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500">No bookings found for selected period</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($bookingsWithEntries->count() > 0)
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td class="px-6 py-3 text-right text-sm font-medium text-gray-900">Totals:</td>
                                <td class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                    {{ $bookingsWithEntries->sum('entry_count') }}
                                </td>
                                <td class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                    Rs {{ number_format($summary['total_customer_payments'], 2) }}
                                </td>
                                <td class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                    Rs {{ number_format($summary['total_tax_collected'], 2) }}
                                </td>
                                <td class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                    Rs {{ number_format($summary['total_partner_earnings'], 2) }}
                                </td>
                                <td class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                    Rs {{ number_format($summary['total_platform_fees'], 2) }}
                                </td>
                                <td class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                    Rs {{ number_format($bookingsWithEntries->sum('total_flow'), 2) }}
                                </td>
                                <td class="px-6 py-3 text-center text-sm font-bold text-gray-900">
                                    @if($summary['discrepancies_count'] == 0)
                                        <span class="text-green-600">All Balanced</span>
                                    @else
                                        <span class="text-red-600">{{ $summary['discrepancies_count'] }} Issues</span>
                                    @endif
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
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
