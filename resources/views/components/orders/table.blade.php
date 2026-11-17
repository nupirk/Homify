@props(['orders', 'showBulkActions' => true])

<div class="bg-white shadow-sm rounded-lg border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <!-- Table Header -->
            <thead class="bg-gray-50">
                <tr>
                    @if ($showBulkActions)
                        <th scope="col" class="w-4 p-4">
                            <div class="flex items-center">
                                <input id="selectAll" type="checkbox" onchange="toggleAllOrders()"
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                            </div>
                        </th>
                    @endif
                    <th scope="col"
                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Order Details
                    </th>
                    <th scope="col"
                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Customer
                    </th>
                    <th scope="col"
                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Service
                    </th>
                    <th scope="col"
                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Partner
                    </th>
                    <th scope="col"
                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Status
                    </th>
                    <th scope="col"
                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Amount
                    </th>
                    <th scope="col"
                        class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Date
                    </th>
                    <th scope="col"
                        class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>

            <!-- Table Body -->
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($orders as $order)
                    <tr class="hover:bg-gray-50">
                        @if ($showBulkActions)
                            <td class="w-4 p-4">
                                <div class="flex items-center">
                                    <input type="checkbox" value="{{ $order->order_id }}"
                                        class="order-checkbox w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                                </div>
                            </td>
                        @endif

                        <!-- Order Details -->
                        <td class="px-4 py-3">
                            <div class="text-sm">
                                <div class="font-medium text-gray-900">#{{ $order->order_id }}</div>
                                <div class="text-gray-500">{{ $order->created_at->format('M d, Y H:i') }}</div>
                            </div>
                        </td>

                        <!-- Customer -->
                        <td class="px-4 py-3">
                            <div class="text-sm">
                                <div class="font-medium text-gray-900">{{ $order->user->name ?? 'N/A' }}</div>
                                <div class="text-gray-500">{{ $order->user->email ?? 'N/A' }}</div>
                            </div>
                        </td>

                        <!-- Service -->
                        <td class="px-4 py-3">
                            @if ($order->serviceRequests->first())
                                <div class="text-sm">
                                    <div class="font-medium text-gray-900">
                                        {{ $order->serviceRequests->first()->service->name ?? 'N/A' }}</div>
                                    <div class="text-gray-500">
                                        {{ $order->serviceRequests->first()->service->category->name ?? 'N/A' }}</div>
                                </div>
                            @else
                                <span class="text-gray-500">N/A</span>
                            @endif
                        </td>

                        <!-- Partner -->
                        <td class="px-4 py-3">
                            @if ($order->serviceRequests->first()?->partner)
                                <div class="text-sm">
                                    <div class="font-medium text-gray-900">
                                        {{ $order->serviceRequests->first()->partner->first_name }}
                                        {{ $order->serviceRequests->first()->partner->last_name }}
                                    </div>
                                    <div class="text-gray-500">
                                        {{ $order->serviceRequests->first()->partner->user->email ?? 'N/A' }}
                                    </div>
                                </div>
                            @else
                                <span class="text-orange-600 font-medium">Unassigned</span>
                            @endif
                        </td>

                        <!-- Status -->
                        <td class="px-4 py-3">
                            <x-orders.status-badge :status="$order->status" />
                        </td>

                        <!-- Amount -->
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900">
                                Rs {{ $order->total_amount }}</div>
                        </td>

                        <!-- Date -->
                        <td class="px-4 py-3">
                            <div class="text-sm text-gray-900">{{ $order->created_at->format('M d, Y') }}</div>
                            <div class="text-xs text-gray-500">{{ $order->created_at->diffForHumans() }}</div>
                        </td>

                        <!-- Actions -->
                        <td class="px-4 py-3">
                            <x-orders.action-buttons :order="$order" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showBulkActions ? 9 : 8 }}" class="px-4 py-8 text-center">
                            <div class="text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-4"></i>
                                <p class="text-lg font-medium">No orders found</p>
                                <p class="text-sm">Try adjusting your search or filter criteria</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
    <script>
        function toggleAllOrders() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.order-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        function getSelectedOrders() {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            return Array.from(checkboxes).map(cb => cb.value);
        }
    </script>
@endpush
