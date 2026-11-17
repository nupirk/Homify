@extends('layouts.app')

@section('title', 'User Balances')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-4 sm:mb-0">User Balances</h1>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.ledger.export', array_merge(request()->query(), ['export_type' => 'balances'])) }}"
                    class="inline-flex items-center px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md transition-colors">
                    <i class="fas fa-download mr-2"></i> Export Balances
                </a>
                <a href="{{ route('admin.ledger.index') }}" class="inline-flex items-center px-3 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-md transition-colors">
                    <i class="fas fa-list mr-2"></i> View Entries
                </a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-users text-2xl text-blue-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Users with Positive Balance</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $stats['positive_balance_users'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-users text-2xl text-red-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Users with Negative Balance</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $stats['negative_balance_users'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-plus-circle text-2xl text-green-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Positive Balance</dt>
                                <dd class="text-lg font-medium text-gray-900">Rs {{ number_format($stats['total_positive_balance'], 2) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-minus-circle text-2xl text-red-600"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Negative Balance</dt>
                                <dd class="text-lg font-medium text-gray-900">Rs {{ number_format(abs($stats['total_negative_balance']), 2) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Filters</h3>
            </div>
            <div class="p-4">
                <form method="GET" action="{{ route('admin.ledger.balances') }}">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="balance_filter" class="block text-sm font-medium text-gray-700 mb-1">Balance Filter</label>
                            <select name="balance_filter" id="balance_filter" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Users</option>
                                <option value="positive" {{ request('balance_filter') == 'positive' ? 'selected' : '' }}>Positive Balance</option>
                                <option value="negative" {{ request('balance_filter') == 'negative' ? 'selected' : '' }}>Negative Balance</option>
                                <option value="zero" {{ request('balance_filter') == 'zero' ? 'selected' : '' }}>Zero Balance</option>
                            </select>
                        </div>

                        <div>
                            <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                            <select name="sort_by" id="sort_by" class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="name" {{ request('sort_by') == 'name' ? 'selected' : '' }}>Name</option>
                                <option value="balance" {{ request('sort_by') == 'balance' ? 'selected' : '' }}>Balance</option>
                                <option value="transactions" {{ request('sort_by') == 'transactions' ? 'selected' : '' }}>Transaction Count</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">&nbsp;</label>
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors">
                                Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- User Balances Table -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 py-3 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">User Balances</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Current Balance</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Entries</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User Type</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($userBalances as $userBalance)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <div class="font-medium text-gray-900">
                                        {{ $userBalance->first_name }} {{ $userBalance->last_name }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $userBalance->email }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                    <span class="font-medium {{ $userBalance->current_balance < 0 ? 'text-red-600' : ($userBalance->current_balance > 0 ? 'text-green-600' : 'text-gray-900') }}">
                                        Rs {{ number_format($userBalance->current_balance, 2) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    {{ number_format($userBalance->transaction_count) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        {{ ucfirst($userBalance->user_type) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <a href="{{ route('admin.ledger.user', $userBalance->user_id) }}" 
                                       class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    @if($userBalance->transaction_count > 0)
                                        <button type="button" 
                                                class="text-green-600 hover:text-green-900"
                                                onclick="openBalanceModal('{{ $userBalance->user_id }}')">
                                            <i class="fas fa-info-circle"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">No user balances found</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($userBalances->count() > 0)
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="2" class="px-6 py-3 text-right text-sm font-medium text-gray-900">Page Totals:</td>
                                <td class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                    {{ $userBalances->count() }} users shown
                                </td>
                                <td class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                    {{ isset($pageTotals['total_transactions']) ? number_format($pageTotals['total_transactions']) : 'N/A' }} transactions
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                <div class="flex-1 flex justify-between sm:hidden">
                    {{ $userBalances->links() }}
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing {{ $userBalances->firstItem() ?? 0 }} to {{ $userBalances->lastItem() ?? 0 }}
                            of {{ $userBalances->total() }} users
                        </p>
                    </div>
                    <div>
                        {{ $userBalances->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        // Auto-submit form when select changes
        document.querySelectorAll('select[name="balance_filter"], select[name="sort_by"]').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });

        function openBalanceModal(userId) {
            // You can implement modal functionality here if needed
            window.location.href = `/admin/ledger/user/${userId}`;
        }
    </script>
@endsection
