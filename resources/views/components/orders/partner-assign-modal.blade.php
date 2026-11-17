@props(['partners' => []])

<x-modals.base-modal id="partnerAssignModal" title="Assign Partner to Order" size="lg">
    <div>
        <!-- Order Details -->
        <div class="mb-4 p-4 bg-blue-50 rounded-lg">
            <h4 class="font-medium text-blue-900 mb-2">Order Details</h4>
            <div id="orderDetailsForAssignment" class="text-sm text-blue-800">
                <!-- Order details will be populated here -->
            </div>
        </div>

        <!-- Partners List -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-3">Available Partners</label>
            <div id="availablePartners" class="space-y-3 max-h-64 overflow-y-auto">
                @forelse($partners as $partner)
                    <div class="partner-option border border-gray-200 rounded-lg p-4 hover:border-blue-300 hover:bg-blue-50 cursor-pointer transition-colors"
                        data-partner-id="{{ $partner->user_id }}" onclick="selectPartner(this)">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-blue-500 rounded-full overflow-hidden">
                                <img src="https://ui-avatars.com/api/?name={{ urlencode($partner->first_name . ' ' . $partner->last_name) }}&background=3b82f6&color=fff&size=48"
                                    alt="{{ $partner->first_name }} {{ $partner->last_name }}"
                                    class="w-full h-full object-cover">
                            </div>
                            <div class="flex-1">
                                <div class="font-medium text-gray-900">
                                    {{ $partner->first_name }} {{ $partner->last_name }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ $partner->user->email ?? 'No email' }}
                                </div>
                                <div class="text-xs text-gray-400 mt-1">
                                    Rating: ⭐ {{ $partner->average_rating ?? 'N/A' }} •
                                    Completed: {{ $partner->completed_orders ?? 0 }} orders
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-gray-500">Status</div>
                                <div
                                    class="text-sm font-medium {{ $partner->is_available ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $partner->is_available ? 'Available' : 'Busy' }}
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-user-slash text-4xl mb-4"></i>
                        <p>No partners available at the moment</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Notes -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Assignment Notes (Optional)</label>
            <textarea id="assignmentNotes" rows="3"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Add any special instructions or notes for the partner..."></textarea>
        </div>

        <!-- Actions -->
        <div class="flex justify-end space-x-3">
            <button type="button" onclick="closePartnerAssignModal()"
                class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg">
                Cancel
            </button>
            <button type="button" id="assignPartnerBtn" onclick="confirmPartnerAssignment()" disabled
                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
                Assign Partner
            </button>
        </div>
    </div>
</x-modals.base-modal>

@push('scripts')
    <script>
        let selectedOrderId = null;
        let selectedPartnerId = null;

        function openPartnerAssignModal(orderId) {
            selectedOrderId = orderId;
            selectedPartnerId = null;

            // Reset partner selection
            document.querySelectorAll('.partner-option').forEach(option => {
                option.classList.remove('border-blue-500', 'bg-blue-100');
                option.classList.add('border-gray-200');
            });

            // Disable assign button
            document.getElementById('assignPartnerBtn').disabled = true;

            // Load order details
            document.getElementById('orderDetailsForAssignment').innerHTML = `
            <div class="flex items-center space-x-2">
                <strong>Order ID:</strong> <span>#${orderId.substring(0, 8)}</span>
            </div>
        `;

            // Show modal
            document.getElementById('partnerAssignModal').classList.remove('hidden');
        }

        function closePartnerAssignModal() {
            document.getElementById('partnerAssignModal').classList.add('hidden');
            selectedOrderId = null;
            selectedPartnerId = null;
            document.getElementById('assignmentNotes').value = '';
        }

        function selectPartner(element) {
            // Remove previous selection
            document.querySelectorAll('.partner-option').forEach(option => {
                option.classList.remove('border-blue-500', 'bg-blue-100');
                option.classList.add('border-gray-200');
            });

            // Add selection to clicked element
            element.classList.remove('border-gray-200');
            element.classList.add('border-blue-500', 'bg-blue-100');

            // Store selected partner ID
            selectedPartnerId = element.dataset.partnerId;

            // Enable assign button
            document.getElementById('assignPartnerBtn').disabled = false;
        }

        function confirmPartnerAssignment() {
            if (!selectedOrderId || !selectedPartnerId) {
                showNotificationModal('error', 'Error', 'Please select a partner first');
                return;
            }

            const notes = document.getElementById('assignmentNotes').value;

            showConfirmationModal(
                'Assign Partner',
                'Are you sure you want to assign this partner to the order?',
                () => {
                    fetch(`/admin/orders/${selectedOrderId}/assign-partner`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                                    'content')
                            },
                            body: JSON.stringify({
                                partner_id: selectedPartnerId,
                                notes: notes
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            closePartnerAssignModal();
                            if (data.success) {
                                showNotificationModal('success', 'Success', data.message, () => location.reload());
                            } else {
                                showNotificationModal('error', 'Error', data.message);
                            }
                        })
                        .catch(error => {
                            closePartnerAssignModal();
                            showNotificationModal('error', 'Error', 'Failed to assign partner: ' + error.message);
                        });
                }
            );
        }

        function reassignPartner(orderId) {
            showConfirmationModal(
                'Reassign Partner',
                'Are you sure you want to reassign this order to a different partner?',
                () => openPartnerAssignModal(orderId)
            );
        }
    </script>
@endpush
