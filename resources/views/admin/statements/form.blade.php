@extends('layouts.app')

@section('title', 'Generate Partner Statement')

@section('content')
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-4 sm:mb-0">Generate Partner Statement</h1>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.ledger.user', $partner->user_id) }}"
                    class="inline-flex items-center px-3 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-md transition-colors">
                    <i class="fas fa-list mr-2"></i> View Ledger
                </a>
                <a href="{{ route('admin.ledger.index') }}"
                    class="inline-flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Ledger
                </a>
            </div>
        </div>

        <!-- Partner Information Card -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Partner Information</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <div class="mb-4">
                            <span class="text-sm font-medium text-gray-500">Name:</span>
                            <div class="mt-1 text-lg font-semibold text-gray-900">{{ $partner->first_name }}
                                {{ $partner->last_name }}</div>
                        </div>
                        <div class="mb-4">
                            <span class="text-sm font-medium text-gray-500">Email:</span>
                            <div class="mt-1 text-gray-900">{{ $partner->email }}</div>
                        </div>
                        <div class="mb-4">
                            <span class="text-sm font-medium text-gray-500">Partner ID:</span>
                            <div class="mt-1 text-gray-900">#{{ $partner->user_id }}</div>
                        </div>
                    </div>
                    <div>
                        @if ($partnerBalance)
                            <div class="mb-4">
                                <span class="text-sm font-medium text-gray-500">Current Available Balance:</span>
                                <div
                                    class="mt-1 text-lg font-semibold {{ $partnerBalance->available_balance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    NPR {{ number_format($partnerBalance->available_balance, 2) }}
                                </div>
                            </div>
                            <div class="mb-4">
                                <span class="text-sm font-medium text-gray-500">Total Lifetime Earnings:</span>
                                <div class="mt-1 text-gray-900">NPR {{ number_format($partnerBalance->total_earnings, 2) }}
                                </div>
                            </div>
                            <div class="mb-4">
                                <span class="text-sm font-medium text-gray-500">Total Withdrawn:</span>
                                <div class="mt-1 text-gray-900">NPR {{ number_format($partnerBalance->total_withdrawn, 2) }}
                                </div>
                            </div>
                        @else
                            <div class="text-yellow-600">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                No balance record found for this partner.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Statement Generation Form -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Statement Parameters</h3>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('admin.ledger.partner.statement', $partner->user_id) }}"
                    id="statementForm">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="date_from" class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                            <input type="date" name="date_from" id="date_from"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                value="{{ request('date_from', now()->startOfMonth()->format('Y-m-d')) }}"
                                @if ($firstTransaction) min="{{ $firstTransaction->created_at->format('Y-m-d') }}" @endif
                                max="{{ now()->format('Y-m-d') }}">
                            @if ($firstTransaction)
                                <p class="mt-1 text-xs text-gray-500">
                                    First transaction: {{ $firstTransaction->created_at->format('M j, Y') }}
                                </p>
                            @endif
                        </div>
                        <div>
                            <label for="date_to" class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                            <input type="date" name="date_to" id="date_to"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                value="{{ request('date_to', now()->format('Y-m-d')) }}"
                                @if ($firstTransaction) min="{{ $firstTransaction->created_at->format('Y-m-d') }}" @endif
                                max="{{ now()->format('Y-m-d') }}">
                            @if ($lastTransaction)
                                <p class="mt-1 text-xs text-gray-500">
                                    Last transaction: {{ $lastTransaction->created_at->format('M j, Y') }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-6">
                        <label for="format" class="block text-sm font-medium text-gray-700 mb-2">Statement Format</label>
                        <select name="format" id="format"
                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="detailed">Detailed Statement (with all transactions)</option>
                            <option value="summary">Summary Statement (overview only)</option>
                        </select>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors">
                            <i class="fas fa-download mr-2"></i> Download PDF Statement
                        </button>

                        <button type="button" onclick="generatePreview()"
                            class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md transition-colors">
                            <i class="fas fa-eye mr-2"></i> Preview Summary
                        </button>

                        <button type="button" onclick="setQuickDate('current_month')"
                            class="inline-flex items-center px-3 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-md transition-colors">
                            This Month
                        </button>

                        <button type="button" onclick="setQuickDate('last_month')"
                            class="inline-flex items-center px-3 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-md transition-colors">
                            Last Month
                        </button>

                        <button type="button" onclick="setQuickDate('last_3_months')"
                            class="inline-flex items-center px-3 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-md transition-colors">
                            Last 3 Months
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Preview Area -->
        <div id="previewArea" class="bg-white shadow rounded-lg hidden">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Statement Preview</h3>
            </div>
            <div id="previewContent" class="p-6">
                <!-- Preview content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        function setQuickDate(period) {
            const fromDate = document.getElementById('date_from');
            const toDate = document.getElementById('date_to');
            const today = new Date();

            switch (period) {
                case 'current_month':
                    fromDate.value = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
                    toDate.value = today.toISOString().split('T')[0];
                    break;
                case 'last_month':
                    const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0);
                    fromDate.value = lastMonth.toISOString().split('T')[0];
                    toDate.value = lastMonthEnd.toISOString().split('T')[0];
                    break;
                case 'last_3_months':
                    const threeMonthsAgo = new Date(today.getFullYear(), today.getMonth() - 3, 1);
                    fromDate.value = threeMonthsAgo.toISOString().split('T')[0];
                    toDate.value = today.toISOString().split('T')[0];
                    break;
            }
        }

        function generatePreview() {
            const fromDate = document.getElementById('date_from').value;
            const toDate = document.getElementById('date_to').value;
            const previewArea = document.getElementById('previewArea');
            const previewContent = document.getElementById('previewContent');

            previewContent.innerHTML =
                '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-blue-500"></i> Generating preview...</div>';
            previewArea.classList.remove('hidden');

            fetch(`{{ route('admin.ledger.partner.summary', $partner->user_id) }}?date_from=${fromDate}&date_to=${toDate}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const summary = data.data.summary.period;
                        const balances = data.data.balances;

                        previewContent.innerHTML = `
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <div class="text-sm font-medium text-blue-800">Period Earnings</div>
                                <div class="text-2xl font-bold text-blue-900">NPR ${summary.total_earnings.toLocaleString()}</div>
                                <div class="text-xs text-blue-600">${summary.earnings_count} jobs completed</div>
                            </div>
                            <div class="bg-red-50 p-4 rounded-lg">
                                <div class="text-sm font-medium text-red-800">COD Commission</div>
                                <div class="text-2xl font-bold text-red-900">NPR ${summary.total_cod_commission.toLocaleString()}</div>
                                <div class="text-xs text-red-600">${summary.cod_jobs_count} COD jobs</div>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="text-sm font-medium text-green-800">Net Balance Change</div>
                                <div class="text-2xl font-bold text-green-900">NPR ${summary.net_earnings.toLocaleString()}</div>
                                <div class="text-xs text-green-600">After commissions & cash settled</div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <h4 class="font-medium text-gray-900 mb-2">Balance Changes</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span>Opening Balance:</span>
                                        <span class="${balances.opening >= 0 ? 'text-green-600' : 'text-red-600'}">NPR ${balances.opening.toLocaleString()}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Closing Balance:</span>
                                        <span class="${balances.closing >= 0 ? 'text-green-600' : 'text-red-600'}">NPR ${balances.closing.toLocaleString()}</span>
                                    </div>
                                    <div class="flex justify-between font-medium">
                                        <span>Net Change:</span>
                                        <span class="${balances.net_change >= 0 ? 'text-green-600' : 'text-red-600'}">
                                            ${balances.net_change >= 0 ? '+' : ''}NPR ${balances.net_change.toLocaleString()}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <h4 class="font-medium text-gray-900 mb-2">Transaction Summary</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span>Total Transactions:</span>
                                        <span>${data.data.transactions.transaction_count}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Withdrawals:</span>
                                        <span>NPR ${summary.total_withdrawals.toLocaleString()}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    } else {
                        previewContent.innerHTML =
                            `<div class="text-red-600"><i class="fas fa-exclamation-triangle mr-2"></i>${data.message}</div>`;
                    }
                })
                .catch(error => {
                    previewContent.innerHTML =
                        `<div class="text-red-600"><i class="fas fa-exclamation-triangle mr-2"></i>Failed to generate preview</div>`;
                });
        }

        // Date validation
        document.getElementById('date_from').addEventListener('change', function() {
            const toDate = document.getElementById('date_to');
            if (toDate.value && this.value > toDate.value) {
                toDate.value = this.value;
            }
        });

        document.getElementById('date_to').addEventListener('change', function() {
            const fromDate = document.getElementById('date_from');
            if (fromDate.value && this.value < fromDate.value) {
                fromDate.value = this.value;
            }
        });
    </script>
@endsection
