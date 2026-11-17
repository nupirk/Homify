@props(['order'])

<div class="flex items-center space-x-1">
    <!-- View Details -->
    <a href="{{ route('admin.orders.show', $order->order_id) }}"
        class="text-blue-600 hover:text-blue-800 p-1 rounded hover:bg-blue-50" title="View Details">
        <i class="fas fa-eye text-sm"></i>
    </a>

    <!-- Status Update -->
    <button onclick="toggleStatusMenu('{{ $order->order_id }}')"
        class="text-green-600 hover:text-green-800 p-1 rounded hover:bg-green-50" title="Update Status">
        <i class="fas fa-edit text-sm"></i>
    </button>

    <!-- Partner Assignment -->
    @if ($order->serviceRequests->first()?->partner)
        <!-- Reassign Partner -->
        <button onclick="reassignPartner('{{ $order->order_id }}')"
            class="text-orange-600 hover:text-orange-800 p-1 rounded hover:bg-orange-50" title="Reassign Partner">
            <i class="fas fa-user-edit text-sm"></i>
        </button>
    @else
        <!-- Assign Partner -->
        <button onclick="openPartnerAssignModal('{{ $order->order_id }}')"
            class="text-blue-600 hover:text-blue-800 p-1 rounded hover:bg-blue-50" title="Assign Partner">
            <i class="fas fa-user-plus text-sm"></i>
        </button>
    @endif

    <!-- Cancel Order -->
    @if (in_array($order->status, ['created', 'awaiting_partner', 'confirmed']))
        <button onclick="cancelOrder('{{ $order->order_id }}')"
            class="text-red-600 hover:text-red-800 p-1 rounded hover:bg-red-50" title="Cancel Order">
            <i class="fas fa-times text-sm"></i>
        </button>
    @endif

    <!-- Communications -->
    <button onclick="viewCommunications('{{ $order->order_id }}')"
        class="text-purple-600 hover:text-purple-800 p-1 rounded hover:bg-purple-50" title="View Communications">
        <i class="fas fa-comments text-sm"></i>
    </button>

    <!-- More Actions Dropdown -->
    <div class="relative inline-block text-left">
        <button onclick="toggleActionDropdown('{{ $order->order_id }}')"
            class="text-gray-600 hover:text-gray-800 p-1 rounded hover:bg-gray-50" title="More Actions">
            <i class="fas fa-ellipsis-v text-sm"></i>
        </button>

        <div id="actionDropdown-{{ $order->order_id }}"
            class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10">
            <div class="py-1">
                <button onclick="showOrderItems('{{ $order->order_id }}')"
                    class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                    <i class="fas fa-list mr-2"></i>View Items
                </button>
                <button onclick="exportOrder('{{ $order->order_id }}')"
                    class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                    <i class="fas fa-download mr-2"></i>Export
                </button>
                <button onclick="duplicateOrder('{{ $order->order_id }}')"
                    class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                    <i class="fas fa-copy mr-2"></i>Duplicate
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        function toggleActionDropdown(orderId) {
            const dropdown = document.getElementById(`actionDropdown-${orderId}`);

            // Close all other dropdowns
            document.querySelectorAll('[id^="actionDropdown-"]').forEach(dd => {
                if (dd.id !== `actionDropdown-${orderId}`) {
                    dd.classList.add('hidden');
                }
            });

            // Toggle current dropdown
            dropdown.classList.toggle('hidden');
        }

        function viewCommunications(orderId) {
            window.location.href = `/admin/orders/${orderId}/communications`;
        }

        function showOrderItems(orderId) {
            // This would open an order items modal
            showNotificationModal('info', 'Info', 'Order items modal - to be implemented');
        }

        function exportOrder(orderId) {
            window.open(`/admin/orders/${orderId}/export`, '_blank');
        }

        function duplicateOrder(orderId) {
            showConfirmationModal(
                'Duplicate Order',
                'Are you sure you want to create a duplicate of this order?',
                () => {
                    // Implementation for order duplication
                    showNotificationModal('info', 'Info', 'Order duplication - to be implemented');
                }
            );
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('[id^="actionDropdown-"]') && !event.target.closest(
                    'button[onclick*="toggleActionDropdown"]')) {
                document.querySelectorAll('[id^="actionDropdown-"]').forEach(dropdown => {
                    dropdown.classList.add('hidden');
                });
            }
        });
    </script>
@endpush
