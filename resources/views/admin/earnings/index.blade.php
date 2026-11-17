@extends('layouts.app')

@section('title', 'Earnings Statement - Admin')

@section('content')
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Earnings Statement</h1>
                <p class="text-gray-600">Generate comprehensive earnings reports and statements</p>
            </div>

            <!-- Export Button -->
            <button id="exportStatement"
                class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg flex items-center space-x-2">
                <i class="fas fa-file-pdf"></i>
                <span>Export Statement</span>
            </button>
        </div>

        <!-- 3-Part View Toggle Switch -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-center mb-6">
                <div class="bg-gray-100 p-1 rounded-lg inline-flex">
                    <button onclick="switchView('all')" id="toggle-all"
                        class="view-toggle px-6 py-2 rounded-md text-sm font-medium transition-all {{ $viewType == 'all' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        <i class="fas fa-chart-pie mr-2"></i>All Transactions
                    </button>
                    <button onclick="switchView('partners')" id="toggle-partners"
                        class="view-toggle px-6 py-2 rounded-md text-sm font-medium transition-all {{ $viewType == 'partners' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        <i class="fas fa-users mr-2"></i>Partners Only
                    </button>
                    <button onclick="switchView('company')" id="toggle-company"
                        class="view-toggle px-6 py-2 rounded-md text-sm font-medium transition-all {{ $viewType == 'company' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        <i class="fas fa-building mr-2"></i>Company Only
                    </button>
                </div>
            </div>

            <!-- Advanced Filters -->
            <form method="GET" action="{{ route('admin.earnings.index') }}" id="filterForm">
                <input type="hidden" name="view_type" id="viewTypeInput" value="{{ $viewType }}">

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                    <!-- Date Range -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                        <input type="date" name="date_from" value="{{ request('date_from') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                        <input type="date" name="date_to" value="{{ request('date_to') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <!-- Partner Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Specific Partner</label>
                        <select name="partner_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Partners</option>
                            @foreach ($partners as $partner)
                                <option value="{{ $partner->user_id }}"
                                    {{ request('partner_id') == $partner->user_id ? 'selected' : '' }}>
                                    {{ $partner->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Service Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Service Type</label>
                        <select name="service_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Services</option>
                            @foreach ($services as $service)
                                <option value="{{ $service->service_id }}"
                                    {{ request('service_id') == $service->service_id ? 'selected' : '' }}>
                                    {{ $service->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex space-x-2">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg flex-1">
                            <i class="fas fa-filter mr-1"></i>Filter
                        </button>
                        <a href="{{ route('admin.earnings.index') }}"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg">
                            <i class="fas fa-times"></i>
                        </a>
                        <button type="submit" name="generate_statement" value="1"
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-file-alt"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Earnings Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Partner Earnings</p>
                        <p class="text-2xl font-bold text-gray-900">Rs {{ number_format($totalEarnings, 2) }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ $totalTransactions }} transactions</p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                        <i class="fas fa-rupee-sign text-blue-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Platform Revenue</p>
                        <p class="text-2xl font-bold text-green-600">Rs {{ number_format($platformRevenue, 2) }}</p>
                        <p class="text-xs text-gray-500 mt-1">From platform fees</p>
                    </div>
                    <div class="bg-green-100 p-3 rounded-full">
                        <i class="fas fa-users text-green-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Transaction Count</p>
                        <p class="text-2xl font-bold text-purple-600">{{ $totalTransactions }}</p>
                        <p class="text-xs text-gray-500 mt-1">Partner earning entries</p>
                    </div>
                    <div class="bg-purple-100 p-3 rounded-full">
                        <i class="fas fa-building text-purple-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Avg per Transaction</p>
                        <p class="text-2xl font-bold text-indigo-600">
                            Rs {{ $totalTransactions > 0 ? number_format($totalEarnings / $totalTransactions, 2) : '0' }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">Average earning</p>
                    </div>
                    <div class="bg-indigo-100 p-3 rounded-full">
                        <i class="fas fa-chart-line text-indigo-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statement Data -->
        @if (isset($statementData) && $statementData)
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Generated Statement</h3>
                    <span class="text-sm text-gray-500">
                        {{ $statementData['date_from'] ? 'From: ' . date('M d, Y', strtotime($statementData['date_from'])) : '' }}
                        {{ $statementData['date_to'] ? ' To: ' . date('M d, Y', strtotime($statementData['date_to'])) : '' }}
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-blue-600">{{ $statementData['transaction_count'] }}</p>
                        <p class="text-sm text-gray-600">Total Transactions</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-gray-900">Rs {{ $statementData['total_gross'] }}
                        </p>
                        <p class="text-sm text-gray-600">Gross Revenue</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-green-600">
                            Rs {{ $statementData['total_partner_earnings'] }}</p>
                        <p class="text-sm text-gray-600">Partner Share</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-purple-600">
                            Rs {{ $statementData['total_platform_earnings'] }}</p>
                        <p class="text-sm text-gray-600">Company Share</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Detailed Transactions -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900">
                    @if ($viewType == 'partners')
                        Partner Earnings Details
                    @elseif($viewType == 'company')
                        Company Earnings Details
                    @else
                        All Earnings Details
                    @endif
                </h2>
                <span class="text-sm text-gray-500">{{ $earnings->total() }} total records</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Order ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Partner</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Service</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total Amount</th>
                            @if ($viewType == 'all' || $viewType == 'company')
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Company Fees</th>
                            @endif
                            @if ($viewType == 'all' || $viewType == 'partners')
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Partner Earnings</th>
                            @endif
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Transaction Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($earnings as $earning)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $earning->booking_id ? substr($earning->booking_id, 0, 12) . '...' : 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $earning->created_at ? $earning->created_at->format('M d, Y') : 'Unknown' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $earning->user->name ?? 'Unknown Partner' }}
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $earning->order?->orderItems?->first()?->service?->name ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    Rs {{ number_format($earning->amount, 2) }}
                                </td>
                                @if ($viewType == 'all' || $viewType == 'company')
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-purple-600 font-medium">
                                        Platform Fee
                                    </td>
                                @endif
                                @if ($viewType == 'all' || $viewType == 'partners')
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium">
                                        Rs {{ number_format($earning->amount, 2) }}
                                    </td>
                                @endif
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $earning->type }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <div class="flex space-x-2">
                                        <a href="{{ route('admin.earnings.show', $earning->id) }}"
                                            class="text-blue-600 hover:text-blue-900" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @if ($earning->user)
                                            <a href="{{ route('admin.partners.show', $earning->user) }}"
                                                class="text-green-600 hover:text-green-900" title="View Partner">
                                                <i class="fas fa-user"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <i class="fas fa-chart-line text-4xl text-gray-300 mb-4"></i>
                                        <p class="text-lg font-medium">No earnings data found</p>
                                        <p class="text-sm">Try adjusting your filters or date range</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($earnings->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $earnings->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- JavaScript for Toggle and Export -->
    <script>
        function switchView(viewType) {
            // Update the hidden input
            document.getElementById('viewTypeInput').value = viewType;

            // Update button states
            document.querySelectorAll('.view-toggle').forEach(btn => {
                btn.classList.remove('bg-blue-600', 'text-white', 'shadow-sm');
                btn.classList.add('text-gray-500', 'hover:text-gray-700');
            });

            document.getElementById('toggle-' + viewType).classList.remove('text-gray-500', 'hover:text-gray-700');
            document.getElementById('toggle-' + viewType).classList.add('bg-blue-600', 'text-white', 'shadow-sm');

            // Submit the form to reload with new view
            document.getElementById('filterForm').submit();
        }

        // Export functionality
        document.getElementById('exportStatement').addEventListener('click', function() {
            // Create a form to submit with current filters and generate_statement=1
            const form = document.getElementById('filterForm');
            const exportInput = document.createElement('input');
            exportInput.type = 'hidden';
            exportInput.name = 'generate_statement';
            exportInput.value = '1';
            form.appendChild(exportInput);

            // Create export format input
            const formatInput = document.createElement('input');
            formatInput.type = 'hidden';
            formatInput.name = 'export_format';
            formatInput.value = 'csv'; // Changed to CSV for testing
            form.appendChild(formatInput);

            form.submit();
        });

        // Auto-submit on filter changes for better UX
        document.addEventListener('DOMContentLoaded', function() {
            const filterInputs = ['date_from', 'date_to', 'partner_id', 'service_id'];

            filterInputs.forEach(inputName => {
                const input = document.querySelector(`[name="${inputName}"]`);
                if (input) {
                    input.addEventListener('change', function() {
                        // Add a small delay to allow multiple quick changes
                        clearTimeout(this.debounceTimer);
                        this.debounceTimer = setTimeout(() => {
                            document.getElementById('filterForm').submit();
                        }, 300);
                    });
                }
            });
        });
    </script>
@endsection
