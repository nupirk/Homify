@extends('layouts.app')

@section('title', 'Notifications - ServiceCo Admin')

@section('content')
    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Notification Management</h1>
                <p class="text-gray-600">Manage templates, campaigns, and send notifications</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.notifications.templates.create') }}"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-plus"></i>
                    <span>New Template</span>
                </a>
                <a href="{{ route('admin.notifications.campaigns.create') }}"
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-bullhorn"></i>
                    <span>New Campaign</span>
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <i class="fas fa-file-alt text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Templates</p>
                        <p id="total-templates" class="text-2xl font-bold text-gray-900">{{ $totalTemplates ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <i class="fas fa-bullhorn text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Active Campaigns</p>
                        <p id="active-campaigns" class="text-2xl font-bold text-gray-900">{{ $activeCampaigns ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-yellow-100 rounded-lg">
                        <i class="fas fa-paper-plane text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Sent Today</p>
                        <p id="sent-today" class="text-2xl font-bold text-gray-900">{{ $sentToday ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-red-100 rounded-lg">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Failed Today</p>
                        <p id="failed-today" class="text-2xl font-bold text-gray-900">{{ $failedToday ?? 0 }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-lg shadow">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex">
                    <button onclick="switchTab('templates')"
                        class="tab-button active w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm focus:outline-none"
                        data-tab="templates">
                        Templates
                    </button>
                    <button onclick="switchTab('campaigns')"
                        class="tab-button w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm focus:outline-none"
                        data-tab="campaigns">
                        Campaigns
                    </button>
                    <button onclick="switchTab('quick-send')"
                        class="tab-button w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm focus:outline-none"
                        data-tab="quick-send">
                        Quick Send
                    </button>
                </nav>
            </div>

            <!-- Templates Tab -->
            <div id="templates-tab" class="tab-content p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-semibold text-gray-900">Notification Templates</h2>
                    <a href="{{ route('admin.notifications.templates.create') }}"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                        Create Template
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody id="templates-table" class="bg-white divide-y divide-gray-200">
                            @forelse($templates ?? [] as $template)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $template->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ ucfirst(str_replace('_', ' ', $template->type)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $template->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $template->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $template->created_at->format('M d, Y') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="{{ route('admin.notifications.templates.edit', $template->template_id) }}"
                                            class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                                        <button onclick="deleteTemplate({{ $template->template_id }})"
                                            class="text-red-600 hover:text-red-900">Delete</button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                        <div class="flex flex-col items-center">
                                            <i class="fas fa-file-alt text-gray-300 text-4xl mb-3"></i>
                                            <h3 class="text-lg font-medium text-gray-900 mb-1">No templates found</h3>
                                            <p class="text-gray-500 mb-4">Get started by creating your first notification
                                                template.</p>
                                            <a href="{{ route('admin.notifications.templates.create') }}"
                                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                                                Create Template
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Campaigns Tab -->
            <div id="campaigns-tab" class="tab-content p-6 hidden">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-semibold text-gray-900">Notification Campaigns</h2>
                    <a href="{{ route('admin.notifications.campaigns.create') }}"
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm">
                        Create Campaign
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Campaign</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Template</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Recipients</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Scheduled</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody id="campaigns-table" class="bg-white divide-y divide-gray-200">
                            <!-- Campaigns will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Send Tab -->
            <div id="quick-send-tab" class="tab-content p-6 hidden">
                <div class="max-w-2xl">
                    <h2 class="text-lg font-semibold text-gray-900 mb-6">Send Quick Notification</h2>

                    <form id="quick-send-form" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Target Audience</label>
                            <select id="target-type"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="all_users">All Users</option>
                                <option value="all_customers">All Customers</option>
                                <option value="all_partners">All Partners</option>
                                <option value="partners_with_negative_balance">Negative Balance Partners</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                            <input type="text" id="notification-title"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Enter notification title">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                            <textarea id="notification-message" rows="4"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Enter notification message"></textarea>
                        </div>

                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <label class="flex items-center">
                                    <input type="checkbox" id="send-push" checked
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <span class="ml-2 text-sm text-gray-700">Send Push Notification</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" id="send-email"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <span class="ml-2 text-sm text-gray-700">Send Email</span>
                                </label>
                            </div>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                                Send Notification
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Notification Modal Component -->
    <x-modals.notification />

    @push('scripts')
        <script>
            // Tab switching functionality
            function switchTab(tabName) {
                // Hide all tab contents
                document.querySelectorAll('.tab-content').forEach(tab => {
                    tab.classList.add('hidden');
                });

                // Remove active class from all tab buttons
                document.querySelectorAll('.tab-button').forEach(button => {
                    button.classList.remove('active', 'border-blue-500', 'text-blue-600');
                    button.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700',
                        'hover:border-gray-300');
                });

                // Show selected tab content
                document.getElementById(tabName + '-tab').classList.remove('hidden');

                // Add active class to selected tab button
                const activeButton = document.querySelector(`[data-tab="${tabName}"]`);
                activeButton.classList.add('active', 'border-blue-500', 'text-blue-600');
                activeButton.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700',
                    'hover:border-gray-300');
            }

            // Initialize page
            document.addEventListener('DOMContentLoaded', function() {
                // Templates are rendered server-side; the campaigns table and
                // the counters below come from the API.
                loadCampaigns();
            });

            // Load dashboard statistics
            // Load dashboard data
            async function loadDashboard() {
                    try {
                        const response = await fetch('/api/v1/admin/notifications/dashboard', {
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await response.json();

                                if (data.success) {
                                    document.getElementById('total-templates').textContent = data.data.stats
                                        .total_templates;
                                    document.getElementById('active-campaigns').textContent = data.data.stats
                                        .pending_campaigns + data.data.stats.scheduled_campaigns;
                                    document.getElementById('sent-today').textContent = data.data.stats
                                        .successful_sends_today;
                                    document.getElementById('failed-today').textContent = data.data.stats
                                        .failed_sends_today;
                                }
                            }
                            catch (error) {
                                console.error('Error loading dashboard stats:', error);
                            }
                        }

                        // Load templates
                        async function loadTemplates() {
                            try {
                                const response = await fetch('/api/v1/admin/notifications/templates');
                                const data = await response.json();

                                if (data.success) {
                                    const tbody = document.getElementById('templates-table');
                                    tbody.innerHTML = (data.data.data || data.data).map(template => `
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${template.name}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${template.type}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${template.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                    ${template.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${new Date(template.created_at).toLocaleDateString()}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="/admin/notifications/templates/${template.template_id}/edit" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                                <button onclick="deleteTemplate(${template.template_id})" class="text-red-600 hover:text-red-900">Delete</button>
                            </td>
                        </tr>
                    `).join('');
                                }
                            } catch (error) {
                                console.error('Error loading templates:', error);
                            }
                        }

                        // Load campaigns
                        async function loadCampaigns() {
                            try {
                                const response = await fetch('/api/v1/admin/notifications/campaigns');
                                const data = await response.json();

                                if (data.success) {
                                    const tbody = document.getElementById('campaigns-table');
                                    tbody.innerHTML = (data.data.data || data.data).map(campaign => `
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${campaign.name}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${campaign.template?.name || 'N/A'}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusClass(campaign.status)}">
                                    ${campaign.status}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${campaign.total_recipients || 0}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                ${campaign.scheduled_at ? new Date(campaign.scheduled_at).toLocaleDateString() : 'Immediate'}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="/admin/notifications/campaigns/${campaign.campaign_id}" class="text-blue-600 hover:text-blue-900">View</a>
                            </td>
                        </tr>
                    `).join('');
                                }
                            } catch (error) {
                                console.error('Error loading campaigns:', error);
                            }
                        }

                        function getStatusClass(status) {
                            switch (status) {
                                case 'pending':
                                case 'scheduled':
                                    return 'bg-yellow-100 text-yellow-800';
                                case 'sending':
                                case 'processing':
                                    return 'bg-blue-100 text-blue-800';
                                case 'sent':
                                case 'completed':
                                    return 'bg-green-100 text-green-800';
                                case 'failed':
                                    return 'bg-red-100 text-red-800';
                                default:
                                    return 'bg-gray-100 text-gray-800';
                            }
                        }

                        // Quick send form
                        document.getElementById('quick-send-form').addEventListener('submit', async function(e) {
                            e.preventDefault();

                            const formData = {
                                target_type: document.getElementById('target-type').value,
                                title: document.getElementById('notification-title').value,
                                message: document.getElementById('notification-message').value,
                                send_push: document.getElementById('send-push').checked,
                                send_email: document.getElementById('send-email').checked
                            };

                            try {
                                const response = await fetch('/api/v1/admin/notifications/send-quick', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                            .getAttribute(
                                                'content')
                                    },
                                    body: JSON.stringify(formData)
                                });

                                const data = await response.json();

                                if (data.success) {
                                    showNotificationModal('success', 'Success', 'Notification sent successfully!');
                                    document.getElementById('quick-send-form').reset();
                                    loadDashboard();
                                } else {
                                    showNotificationModal('error', 'Error', 'Error sending notification: ' + data
                                        .message);
                                }
                            } catch (error) {
                                console.error('Error sending notification:', error);
                                showNotificationModal('error', 'Error',
                                    'Error sending notification. Please try again.');
                            }
                        });

                        async function deleteTemplate(templateId) {
                            if (confirm('Are you sure you want to delete this template?')) {
                                try {
                                    const response = await fetch(`/api/v1/admin/notifications/templates/${templateId}`, {
                                        method: 'DELETE',
                                        headers: {
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                                .getAttribute(
                                                    'content')
                                        }
                                    });

                                    const data = await response.json();

                                    if (data.success) {
                                        showNotificationModal('success', 'Success', 'Template deleted successfully!');
                                        loadTemplates();
                                        loadDashboard();
                                    } else {
                                        showNotificationModal('error', 'Error', 'Error deleting template: ' + data.message);
                                    }
                                } catch (error) {
                                    console.error('Error deleting template:', error);
                                    showNotificationModal('error', 'Error', 'Error deleting template. Please try again.');
                                }
                            }
                        }
        </script>
    @endpush
@endsection
