@extends('layouts.app')

@section('title', 'Partner Payouts')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-4 sm:mb-0">Partner Payouts</h1>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.ledger.index') }}"
                    class="inline-flex items-center px-3 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-md transition-colors">
                    <i class="fas fa-list mr-2"></i> View Ledger
                </a>
                <a href="{{ route('admin.ledger.analytics') }}"
                    class="inline-flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors">
                    <i class="fas fa-chart-bar mr-2"></i> Analytics
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Filters -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Filters</h3>
            </div>
            <div class="p-4">
                <form method="GET" action="{{ route('admin.ledger.partner-payouts') }}">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">From
                                Date</label>
                            <input type="date" name="date_from" id="date_from"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                value="{{ request('date_from') }}">
                        </div>
                        <div>
                            <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                            <input type="date" name="date_to" id="date_to"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                value="{{ request('date_to') }}">
                        </div>
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" id="status"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Status</option>
                                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending
                                </option>
                                <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>
                                    Processing</option>
                                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>
                                    Completed</option>
                                <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">&nbsp;</label>
                            <button type="submit"
                                class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors">
                                Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-money-bill-wave text-2xl text-green-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Earnings</dt>
                                <dd class="text-lg font-medium text-gray-900">Rs {{ number_format($totalEarnings, 2) }}
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
                            <i class="fas fa-hourglass-half text-2xl text-yellow-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Pending Partners</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $pendingPartners->count() }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-list text-2xl text-blue-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Payouts</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $payouts->total() }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payouts Table -->
        <div class="bg-white shadow overflow-hidden sm:rounded-md">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Partner Payouts</h3>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">Manage and track partner payout requests</p>
            </div>

            @if ($payouts->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Payout ID</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Partner</th>
                                <th scope="col"
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Amount</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Payment Method</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Created</th>
                                <th scope="col"
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($payouts as $payout)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        #{{ $payout->payout_id }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $payout->partner->name ?? 'N/A' }}</div>
                                        <div class="text-sm text-gray-500">{{ $payout->partner->email ?? 'N/A' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        Rs {{ number_format($payout->amount, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                            @if ($payout->status === 'pending') bg-yellow-100 text-yellow-800
                                            @elseif($payout->status === 'processing') bg-blue-100 text-blue-800
                                            @elseif($payout->status === 'completed') bg-green-100 text-green-800
                                            @else bg-red-100 text-red-800 @endif">
                                            {{ ucfirst($payout->status) }}
                                        </span>
                                        @if ($payout->status === 'processing' && $payout->processed_at)
                                            <div class="mt-1 text-xs whitespace-normal text-blue-700">
                                                since {{ $payout->processed_at->diffForHumans() }}
                                            </div>
                                        @endif
                                        @if ($payout->status === 'failed' && $payout->failure_reason)
                                            <div class="mt-1 max-w-xs whitespace-normal text-xs text-red-700"
                                                title="{{ $payout->failure_reason }}">
                                                {{ \Illuminate\Support\Str::limit($payout->failure_reason, 90) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $payout->payment_method ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $payout->created_at->format('M d, Y H:i') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        @if ($payout->status === 'pending')
                                            <button
                                                onclick="openProcessModal({{ $payout->payout_id }}, @js($payout->partner->name ?? 'N/A'), {{ $payout->amount }})"
                                                class="text-blue-600 hover:text-blue-900 mr-3">Process</button>
                                        @elseif ($payout->status === 'processing')
                                            <button
                                                onclick="openCompleteModal({{ $payout->payout_id }}, @js($payout->partner->name ?? 'N/A'), @js($payout->transaction_reference))"
                                                class="text-green-600 hover:text-green-900 mr-3">Complete</button>
                                        @endif
                                        @if (in_array($payout->status, ['pending', 'processing'], true))
                                            <button
                                                onclick="openFailModal({{ $payout->payout_id }}, @js($payout->partner->name ?? 'N/A'))"
                                                class="text-red-600 hover:text-red-900 mr-3">Fail</button>
                                        @endif
                                        <a href="{{ route('admin.ledger.partner-payouts.details', $payout->payout_id) }}"
                                            class="text-gray-600 hover:text-gray-900">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 sm:px-6">
                    {{ $payouts->appends(request()->query())->links() }}
                </div>
            @else
                <div class="px-4 py-12 text-center">
                    <i class="fas fa-money-bill-wave text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No payouts found</h3>
                    <p class="text-gray-500">No partner payouts match your current filters.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Process Payout Modal -->
    <div id="processModal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title"
        role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div
                class="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form id="processPayoutForm" method="POST"
                    action="{{ route('admin.ledger.partner-payouts.process') }}">
                    @csrf
                    <input type="hidden" name="payout_id" id="modalPayoutId">

                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div
                                class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-credit-card text-blue-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    Process Partner Payout
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500" id="modalDescription">
                                        Processing payout for <span id="modalPartnerName" class="font-medium"></span>
                                        - Rs <span id="modalAmount" class="font-medium"></span>
                                    </p>
                                </div>
                                <div class="mt-4 space-y-4">
                                    <div>
                                        <label for="payment_method"
                                            class="block text-sm font-medium text-gray-700">Payment Method</label>
                                        <select name="payment_method" id="payment_method" required
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">Select Payment Method</option>
                                            <option value="bank_transfer">Bank Transfer</option>
                                            <option value="esewa">eSewa (manual transfer)</option>
                                            <option value="khalti">Khalti (manual transfer)</option>
                                            <option value="cash">Cash</option>
                                        </select>
                                        <p id="esewaHint"
                                            class="mt-2 hidden text-xs text-blue-800 bg-blue-50 border border-blue-200 rounded-md p-2">
                                            Send the money to the partner from the platform eSewa account first, then
                                            enter the eSewa transaction ID below. eSewa&rsquo;s payment API cannot send money
                                            to a partner automatically &mdash; this form only records the transfer you made.
                                        </p>
                                    </div>
                                    <div>
                                        <label for="transaction_reference"
                                            class="block text-sm font-medium text-gray-700">Transaction Reference
                                            <span id="referenceRequired" class="text-red-500">*</span></label>
                                        <input type="text" name="transaction_reference" id="transaction_reference"
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="Bank ref / eSewa transaction ID / receipt number">
                                    </div>
                                    <div>
                                        <label for="gateway_fee" class="block text-sm font-medium text-gray-700">Gateway
                                            Fee (if any)</label>
                                        <input type="number" name="gateway_fee" id="gateway_fee" step="0.01"
                                            min="0"
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="0.00">
                                    </div>
                                    <div>
                                        <label for="notes"
                                            class="block text-sm font-medium text-gray-700">Notes</label>
                                        <textarea name="notes" id="notes" rows="3"
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="Additional notes about this payout"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Process Payout
                        </button>
                        <button type="button" onclick="closeProcessModal()"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Complete Payout Modal -->
    <div id="completeModal" class="fixed inset-0 z-50 overflow-y-auto hidden" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div
                class="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form id="completePayoutForm" method="POST"
                    action="{{ route('admin.ledger.partner-payouts.complete') }}">
                    @csrf
                    <input type="hidden" name="payout_id" id="completePayoutId">

                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Mark Payout Completed</h3>
                        <p class="mt-2 text-sm text-gray-500">
                            Only do this after the money has actually reached
                            <span id="completePartnerName" class="font-medium"></span>. Payout #
                            <span id="completePayoutLabel" class="font-medium"></span> will be finalized and the
                            withdrawal recorded in the ledger.
                        </p>
                        <div class="mt-4 space-y-4">
                            <div>
                                <label for="completeGatewayId"
                                    class="block text-sm font-medium text-gray-700">Transaction ID / Reference</label>
                                <input type="text" name="gateway_payout_id" id="completeGatewayId"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="eSewa transaction ID / bank reference">
                            </div>
                            <div>
                                <label for="completion_notes"
                                    class="block text-sm font-medium text-gray-700">Completion Notes</label>
                                <textarea name="completion_notes" id="completion_notes" rows="2"
                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Who confirmed the transfer, when, etc."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Mark Completed
                        </button>
                        <button type="button" onclick="closeCompleteModal()"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Fail Payout Modal -->
    <div id="failModal" class="fixed inset-0 z-50 overflow-y-auto hidden" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div
                class="relative inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form id="failPayoutForm" method="POST" action="{{ route('admin.ledger.partner-payouts.fail') }}"
                    onsubmit="return confirm('Mark this payout as failed? Any reserved balance will be restored to the partner.');">
                    @csrf
                    <input type="hidden" name="payout_id" id="failPayoutId">

                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Fail Payout</h3>
                        <p class="mt-2 text-sm text-gray-500">
                            Use this when the transfer was never sent, was cancelled, or was returned. The partner's
                            reserved balance is restored. Payout for
                            <span id="failPartnerName" class="font-medium"></span>.
                        </p>
                        <div class="mt-4">
                            <label for="failure_reason" class="block text-sm font-medium text-gray-700">Reason</label>
                            <textarea name="failure_reason" id="failure_reason" rows="3" required minlength="5"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                placeholder="e.g. eSewa transfer cancelled / wrong number / returned by bank"></textarea>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Mark Failed
                        </button>
                        <button type="button" onclick="closeFailModal()"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openProcessModal(payoutId, partnerName, amount) {
            document.getElementById('modalPayoutId').value = payoutId;
            document.getElementById('modalPartnerName').textContent = partnerName;
            document.getElementById('modalAmount').textContent = parseFloat(amount).toFixed(2);
            document.getElementById('processModal').classList.remove('hidden');
        }

        function closeProcessModal() {
            document.getElementById('processModal').classList.add('hidden');
            document.getElementById('processPayoutForm').reset();
        }

        // Close modal when clicking outside
        document.getElementById('processModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeProcessModal();
            }
        });

        // eSewa / Khalti are manual transfers now: the reference is mandatory
        // and the hint spells out that the platform cannot push money to the
        // partner from here.
        (function() {
            const methodSelect = document.getElementById('payment_method');
            const esewaHint = document.getElementById('esewaHint');
            const referenceInput = document.getElementById('transaction_reference');
            const referenceRequired = document.getElementById('referenceRequired');

            function syncPayoutMethodUi() {
                const isCash = methodSelect.value === 'cash';
                referenceInput.required = !isCash;
                referenceRequired.classList.toggle('hidden', isCash);
                esewaHint.classList.toggle('hidden', methodSelect.value !== 'esewa');
            }

            methodSelect.addEventListener('change', syncPayoutMethodUi);
            syncPayoutMethodUi();
        })();

        function openCompleteModal(payoutId, partnerName, reference) {
            document.getElementById('completePayoutId').value = payoutId;
            document.getElementById('completePayoutLabel').textContent = payoutId;
            document.getElementById('completePartnerName').textContent = partnerName;

            const gatewayInput = document.getElementById('completeGatewayId');
            if (!gatewayInput.value && reference) {
                gatewayInput.value = reference;
            }

            document.getElementById('completeModal').classList.remove('hidden');
        }

        function closeCompleteModal() {
            document.getElementById('completeModal').classList.add('hidden');
            document.getElementById('completePayoutForm').reset();
        }

        function openFailModal(payoutId, partnerName) {
            document.getElementById('failPayoutId').value = payoutId;
            document.getElementById('failPartnerName').textContent = partnerName;
            document.getElementById('failModal').classList.remove('hidden');
        }

        function closeFailModal() {
            document.getElementById('failModal').classList.add('hidden');
            document.getElementById('failPayoutForm').reset();
        }

        ['completeModal', 'failModal'].forEach(function(id) {
            document.getElementById(id).addEventListener('click', function(e) {
                if (e.target === this) {
                    if (id === 'completeModal') {
                        closeCompleteModal();
                    } else {
                        closeFailModal();
                    }
                }
            });
        });
    </script>
    <!-- Partner Earnings & Payouts Table and Recent Payouts -->
    <div class="px-4 py-3 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">Partner Earnings & Payouts</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col"
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Partner
                    </th>
                    <th scope="col"
                        class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Orders
                    </th>
                    <th scope="col"
                        class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total
                        Earnings</th>
                    <th scope="col"
                        class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Paid
                        Out</th>
                    <th scope="col"
                        class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Pending
                    </th>
                    <th scope="col"
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last
                        Earning</th>
                    <th scope="col"
                        class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($partnerEarnings as $partner)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <div class="font-medium text-gray-900">{{ $partner->first_name }}
                                {{ $partner->last_name }}</div>
                            <div class="text-gray-500">{{ $partner->email }}</div>
                            @if ($partner->phone)
                                <div class="text-gray-400 text-xs">{{ $partner->phone }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                            {{ number_format($partner->completed_orders) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-medium">
                            Rs {{ number_format($partner->total_earnings, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                            Rs {{ number_format($partner->total_payouts, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                            @if ($partner->pending_payout > 0)
                                <span class="text-green-600 font-medium">Rs
                                    {{ number_format($partner->pending_payout, 2) }}</span>
                            @else
                                <span class="text-gray-400">Rs 0.00</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            @if ($partner->last_earning_date)
                                {{ \Carbon\Carbon::parse($partner->last_earning_date)->format('M j, Y') }}
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                            <a href="{{ route('admin.ledger.user', $partner->user_id) }}"
                                class="inline-flex items-center text-blue-600 hover:text-blue-900"
                                title="Open this partner's ledger">
                                <i class="fas fa-book mr-1"></i> Ledger
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">No partner earnings
                            found for selected criteria</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-700">
                    Showing {{ $partnerEarnings->firstItem() ?? 0 }} to {{ $partnerEarnings->lastItem() ?? 0 }}
                    of {{ $partnerEarnings->total() }} partners
                </p>
            </div>
            <div>
                {{ $partnerEarnings->links() }}
            </div>
        </div>
    </div>

    <!-- Recent Payouts -->
    @if ($recentPayouts->count() > 0)
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Recent Payouts</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Partner</th>
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Amount</th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Details</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($recentPayouts as $payout)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $payout->created_at->format('M j, Y H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if ($payout->user)
                                        <div class="font-medium text-gray-900">{{ $payout->user->first_name }}
                                            {{ $payout->user->last_name }}</div>
                                        <div class="text-gray-500">{{ $payout->user->email }}</div>
                                    @else
                                        <span class="text-gray-400">User #{{ $payout->user_id }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-medium">
                                    Rs {{ number_format(abs($payout->amount), 2) }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div class="max-w-xs truncate">{{ $payout->narration }}</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Payout Modal -->
    <div id="payoutModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Process Partner Payout</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Payouts are requested by partners from their app and appear in the table above. Use the
                    <span class="font-medium">Process</span> action on a pending payout &mdash; this page does not
                    create new payout requests.
                </p>
                <div class="flex items-center justify-end">
                    <button type="button" onclick="closePayoutModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        // Auto-submit form when filters change
        document.querySelectorAll('select[name="status"]').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
@endsection
