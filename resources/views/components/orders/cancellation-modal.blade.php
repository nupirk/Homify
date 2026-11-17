@props(['id' => 'cancellationModal'])

<x-modals.base-modal :id="$id" title="Cancel Order" size="md">
    <div>
        <!-- Order Details -->
        <div class="mb-4 p-4 bg-red-50 rounded-lg">
            <h4 class="font-medium text-red-900 mb-2">Order Details</h4>
            <div id="orderDetailsForCancellation" class="text-sm text-red-800">
                <!-- Order details will be populated here -->
            </div>
        </div>

        <!-- Cancellation Reason -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Cancellation Reason <span class="text-red-500">*</span>
            </label>
            <textarea id="cancellationReason" rows="3" required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                placeholder="Please provide a reason for cancellation..."></textarea>
        </div>

        <!-- Actions -->
        <div class="flex justify-end space-x-3">
            <button type="button" onclick="close{{ Str::studly($id) }}Modal()"
                class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg">
                Cancel
            </button>
            <button type="button" onclick="confirmCancellation()"
                class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg">
                Cancel Order
            </button>
        </div>
    </div>
</x-modals.base-modal>

@push('scripts')
    <script>
        let cancellationOrderId = null;

        function open{{ Str::studly($id) }}Modal(orderId) {
            cancellationOrderId = orderId;
            document.getElementById('orderDetailsForCancellation').innerHTML = `
            <div class="flex items-center space-x-2">
                <strong>Order ID:</strong> <span>#${orderId.substring(0, 8)}</span>
            </div>
        `;
            document.getElementById('{{ $id }}').classList.remove('hidden');
        }

        function close{{ Str::studly($id) }}Modal() {
            document.getElementById('{{ $id }}').classList.add('hidden');
            cancellationOrderId = null;
            document.getElementById('cancellationReason').value = '';
        }

        function confirmCancellation() {
            if (!cancellationOrderId) return;

            const reason = document.getElementById('cancellationReason').value.trim();
            if (!reason) {
                showNotificationModal('error', 'Error', 'Please provide a cancellation reason');
                return;
            }

            fetch(`/admin/orders/${cancellationOrderId}/cancel`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        reason: reason
                    })
                })
                .then(response => response.json())
                .then(data => {
                    close{{ Str::studly($id) }}Modal();
                    if (data.success) {
                        showNotificationModal('success', 'Success', data.message, () => location.reload());
                    } else {
                        showNotificationModal('error', 'Error', data.message);
                    }
                })
                .catch(error => {
                    close{{ Str::studly($id) }}Modal();
                    showNotificationModal('error', 'Error', 'Failed to cancel order: ' + error.message);
                });
        }

        // Alias function for backward compatibility
        function cancelOrder(orderId) {
            open{{ Str::studly($id) }}Modal(orderId);
        }
    </script>
@endpush
