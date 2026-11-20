@extends('layouts.app')

@section('title', 'Campaign Details - ServiceCo Admin')

@section('content')
    <div class="max-w-6xl mx-auto space-y-6">
        <!-- Loading State -->
        <div id="loading" class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i>
            <p class="text-gray-600 mt-2">Loading campaign...</p>
        </div>

        <!-- Campaign Details -->
        <div id="campaign-content" class="hidden space-y-6">
            <!-- Page Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900" id="campaign-name">Campaign Details</h1>
                    <p class="text-gray-600" id="campaign-description">Campaign information and delivery status</p>
                </div>
                <div class="flex items-center space-x-3">
                    <span id="campaign-status-badge"
                        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"></span>
                    <a href="{{ route('admin.notifications.index') }}"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Notifications</span>
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Target Recipients</p>
                            <p class="text-2xl font-bold text-gray-900" id="target-count">0</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <i class="fas fa-check text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Successfully Sent</p>
                            <p class="text-2xl font-bold text-gray-900" id="sent-count">0</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-red-100 rounded-lg">
                            <i class="fas fa-times text-red-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Failed</p>
                            <p class="text-2xl font-bold text-gray-900" id="failed-count">0</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-yellow-100 rounded-lg">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Pending</p>
                            <p class="text-2xl font-bold text-gray-900" id="pending-count">0</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Campaign Information -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Details -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-6">Campaign Information</h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Campaign Name</label>
                                <p class="text-lg text-gray-900" id="info-name">-</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Status</label>
                                <p class="text-lg text-gray-900" id="info-status">-</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Template</label>
                                <p class="text-lg text-gray-900" id="info-template">-</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Target Type</label>
                                <p class="text-lg text-gray-900" id="info-target-type">-</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Priority</label>
                                <p class="text-lg text-gray-900" id="info-priority">-</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Created</label>
                                <p class="text-lg text-gray-900" id="info-created">-</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Scheduled For</label>
                                <p class="text-lg text-gray-900" id="info-scheduled">-</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Completed At</label>
                                <p class="text-lg text-gray-900" id="info-completed">-</p>
                            </div>
                        </div>

                        <div class="mt-6" id="description-section">
                            <label class="block text-sm font-medium text-gray-500">Description</label>
                            <p class="text-gray-700 mt-2" id="info-description">No description provided</p>
                        </div>
                    </div>
                </div>

                <!-- Template Preview -->
                <div class="space-y-6">
                    <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Template Preview</h3>
                        <div id="template-preview" class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <p class="text-gray-500 text-center">Loading template...</p>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                        <div class="space-y-3">
                            <button onclick="retryFailedSends()" id="retry-btn"
                                class="w-full text-left px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center space-x-3 text-blue-600 disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fas fa-redo"></i>
                                <span>Retry Failed Sends</span>
                            </button>

                            <button onclick="pauseCampaign()" id="pause-btn"
                                class="w-full text-left px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center space-x-3 text-yellow-600 disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fas fa-pause"></i>
                                <span>Pause Campaign</span>
                            </button>

                            <button onclick="cancelCampaign()" id="cancel-btn"
                                class="w-full text-left px-4 py-2 rounded-lg hover:bg-red-50 flex items-center space-x-3 text-red-600 disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fas fa-stop"></i>
                                <span>Cancel Campaign</span>
                            </button>

                            <a href="#" id="export-btn"
                                class="block w-full text-left px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center space-x-3 text-gray-600">
                                <i class="fas fa-download"></i>
                                <span>Export Report</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delivery Logs -->
            <div class="bg-white rounded-lg shadow border border-gray-200">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-900">Delivery Logs</h2>
                        <div class="flex items-center space-x-3">
                            <select id="log-filter" class="px-3 py-1 border border-gray-300 rounded text-sm">
                                <option value="">All Status</option>
                                <option value="success">Success</option>
                                <option value="failed">Failed</option>
                                <option value="pending">Pending</option>
                            </select>
                            <button onclick="refreshLogs()" class="text-blue-600 hover:text-blue-900 text-sm">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Recipient</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Sent At</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Response</th>
                            </tr>
                        </thead>
                        <tbody id="logs-table" class="bg-white divide-y divide-gray-200">
                            <!-- Logs will be loaded here -->
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-700">
                            Showing <span id="logs-showing">0</span> of <span id="logs-total">0</span> logs
                        </p>
                        <div class="flex items-center space-x-2">
                            <button onclick="loadLogs(currentPage - 1)" id="prev-btn"
                                class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                Previous
                            </button>
                            <span class="px-3 py-1 text-sm" id="page-info">Page 1</span>
                            <button onclick="loadLogs(currentPage + 1)" id="next-btn"
                                class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            let campaignId = null;
            let currentPage = 1;
            let campaign = null;

            // Get campaign ID from URL
            document.addEventListener('DOMContentLoaded', function() {
                const pathParts = window.location.pathname.split('/');
                campaignId = pathParts[pathParts.length - 1];
                loadCampaign();
            });

            async function loadCampaign() {
                try {
                    const response = await fetch(`/admin/notifications/data/campaigns/${campaignId}`);
                    const data = await response.json();

                    if (data.success) {
                        campaign = data.data;
                        populateCampaignData();
                        loadLogs();

                        // Show content and hide loading
                        document.getElementById('loading').classList.add('hidden');
                        document.getElementById('campaign-content').classList.remove('hidden');
                    } else {
                        alert('Error loading campaign: ' + (data.message || 'Unknown error'));
                        window.location.href = '{{ route('admin.notifications.index') }}';
                    }
                } catch (error) {
                    console.error('Error loading campaign:', error);
                    alert('An error occurred while loading the campaign.');
                    window.location.href = '{{ route('admin.notifications.index') }}';
                }
            }

            function populateCampaignData() {
                // Update header
                document.getElementById('campaign-name').textContent = campaign.name || 'Unnamed Campaign';
                document.getElementById('campaign-description').textContent = campaign.description ||
                    'Campaign information and delivery status';

                // Update status badge
                const statusBadge = document.getElementById('campaign-status-badge');
                statusBadge.textContent = campaign.status;
                statusBadge.className =
                    `inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${getStatusClass(campaign.status)}`;

                // Update stats
                // The campaign table stores total/successful/failed; the page
                // asked for fields that never existed and always showed zeros.
                const totalRecipients = campaign.total_recipients || 0;
                const successfulSends = campaign.successful_sends || 0;
                const failedSends = campaign.failed_sends || 0;
                document.getElementById('target-count').textContent = totalRecipients;
                document.getElementById('sent-count').textContent = successfulSends;
                document.getElementById('failed-count').textContent = failedSends;
                document.getElementById('pending-count').textContent =
                    Math.max(0, totalRecipients - successfulSends - failedSends);

                // Update info section
                document.getElementById('info-name').textContent = campaign.name || '-';
                document.getElementById('info-status').textContent = campaign.status || '-';
                document.getElementById('info-template').textContent = campaign.template?.name || '-';
                document.getElementById('info-target-type').textContent = campaign.target_type || '-';
                document.getElementById('info-priority').textContent = campaign.priority || '-';
                document.getElementById('info-created').textContent = campaign.created_at ?
                    new Date(campaign.created_at).toLocaleDateString() :
                    '-';
                document.getElementById('info-scheduled').textContent = campaign.scheduled_at ?
                    new Date(campaign.scheduled_at).toLocaleString() :
                    'Immediate';
                document.getElementById('info-completed').textContent = campaign.sent_at ?
                    new Date(campaign.sent_at).toLocaleString() :
                    '-';
                document.getElementById('info-description').textContent = campaign.description || 'No description provided';

                // Update template preview
                if (campaign.template) {
                    const template = campaign.template;
                    let previewHtml = `
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="font-medium text-gray-900">${template.name}</h4>
                            <span class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded">${template.type}</span>
                        </div>
                        <div class="border-t pt-3">
                            <p class="font-medium text-sm text-gray-700">Title: ${template.title}</p>
                            <p class="text-sm text-gray-600 mt-2">${template.content}</p>
                        </div>
                    </div>
                `;
                    document.getElementById('template-preview').innerHTML = previewHtml;
                }

                // Update action buttons based on status
                updateActionButtons();
            }

            function updateActionButtons() {
                const retryBtn = document.getElementById('retry-btn');
                const pauseBtn = document.getElementById('pause-btn');
                const cancelBtn = document.getElementById('cancel-btn');

                // Enable/disable buttons based on campaign status
                if (campaign.status === 'completed' || campaign.status === 'cancelled') {
                    pauseBtn.disabled = true;
                    cancelBtn.disabled = true;
                }

                if (!campaign.failed_sends) {
                    retryBtn.disabled = true;
                }

                if (campaign.status === 'processing') {
                    pauseBtn.textContent = 'Pause Campaign';
                } else if (campaign.status === 'paused') {
                    pauseBtn.innerHTML = '<i class="fas fa-play"></i><span>Resume Campaign</span>';
                }
            }

            async function loadLogs(page = 1) {
                currentPage = page;
                const filter = document.getElementById('log-filter').value;

                try {
                    const params = new URLSearchParams({
                        page: page,
                        ...(filter && {
                            status: filter
                        })
                    });

                    const response = await fetch(`/admin/notifications/data/campaigns/${campaignId}/logs?${params}`);
                    const data = await response.json();

                    if (data.success) {
                        const tbody = document.getElementById('logs-table');
                        tbody.innerHTML = data.data.map(log => `
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${log.recipient_email || log.recipient_phone || 'N/A'}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                ${log.platform || 'Unknown'}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getLogStatusClass(log.status)}">
                                    ${log.status}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                ${log.sent_at ? new Date(log.sent_at).toLocaleString() : '-'}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                ${log.error_message || (log.status === 'sent' ? 'Delivered' : '-')}
                            </td>
                        </tr>
                    `).join('');

                        // Update pagination info
                        document.getElementById('logs-showing').textContent = data.data.length;
                        document.getElementById('logs-total').textContent = data.total || 0;
                        document.getElementById('page-info').textContent = `Page ${page}`;

                        // Update pagination buttons
                        document.getElementById('prev-btn').disabled = page <= 1;
                        document.getElementById('next-btn').disabled = data.data.length < 20; // Assuming 20 per page
                    }
                } catch (error) {
                    console.error('Error loading logs:', error);
                }
            }

            function getStatusClass(status) {
                switch (status) {
                    case 'pending':
                        return 'bg-yellow-100 text-yellow-800';
                    case 'processing':
                        return 'bg-blue-100 text-blue-800';
                    case 'completed':
                        return 'bg-green-100 text-green-800';
                    case 'failed':
                        return 'bg-red-100 text-red-800';
                    case 'cancelled':
                        return 'bg-gray-100 text-gray-800';
                    case 'paused':
                        return 'bg-orange-100 text-orange-800';
                    default:
                        return 'bg-gray-100 text-gray-800';
                }
            }

            function getLogStatusClass(status) {
                switch (status) {
                    case 'success':
                        return 'bg-green-100 text-green-800';
                    case 'failed':
                        return 'bg-red-100 text-red-800';
                    case 'pending':
                        return 'bg-yellow-100 text-yellow-800';
                    default:
                        return 'bg-gray-100 text-gray-800';
                }
            }

            function refreshLogs() {
                loadLogs(currentPage);
            }

            document.getElementById('log-filter').addEventListener('change', function() {
                loadLogs(1);
            });

            // Action functions
            async function retryFailedSends() {
                if (!confirm('Are you sure you want to retry all failed sends?')) return;

                // Implementation for retry
                alert('Retry functionality will be implemented');
            }

            async function pauseCampaign() {
                if (!confirm('Are you sure you want to pause this campaign?')) return;

                // Implementation for pause/resume
                alert('Pause/Resume functionality will be implemented');
            }

            async function cancelCampaign() {
                if (!confirm('Are you sure you want to cancel this campaign? This action cannot be undone.')) return;

                // Implementation for cancel
                alert('Cancel functionality will be implemented');
            }
        </script>
    @endpush
@endsection
