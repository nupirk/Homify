@props([
    'statuses' => [
        'pending_payment' => 'Payment Pending',
        'awaiting_partner' => 'Awaiting Partner',
        'confirmed' => 'Confirmed',
        'enroute' => 'En Route',
        'in_progress' => 'In Progress',
        'working' => 'Working',
        'completed' => 'Completed',
        'rejected' => 'Rejected',
        'cancelled_by_admin' => 'Cancelled by Admin',
    ],
])

<x-modals.base-modal id="statusUpdateModal" title="Update Order Status" size="md">
    <div>
        <!-- Order Details -->
        <div class="mb-4 p-4 bg-blue-50 rounded-lg">
            <h4 class="font-medium text-blue-900 mb-2">Order Details</h4>
            <div id="orderDetailsForStatus" class="text-sm text-blue-800">
                <!-- Order details will be populated here -->
            </div>
        </div>

        <!-- Status Selection -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">New Status</label>
            <select id="newStatusSelect"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <!-- Notes -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Special Instructions (Optional)</label>
            <textarea id="statusUpdateNotes" rows="3"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Add any special instructions or notes..."></textarea>
        </div>

        <!-- Actions -->
        <div class="flex justify-end space-x-3">
            <button type="button" onclick="closeStatusUpdateModal()"
                class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg">
                Cancel
            </button>
            <button type="button" onclick="confirmStatusUpdate()"
                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                Update Status
            </button>
        </div>
    </div>
</x-modals.base-modal>

@push('scripts')
    <script>
        let statusUpdateOrderId = null;

        function openStatusUpdateModal(orderId) {
            statusUpdateOrderId = orderId;
            document.getElementById('orderDetailsForStatus').innerHTML = `
            <div class="flex items-center space-x-2">
                <strong>Order ID:</strong> <span>#${orderId.substring(0, 8)}</span>
            </div>
        `;
            document.getElementById('statusUpdateModal').classList.remove('hidden');
        }

        function closeStatusUpdateModal() {
            document.getElementById('statusUpdateModal').classList.add('hidden');
            statusUpdateOrderId = null;
            document.getElementById('statusUpdateNotes').value = '';
        }

        function confirmStatusUpdate() {
            if (!statusUpdateOrderId) return;

            const status = document.getElementById('newStatusSelect').value;
            const notes = document.getElementById('statusUpdateNotes').value;

            fetch(`/admin/orders/${statusUpdateOrderId}/status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        status: status,
                        notes: notes
                    })
                })
                .then(response => response.json())
                .then(data => {
                    closeStatusUpdateModal();
                    if (data.success) {
                        showNotificationModal('success', 'Success', data.message, () => location.reload());
                    } else {
                        showNotificationModal('error', 'Error', data.message);
                    }
                })
                .catch(error => {
                    closeStatusUpdateModal();
                    showNotificationModal('error', 'Error', 'Failed to update status: ' + error.message);
                });
        }

        // Alias functions for backward compatibility
        function toggleStatusMenu(orderId) {
            openStatusUpdateModal(orderId);
        }

        function updateOrderStatus(orderId, status = null) {
            if (status) {
                // Direct status update
                fetch(`/admin/orders/${orderId}/status`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            status: status
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotificationModal('success', 'Success', data.message, () => location.reload());
                        } else {
                            showNotificationModal('error', 'Error', data.message);
                        }
                    })
                    .catch(error => {
                        showNotificationModal('error', 'Error', 'Failed to update status: ' + error.message);
                    });
            } else {
                // Open modal for status selection
                openStatusUpdateModal(orderId);
            }
        }
    </script>
@endpush
