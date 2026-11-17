@extends('layouts.app')

@section('title', 'Order Management - HomifyServe Admin')

@section('content')
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Order Management</h1>
                <p class="text-gray-600 mt-1">Manage customer orders and service requests</p>
            </div>
            <div class="flex space-x-2">
                <button onclick="openNewOrderModal()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-md flex items-center space-x-1.5 text-sm">
                    <i class="fas fa-plus text-xs"></i>
                    <span>New Order</span>
                </button>
                <button onclick="openBulkActionsModal()"
                    class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-1.5 rounded-md flex items-center space-x-1.5 text-sm">
                    <i class="fas fa-list-check text-xs"></i>
                    <span>Bulk Actions</span>
                </button>
            </div>
        </div>

        <!-- Statistics Dashboard -->
        <x-orders.statistics :stats="$stats ?? []" />

        <!-- Filters -->
        <x-orders.filters :partners="$partners ?? []" />

        <!-- Orders Table -->
        <x-orders.table :orders="$orders" />

        <!-- Pagination -->
        @if ($orders->hasPages())
            <div class="flex justify-center">
                {{ $orders->links() }}
            </div>
        @endif
    </div>

    <!-- Modals -->
    <x-modals.notification />
    <x-modals.confirmation />
    <x-orders.partner-assign-modal :partners="$partners ?? []" />
    <x-orders.status-update-modal />
    <x-orders.cancellation-modal />

    <!-- Bulk Actions Modal -->
    <x-modals.base-modal id="bulkActionsModal" title="Bulk Actions" size="md">
        <div class="space-y-4">
            <div class="bg-blue-50 rounded-lg p-4">
                <p class="text-sm text-blue-800">
                    <span id="selectedCount">0</span> orders selected
                </p>
            </div>

            <div class="space-y-2">
                <button onclick="bulkUpdateStatus('confirmed')"
                    class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-left">
                    <i class="fas fa-check mr-2"></i>Mark as Confirmed
                </button>
                <button onclick="bulkUpdateStatus('in_progress')"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-left">
                    <i class="fas fa-play mr-2"></i>Mark as In Progress
                </button>
                <button onclick="bulkUpdateStatus('completed')"
                    class="w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-left">
                    <i class="fas fa-check-circle mr-2"></i>Mark as Completed
                </button>
                <button onclick="bulkAssignPartner()"
                    class="w-full bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg text-left">
                    <i class="fas fa-user-plus mr-2"></i>Assign Partner
                </button>
                <button onclick="bulkExport()"
                    class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-left">
                    <i class="fas fa-download mr-2"></i>Export Selected
                </button>
            </div>
        </div>
    </x-modals.base-modal>
@endsection

@push('scripts')
    <script>
        // Bulk actions
        function openBulkActionsModal() {
            const selected = getSelectedOrders();
            if (selected.length === 0) {
                showNotificationModal('error', 'Error', 'Please select at least one order');
                return;
            }
            document.getElementById('selectedCount').textContent = selected.length;
            document.getElementById('bulkActionsModal').classList.remove('hidden');
        }

        function closeBulkActionsModal() {
            document.getElementById('bulkActionsModal').classList.add('hidden');
        }

        function bulkUpdateStatus(status) {
            const selected = getSelectedOrders();
            if (selected.length === 0) {
                showNotificationModal('error', 'Error', 'Please select orders first');
                return;
            }

            showConfirmationModal(
                'Bulk Update Status',
                `Update ${selected.length} orders to "${status.replace('_', ' ')}"?`,
                () => {
                    fetch('/admin/orders/bulk-update-status', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                                    'content')
                            },
                            body: JSON.stringify({
                                order_ids: selected,
                                status: status
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            closeBulkActionsModal();
                            if (data.success) {
                                showNotificationModal('success', 'Success', data.message, () => location.reload());
                            } else {
                                showNotificationModal('error', 'Error', data.message);
                            }
                        })
                        .catch(error => {
                            closeBulkActionsModal();
                            showNotificationModal('error', 'Error', 'Failed to update orders: ' + error.message);
                        });
                }
            );
        }

        function bulkAssignPartner() {
            const selected = getSelectedOrders();
            if (selected.length === 0) {
                showNotificationModal('error', 'Error', 'Please select orders first');
                return;
            }
            showNotificationModal('info', 'Info', 'Bulk partner assignment - to be implemented');
        }

        function bulkExport() {
            const selected = getSelectedOrders();
            if (selected.length === 0) {
                showNotificationModal('error', 'Error', 'Please select orders first');
                return;
            }

            const params = new URLSearchParams();
            selected.forEach(id => params.append('order_ids[]', id));
            window.open(`/admin/orders/export?${params.toString()}`, '_blank');
        }

        // New order modal (placeholder)
        function openNewOrderModal() {
            showNotificationModal('info', 'Info', 'New order modal - to be implemented');
        }

        function closeNewOrderModal() {
            // Implementation when modal is created
        }

        // Assignment shortcuts
        function assignPartner(orderId) {
            openPartnerAssignModal(orderId);
        }
    </script>
@endpush
