@extends('layouts.app')

@section('title', 'Create Notification Template - ServiceCo Admin')

@section('content')
    <div class="max-w-4xl mx-auto space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Create Notification Template</h1>
                <p class="text-gray-600">Create a reusable notification template</p>
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
                <form id="template-form" method="POST" action="{{ route('admin.notifications.templates.store') }}"
                    class="space-y-6">
                    @csrf

                    <!-- Basic Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Template Name
                                *</label>
                            <input type="text" id="name" name="name" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Enter template name">
                            <div class="text-red-500 text-sm mt-1 hidden" id="name-error"></div>
                        </div>

                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700 mb-2">Template Type
                                *</label>
                            <select id="type" name="type" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select type</option>
                                <optgroup label="Booking Related">
                                    <option value="booking_confirmed">Booking Confirmed</option>
                                    <option value="booking_updated">Booking Updated</option>
                                    <option value="booking_cancelled">Booking Cancelled</option>
                                </optgroup>
                                <optgroup label="Partner Related">
                                    <option value="partner_assigned">Partner Assigned</option>
                                    <option value="partner_on_way">Partner On Way</option>
                                    <option value="service_started">Service Started</option>
                                    <option value="service_completed">Service Completed</option>
                                </optgroup>
                                <optgroup label="Payment Related">
                                    <option value="payment_due">Payment Due</option>
                                    <option value="payment_received">Payment Received</option>
                                </optgroup>
                                <optgroup label="Other">
                                    <option value="review_request">Review Request</option>
                                    <option value="promotional">Promotional</option>
                                    <option value="system_update">System Update</option>
                                    <option value="reminder">Reminder</option>
                                    <option value="custom">Custom</option>
                                </optgroup>
                            </select>
                            <div class="text-red-500 text-sm mt-1 hidden" id="type-error"></div>
                        </div>
                    </div>

                    <!-- Delivery Method -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="delivery_method" class="block text-sm font-medium text-gray-700 mb-2">Delivery
                                Method</label>
                            <select id="delivery_method" name="delivery_method"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select delivery method (optional)</option>
                                <option value="push">Push Notification</option>
                                <option value="email">Email</option>
                                <option value="sms">SMS</option>
                                <option value="in_app">In-App Notification</option>
                                <option value="multiple">Multiple Methods</option>
                            </select>
                            <p class="text-sm text-gray-500 mt-1">How should this notification be delivered to users?</p>
                            <div class="text-red-500 text-sm mt-1 hidden" id="delivery_method-error"></div>
                        </div>

                        <div>
                            <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">Priority
                                Level</label>
                            <select id="priority" name="priority"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select priority (optional)</option>
                                <option value="low">Low</option>
                                <option value="normal" selected>Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                            <div class="text-red-500 text-sm mt-1 hidden" id="priority-error"></div>
                        </div>
                    </div>

                    <!-- Title and Subject -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
                            <input type="text" id="title" name="title" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Enter notification title">
                            <div class="text-red-500 text-sm mt-1 hidden" id="title-error"></div>
                        </div>

                        <div id="subject-field" class="hidden">
                            <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">Email Subject</label>
                            <input type="text" id="subject" name="subject"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Enter email subject">
                            <div class="text-red-500 text-sm mt-1 hidden" id="subject-error"></div>
                        </div>
                    </div>

                    <!-- Message Content -->
                    <div>
                        <label for="content" class="block text-sm font-medium text-gray-700 mb-2">Message Content *</label>
                        <textarea id="content" name="content" rows="6" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Enter message content. You can use variables like @{{ customer_name }}, @{{ partner_name }}, @{{ order_id }}, etc."></textarea>
                        <p class="text-sm text-gray-500 mt-2">
                            Available variables: @{{ customer_name }}, @{{ partner_name }}, @{{ service_name }},
                            @{{ order_id }}, @{{ amount }}, @{{ date }}
                        </p>
                        <div class="text-red-500 text-sm mt-1 hidden" id="content-error"></div>
                    </div>

                    <!-- Email Template (for email type only) -->
                    <div id="email-template-field" class="hidden">
                        <label for="email_template" class="block text-sm font-medium text-gray-700 mb-2">Email Template
                            (HTML)</label>
                        <textarea id="email_template" name="email_template" rows="8"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono text-sm"
                            placeholder="Enter HTML email template..."></textarea>
                        <p class="text-sm text-gray-500 mt-2">
                            Use @{{ content }} to include the message content above in your HTML template.
                        </p>
                        <div class="text-red-500 text-sm mt-1 hidden" id="email_template-error"></div>
                    </div>

                    <!-- Action Button (for push notifications) -->
                    <div id="action-fields" class="hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="action_text" class="block text-sm font-medium text-gray-700 mb-2">Action
                                    Button
                                    Text</label>
                                <input type="text" id="action_text" name="action_text"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="e.g., View Order, Open App">
                                <div class="text-red-500 text-sm mt-1 hidden" id="action_text-error"></div>
                            </div>

                            <div>
                                <label for="action_url" class="block text-sm font-medium text-gray-700 mb-2">Action
                                    URL</label>
                                <input type="url" id="action_url" name="action_url"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="https://example.com/action">
                                <div class="text-red-500 text-sm mt-1 hidden" id="action_url-error"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Settings -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Template Settings</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="category"
                                    class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                                <select id="category" name="category"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    <option value="">Select category</option>
                                    <option value="booking">Booking Related</option>
                                    <option value="payment">Payment Related</option>
                                    <option value="marketing">Marketing</option>
                                    <option value="system">System</option>
                                    <option value="reminder">Reminder</option>
                                </select>
                                <div class="text-red-500 text-sm mt-1 hidden" id="category-error"></div>
                            </div>

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
                                <div class="text-red-500 text-sm mt-1 hidden" id="priority-error"></div>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center space-x-6">
                            <label class="flex items-center">
                                <input type="checkbox" id="is_active" name="is_active" checked
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <span class="ml-2 text-sm text-gray-700">Active Template</span>
                            </label>

                            <label class="flex items-center">
                                <input type="checkbox" id="allow_variables" name="allow_variables" checked
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <span class="ml-2 text-sm text-gray-700">Allow Variable Substitution</span>
                            </label>
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea id="description" name="description" rows="3"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Enter template description (optional)"></textarea>
                        <div class="text-red-500 text-sm mt-1 hidden" id="description-error"></div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                        <button type="button" onclick="previewTemplate()"
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2 rounded-lg flex items-center space-x-2">
                            <i class="fas fa-eye"></i>
                            <span>Preview</span>
                        </button>

                        <div class="flex items-center space-x-3">
                            <a href="{{ route('admin.notifications.index') }}"
                                class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2 rounded-lg">
                                Cancel
                            </a>
                            <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg flex items-center space-x-2">
                                <i class="fas fa-save"></i>
                                <span>Create Template</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="preview-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-lg max-w-2xl w-full max-h-[80vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Template Preview</h3>
                    <button onclick="closePreview()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="preview-content" class="space-y-4">
                    <!-- Preview content will be inserted here -->
                </div>
                <div class="mt-6 flex justify-end">
                    <button onclick="closePreview()"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Modal (Success/Error) -->
    <div id="notificationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-xl bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 id="notificationTitle" class="text-xl font-bold">Notification</h3>
                <button onclick="closeNotificationModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="text-center">
                <div id="notificationIcon" class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full">
                    <!-- Icon will be populated here -->
                </div>
                <p id="notificationMessage" class="text-gray-700 mb-6">Message will appear here</p>
                <button type="button" onclick="closeNotificationModal()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    OK
                </button>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            // Show/hide fields based on delivery method
            document.getElementById('delivery_method').addEventListener('change', function() {
                const deliveryMethod = this.value;
                const subjectField = document.getElementById('subject-field');
                const emailTemplateField = document.getElementById('email-template-field');
                const actionFields = document.getElementById('action-fields');

                // Reset visibility
                subjectField.classList.add('hidden');
                emailTemplateField.classList.add('hidden');
                actionFields.classList.add('hidden');

                // Show relevant fields based on delivery method
                if (deliveryMethod === 'email' || deliveryMethod === 'multiple') {
                    subjectField.classList.remove('hidden');
                    emailTemplateField.classList.remove('hidden');
                    document.getElementById('subject').required = (deliveryMethod === 'email');
                } else {
                    document.getElementById('subject').required = false;
                }

                if (deliveryMethod === 'push' || deliveryMethod === 'in_app' || deliveryMethod === 'multiple') {
                    actionFields.classList.remove('hidden');
                }
            });

            // Also keep the type listener for any other functionality
            document.getElementById('type').addEventListener('change', function() {
                // Type changes don't affect field visibility anymore
                // Could be used for other type-specific logic in the future
            });

            // Form submission - now using standard form submission
            document.getElementById('template-form').addEventListener('submit', function(e) {
                // Clear previous errors
                document.querySelectorAll('[id$="-error"]').forEach(el => {
                    el.classList.add('hidden');
                    el.textContent = '';
                });

                // Basic client-side validation
                const requiredFields = ['name', 'type', 'title', 'content'];
                let hasErrors = false;

                requiredFields.forEach(field => {
                    const element = document.getElementById(field);
                    if (!element.value.trim()) {
                        const errorEl = document.getElementById(field + '-error');
                        if (errorEl) {
                            errorEl.textContent = 'This field is required';
                            errorEl.classList.remove('hidden');
                        }
                        hasErrors = true;
                    }
                });

                if (hasErrors) {
                    e.preventDefault();
                    return false;
                }

                // Show loading state
                const submitButton = this.querySelector('button[type="submit"]');
                const originalText = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating...';

                // Form will submit normally after this
            });

            // Preview functionality
            function previewTemplate() {
                const data = {
                    type: document.getElementById('type').value,
                    title: document.getElementById('title').value,
                    subject: document.getElementById('subject').value,
                    content: document.getElementById('content').value,
                    email_template: document.getElementById('email_template').value,
                    action_text: document.getElementById('action_text').value,
                    action_url: document.getElementById('action_url').value
                };

                let previewHtml = '';

                if (data.type === 'email') {
                    previewHtml = `
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-900 mb-2">Email Preview</h4>
                        <div class="bg-gray-50 p-4 rounded">
                            <p class="text-sm text-gray-600 mb-2"><strong>Subject:</strong> ${data.subject || 'No subject'}</p>
                            <div class="border-t pt-2">
                                ${data.email_template ? data.email_template.replace('@{{ content }}', data.content) : data.content}
                            </div>
                        </div>
                    </div>
                `;
                } else if (data.type === 'push') {
                    previewHtml = `
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-900 mb-2">Push Notification Preview</h4>
                        <div class="bg-gray-800 text-white p-4 rounded-lg max-w-sm">
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-bell text-white text-sm"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-sm">${data.title || 'Notification Title'}</p>
                                    <p class="text-sm text-gray-300 mt-1">${data.content || 'Notification content'}</p>
                                    ${data.action_text ? `<button class="bg-blue-600 text-white px-3 py-1 rounded text-xs mt-2">${data.action_text}</button>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                } else {
                    previewHtml = `
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-900 mb-2">${data.type.toUpperCase()} Preview</h4>
                        <div class="bg-gray-50 p-4 rounded">
                            <p class="font-medium">${data.title || 'Title'}</p>
                            <p class="text-gray-700 mt-2">${data.content || 'Content'}</p>
                        </div>
                    </div>
                `;
                }

                document.getElementById('preview-content').innerHTML = previewHtml;
                document.getElementById('preview-modal').classList.remove('hidden');
            }

            function closePreview() {
                document.getElementById('preview-modal').classList.add('hidden');
            }

            // Close modal on outside click
            document.getElementById('preview-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closePreview();
                }
            });

            // Notification Modal Functions
            function showNotificationModal(type, title, message, callback = null) {
                document.getElementById('notificationTitle').textContent = title;
                document.getElementById('notificationMessage').textContent = message;

                const iconDiv = document.getElementById('notificationIcon');
                const titleElement = document.getElementById('notificationTitle');

                if (type === 'success') {
                    iconDiv.className = 'flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-green-100';
                    iconDiv.innerHTML = '<i class="fas fa-check text-2xl text-green-600"></i>';
                    titleElement.className = 'text-xl font-bold text-green-800 mb-4';
                } else if (type === 'error') {
                    iconDiv.className = 'flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100';
                    iconDiv.innerHTML = '<i class="fas fa-times text-2xl text-red-600"></i>';
                    titleElement.className = 'text-xl font-bold text-red-800 mb-4';
                } else {
                    iconDiv.className = 'flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-blue-100';
                    iconDiv.innerHTML = '<i class="fas fa-info text-2xl text-blue-600"></i>';
                    titleElement.className = 'text-xl font-bold text-blue-800 mb-4';
                }

                document.getElementById('notificationModal').classList.remove('hidden');

                // Store callback for later use
                window.notificationCallback = callback;

                // Auto-hide success messages after 3 seconds
                if (type === 'success' && callback) {
                    setTimeout(() => {
                        closeNotificationModal();
                        if (callback) callback();
                    }, 3000);
                }
            }

            function closeNotificationModal() {
                document.getElementById('notificationModal').classList.add('hidden');
                window.notificationCallback = null;
            }

            // Close notification modal on outside click
            document.getElementById('notificationModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeNotificationModal();
                }
            });

            // Check for flash messages on page load
            document.addEventListener('DOMContentLoaded', function() {
                @if (session('success'))
                    showNotificationModal('success', 'Success', '{{ session('success') }}', () => {
                        window.location.href = '{{ route('admin.notifications.index') }}';
                    });
                @endif

                @if (session('error'))
                    showNotificationModal('error', 'Error', '{{ session('error') }}');
                @endif

                @if ($errors->any())
                    const errorMessages = @json($errors->all());
                    showNotificationModal('error', 'Validation Errors', errorMessages.join('\\n'));
                @endif
            });
        </script>
    @endpush
@endsection
