@extends('layouts.app')

@section('title', 'Create Notification Campaign - ServiceCo Admin')

@section('content')
    <div class="max-w-4xl mx-auto space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Create Notification Campaign</h1>
                <p class="text-gray-600">Send targeted notifications to specific user groups</p>
            </div>
            <a href="{{ route('admin.notifications.index') }}"
                class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center space-x-2">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Notifications</span>
            </a>
        </div>

        <!-- Create Form -->
        <div class="bg-white rounded-lg shadow border border-gray-200">
            <div class="p-6">
                <form id="campaign-form" method="POST" action="{{ route('admin.notifications.campaigns.store') }}"
                    class="space-y-6">
                    @csrf

                    <!-- Campaign Basic Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Campaign Name
                                *</label>
                            <input type="text" id="name" name="name" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Enter campaign name">
                            <div class="text-red-500 text-sm mt-1 hidden" id="name-error"></div>
                        </div>

                        <div>
                            <label for="template_id" class="block text-sm font-medium text-gray-700 mb-2">Notification
                                Template *</label>
                            <select id="template_id" name="template_id" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select template</option>
                                @forelse($templates ?? [] as $template)
                                    <option value="{{ $template->template_id }}" data-name="{{ $template->name }}"
                                        data-type="{{ $template->type }}" data-title="{{ $template->title_template }}"
                                        data-content="{{ $template->message_template }}">
                                        {{ $template->name }} ({{ ucfirst(str_replace('_', ' ', $template->type)) }})
                                    </option>
                                @empty
                                    <option value="" disabled>No active templates found</option>
                                @endforelse
                            </select>
                            <div class="text-red-500 text-sm mt-1 hidden" id="template_id-error"></div>
                            @if (empty($templates) || $templates->count() === 0)
                                <p class="text-sm text-yellow-600 mt-1">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    No active templates available.
                                    <a href="{{ route('admin.notifications.templates.create') }}"
                                        class="text-blue-600 hover:underline">Create a template first</a>.
                                </p>
                            @endif
                        </div>
                    </div>

                    <!-- Target Audience -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Target Audience</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="target_type" class="block text-sm font-medium text-gray-700 mb-2">Target Type
                                    *</label>
                                <select id="target_type" name="target_type" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Select target type</option>
                                    <option value="all_users">All Users</option>
                                    <option value="all_partners">All Partners</option>
                                    <option value="active_partners">Active Partners Only</option>
                                    <option value="inactive_partners">Inactive Partners Only</option>
                                    <option value="verified_partners">Verified Partners Only</option>
                                    <option value="negative_balance_partners">Negative Balance Partners</option>
                                    <option value="high_earning_partners">High Earning Partners</option>
                                    <option value="new_partners">New Partners (Last 30 days)</option>
                                    <option value="active_customers">Active Customers</option>
                                    <option value="inactive_customers">Inactive Customers</option>
                                    <option value="custom">Custom Selection</option>
                                </select>
                                <div class="text-red-500 text-sm mt-1 hidden" id="target_type-error"></div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Estimated Recipients</label>
                                <div class="w-full px-4 py-2 border border-blue-200 rounded-lg bg-blue-50">
                                    <span id="target-count" class="text-lg font-semibold text-blue-600">0</span>
                                    <span class="text-gray-600"> recipients</span>
                                    <p class="text-xs text-blue-600 mt-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Updates as you select targeting options
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Custom Selection Fields -->
                        <div id="custom-filters" class="hidden mt-6 space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="user_types" class="block text-sm font-medium text-gray-700 mb-2">User
                                        Types</label>
                                    <div class="space-y-2">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="user_types[]" value="customers"
                                                class="h-4 w-4 text-blue-600">
                                            <span class="ml-2 text-sm">Customers</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="user_types[]" value="partners"
                                                class="h-4 w-4 text-blue-600">
                                            <span class="ml-2 text-sm">Partners</span>
                                        </label>
                                    </div>
                                </div>

                                <div>
                                    <label for="partner_status" class="block text-sm font-medium text-gray-700 mb-2">Partner
                                        Status</label>
                                    <div class="space-y-2">
                                        <label class="flex items-center">
                                            <input type="checkbox" name="partner_status[]" value="active"
                                                class="h-4 w-4 text-blue-600">
                                            <span class="ml-2 text-sm">Active</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="partner_status[]" value="inactive"
                                                class="h-4 w-4 text-blue-600">
                                            <span class="ml-2 text-sm">Inactive</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="partner_status[]" value="verified"
                                                class="h-4 w-4 text-blue-600">
                                            <span class="ml-2 text-sm">Verified</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="min_balance" class="block text-sm font-medium text-gray-700 mb-2">Minimum
                                        Balance</label>
                                    <input type="number" id="min_balance" name="min_balance" step="0.01"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        placeholder="0.00">
                                </div>

                                <div>
                                    <label for="max_balance" class="block text-sm font-medium text-gray-700 mb-2">Maximum
                                        Balance</label>
                                    <input type="number" id="max_balance" name="max_balance" step="0.01"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        placeholder="1000.00">
                                </div>
                            </div>

                            <div>
                                <label for="registration_date_from"
                                    class="block text-sm font-medium text-gray-700 mb-2">Registration Date Range</label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <input type="date" id="registration_date_from" name="registration_date_from"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <input type="date" id="registration_date_to" name="registration_date_to"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Scheduling -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Scheduling</h3>

                        <div class="space-y-4">
                            <div class="flex items-center space-x-4">
                                <label class="flex items-center">
                                    <input type="radio" name="schedule_type" value="immediate" checked
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm font-medium">Send Immediately</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="schedule_type" value="scheduled"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm font-medium">Schedule for Later</span>
                                </label>
                            </div>

                            <div id="schedule-fields" class="hidden grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="scheduled_date"
                                        class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                                    <input type="date" id="scheduled_date" name="scheduled_date"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label for="scheduled_time"
                                        class="block text-sm font-medium text-gray-700 mb-2">Time</label>
                                    <input type="time" id="scheduled_time" name="scheduled_time"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Campaign Settings -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Campaign Settings</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="priority"
                                    class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                                <select id="priority" name="priority"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="low">Low</option>
                                    <option value="normal" selected>Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>

                            <div>
                                <label for="batch_size" class="block text-sm font-medium text-gray-700 mb-2">Batch
                                    Size</label>
                                <select id="batch_size" name="batch_size"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="100">100 per batch</option>
                                    <option value="500" selected>500 per batch</option>
                                    <option value="1000">1000 per batch</option>
                                    <option value="2000">2000 per batch</option>
                                </select>
                                <p class="text-sm text-gray-500 mt-1">Larger batches send faster but may impact performance
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" id="track_opens" name="track_opens" checked
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <span class="ml-2 text-sm text-gray-700">Track Opens/Reads</span>
                            </label>

                            <label class="flex items-center">
                                <input type="checkbox" id="track_clicks" name="track_clicks" checked
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <span class="ml-2 text-sm text-gray-700">Track Click-throughs</span>
                            </label>

                            <label class="flex items-center">
                                <input type="checkbox" id="allow_unsubscribe" name="allow_unsubscribe" checked
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <span class="ml-2 text-sm text-gray-700">Include Unsubscribe Option</span>
                            </label>
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Campaign
                            Description</label>
                        <textarea id="description" name="description" rows="3"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Enter campaign description (optional)"></textarea>
                    </div>

                    <!-- Preview Section -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Preview</h3>
                        <div id="template-preview" class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <p class="text-gray-500 text-center">Select a template to see preview</p>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                        <button type="button" onclick="validateAndPreview()"
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2 rounded-lg flex items-center space-x-2">
                            <i class="fas fa-search"></i>
                            <span>Test & Preview</span>
                        </button>

                        <div class="flex items-center space-x-3">
                            <a href="{{ route('admin.notifications.index') }}"
                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2 rounded-lg">
                                Cancel
                            </a>
                            <button type="button" onclick="saveDraft()"
                                class="bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-2 rounded-lg flex items-center space-x-2">
                                <i class="fas fa-save"></i>
                                <span>Save as Draft</span>
                            </button>
                            <button type="submit"
                                class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg flex items-center space-x-2">
                                <i class="fas fa-paper-plane"></i>
                                <span>Create & Send</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Include Notification Modal Component -->
    <x-modals.notification />

    @push('scripts')
        <script>
            // Recipient counts passed from controller
            const recipientCounts = @json($recipientCounts ?? []);
            console.log('Recipient counts loaded:', recipientCounts);

            // Load templates on page load
            document.addEventListener('DOMContentLoaded', function() {
                console.log('DOMContentLoaded fired');

                // Test if elements exist
                const targetTypeEl = document.getElementById('target_type');
                const countEl = document.getElementById('target-count');
                console.log('target_type element found:', !!targetTypeEl);
                console.log('target-count element found:', !!countEl);

                if (countEl) {
                    countEl.textContent = '0';
                }

                updateTargetCount();
            });

            // Update target count when target type changes
            document.getElementById('target_type').addEventListener('change', function() {
                console.log('Target type changed to:', this.value);
                const targetType = this.value;
                const customFilters = document.getElementById('custom-filters');

                if (targetType === 'custom') {
                    customFilters.classList.remove('hidden');
                } else {
                    customFilters.classList.add('hidden');
                }

                updateTargetCount();
            });

            // Update target count
            function updateTargetCount() {
                console.log('updateTargetCount called');
                const targetType = document.getElementById('target_type').value;
                const countElement = document.getElementById('target-count');

                console.log('targetType:', targetType);

                if (!targetType) {
                    countElement.textContent = '0';
                    return;
                }

                if (targetType === 'custom') {
                    updateCustomTargetCount();
                    return;
                }

                // Get count from preloaded data
                const count = recipientCounts[targetType] || 0;
                console.log('Setting count to:', count);
                countElement.textContent = count.toLocaleString();
            }

            // Update count for custom target type based on selected filters
            function updateCustomTargetCount() {
                const countElement = document.getElementById('target-count');
                const userTypes = document.querySelectorAll('input[name="user_types[]"]:checked');
                const partnerStatuses = document.querySelectorAll('input[name="partner_status[]"]:checked');

                if (userTypes.length === 0 && partnerStatuses.length === 0) {
                    countElement.textContent = '0';
                    return;
                }

                // For now, show descriptive text for custom filters
                // Later, we could make an API call with the custom criteria
                let description = [];
                if (userTypes.length > 0) {
                    const types = Array.from(userTypes).map(cb => cb.value).join(', ');
                    description.push(types);
                }
                if (partnerStatuses.length > 0) {
                    const statuses = Array.from(partnerStatuses).map(cb => cb.value).join(', ');
                    description.push(`(${statuses})`);
                }

                countElement.textContent = description.join(' ') || 'Custom filter';
            }

            // Show/hide schedule fields
            document.querySelectorAll('input[name="schedule_type"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    const scheduleFields = document.getElementById('schedule-fields');
                    if (this.value === 'scheduled') {
                        scheduleFields.classList.remove('hidden');
                        document.getElementById('scheduled_date').required = true;
                        document.getElementById('scheduled_time').required = true;
                    } else {
                        scheduleFields.classList.add('hidden');
                        document.getElementById('scheduled_date').required = false;
                        document.getElementById('scheduled_time').required = false;
                    }
                });
            });

            // Update preview when template is selected
            document.getElementById('template_id').addEventListener('change', function() {
                const option = this.options[this.selectedIndex];
                const previewDiv = document.getElementById('template-preview');

                if (option.value && option.dataset.name) {
                    const templateName = option.dataset.name;
                    const templateType = option.dataset.type;
                    const templateTitle = option.dataset.title;
                    const templateContent = option.dataset.content;

                    let previewHtml = `
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="font-medium text-gray-900">${templateName}</h4>
                            <span class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded">${templateType.replace(/_/g, ' ')}</span>
                        </div>
                        <div class="border-t pt-3">
                            <p class="font-medium text-sm text-gray-700 mb-2">Title:</p>
                            <p class="text-sm bg-gray-100 p-2 rounded mb-3">${templateTitle || 'No title'}</p>
                            <p class="font-medium text-sm text-gray-700 mb-2">Message:</p>
                            <p class="text-sm bg-gray-100 p-2 rounded">${templateContent || 'No message'}</p>
                        </div>
                        <div class="text-xs text-gray-500 border-t pt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Variables like {customer_name}, {order_id} will be replaced with actual values when sent.
                        </div>
                    </div>
                `;

                    previewDiv.innerHTML = previewHtml;
                } else {
                    previewDiv.innerHTML = '<p class="text-gray-500 text-center">Select a template to see preview</p>';
                }
            });

            // Form submission - now using standard form submission
            document.getElementById('campaign-form').addEventListener('submit', function(e) {
                // Clear previous errors
                document.querySelectorAll('[id$="-error"]').forEach(el => {
                    el.classList.add('hidden');
                    el.textContent = '';
                });

                // Basic client-side validation
                const requiredFields = ['name', 'template_id', 'target_type', 'schedule_type'];
                let hasErrors = false;
                let errorMessages = [];

                requiredFields.forEach(field => {
                    const element = document.getElementById(field);
                    if (!element.value.trim()) {
                        const errorEl = document.getElementById(field + '-error');
                        if (errorEl) {
                            errorEl.textContent = 'This field is required';
                            errorEl.classList.remove('hidden');
                        }
                        hasErrors = true;

                        // Collect error for notification
                        const label = element.closest('.space-y-6').querySelector('label')?.textContent ||
                            field;
                        errorMessages.push(label.replace('*', '').trim());
                    }
                });

                // Validate scheduled date if schedule type is scheduled
                const scheduleType = document.getElementById('schedule_type').value;
                const scheduledAt = document.getElementById('scheduled_at');
                if (scheduleType === 'scheduled' && scheduledAt && !scheduledAt.value) {
                    const errorEl = document.getElementById('scheduled_at-error');
                    if (errorEl) {
                        errorEl.textContent = 'Scheduled date is required when schedule type is scheduled';
                        errorEl.classList.remove('hidden');
                    }
                    hasErrors = true;
                    errorMessages.push('Scheduled date');
                }

                if (hasErrors) {
                    e.preventDefault();
                    showNotificationModal('error', 'Validation Error',
                        `Please fill in the following required fields: ${errorMessages.join(', ')}`);
                    return false;
                }

                // Show success message for form submission
                showNotificationModal('info', 'Creating Campaign', 'Your campaign is being created. Please wait...');

                // Form will submit normally
                return true;
            });

            // Save as draft functionality (simplified)
            async function saveDraft() {
                // For now, just submit the form - we can add draft status field later
                showNotificationModal('info', 'Draft Feature',
                    'Draft functionality can be implemented later if needed. For now, the campaign will be created as active.'
                );
            }

            function validateAndPreview() {
                const templateId = document.getElementById('template_id').value;
                const targetType = document.getElementById('target_type').value;

                if (!templateId || !targetType) {
                    showNotificationModal('warning', 'Missing Information', 'Please select a template and target type first.');
                    return;
                }

                showNotificationModal('success', 'Preview Ready',
                    'Check the preview section above to see how your notification will look to recipients.');
            }

            // Update target count when custom filters change
            document.getElementById('custom-filters').addEventListener('change', function() {
                const targetType = document.getElementById('target_type').value;
                if (targetType === 'custom') {
                    updateCustomTargetCount();
                }
            });

            // Also update count when user type checkboxes change
            document.addEventListener('change', function(e) {
                if (e.target.name === 'user_types[]' ||
                    e.target.name === 'partner_status[]' ||
                    e.target.name === 'target_users[]') {
                    const targetType = document.getElementById('target_type').value;
                    if (targetType === 'custom') {
                        updateCustomTargetCount();
                    }
                }
            });
        </script>
    @endpush
@endsection
