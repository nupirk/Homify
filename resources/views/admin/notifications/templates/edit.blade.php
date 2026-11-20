@extends('layouts.app')

@section('title', 'Edit Notification Template - ServiceCo Admin')

@section('content')
    <div class="max-w-4xl mx-auto space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Edit Notification Template</h1>
                <p class="text-gray-600">Update template details and content</p>
            </div>
            <div class="flex items-center space-x-3">
                <button onclick="deleteTemplate()"
                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-trash"></i>
                    <span>Delete</span>
                </button>
                <a href="{{ route('admin.notifications.index') }}"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Notifications</span>
                </a>
            </div>
        </div>

        <!-- Loading State -->
        <div id="loading" class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i>
            <p class="text-gray-600 mt-2">Loading template...</p>
        </div>

        <!-- Edit Form -->
        <div id="edit-form-container" class="bg-white rounded-lg shadow border border-gray-200 hidden">
            <div class="p-6">
                <form id="template-form" class="space-y-6">
                    @csrf
                    @method('PUT')

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
                                <option value="push">Push Notification</option>
                                <option value="email">Email</option>
                                <option value="sms">SMS</option>
                                <option value="in_app">In-App</option>
                            </select>
                            <div class="text-red-500 text-sm mt-1 hidden" id="type-error"></div>
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
                                <label for="action_text" class="block text-sm font-medium text-gray-700 mb-2">Action Button
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
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                                <div class="text-red-500 text-sm mt-1 hidden" id="priority-error"></div>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center space-x-6">
                            <label class="flex items-center">
                                <input type="checkbox" id="is_active" name="is_active"
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <span class="ml-2 text-sm text-gray-700">Active Template</span>
                            </label>

                            <label class="flex items-center">
                                <input type="checkbox" id="allow_variables" name="allow_variables"
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

                    <!-- Template Usage Stats -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Usage Statistics</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <p class="text-sm text-blue-600 font-medium">Times Used</p>
                                <p class="text-2xl font-bold text-blue-900" id="usage-count">0</p>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <p class="text-sm text-green-600 font-medium">Last Used</p>
                                <p class="text-sm text-green-900" id="last-used">Never</p>
                            </div>
                            <div class="bg-purple-50 p-4 rounded-lg">
                                <p class="text-sm text-purple-600 font-medium">Created</p>
                                <p class="text-sm text-purple-900" id="created-at">-</p>
                            </div>
                        </div>
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
                                <span>Update Template</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview Modal (same as create form) -->
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

    @push('scripts')
        <script>
            let templateId = null;

            // Get template ID from URL
            document.addEventListener('DOMContentLoaded', function() {
                const pathParts = window.location.pathname.split('/');
                templateId = pathParts[pathParts.length - 2]; // template ID is second to last in URL
                loadTemplate();
            });

            async function loadTemplate() {
                try {
                    const response = await fetch(`/admin/notifications/data/templates/${templateId}`);

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();

                    if (data.success) {
                        const template = data.data;

                        // Populate form fields
                        document.getElementById('name').value = template.name || '';
                        document.getElementById('type').value = template.type || '';
                        document.getElementById('title').value = template.title || '';
                        document.getElementById('subject').value = template.subject || '';
                        document.getElementById('content').value = template.content || '';
                        document.getElementById('email_template').value = template.email_template || '';
                        document.getElementById('action_text').value = template.action_text || '';
                        document.getElementById('action_url').value = template.action_url || '';
                        document.getElementById('category').value = template.category || '';
                        document.getElementById('priority').value = template.priority || 'normal';
                        document.getElementById('description').value = template.description || '';
                        document.getElementById('is_active').checked = template.is_active || false;
                        document.getElementById('allow_variables').checked = template.allow_variables || false;

                        // Update stats
                        document.getElementById('usage-count').textContent = template.usage_count || 0;
                        document.getElementById('last-used').textContent = template.last_used_at ?
                            new Date(template.last_used_at).toLocaleDateString() :
                            'Never';
                        document.getElementById('created-at').textContent = template.created_at ?
                            new Date(template.created_at).toLocaleDateString() :
                            '-';

                        // Trigger type change to show relevant fields
                        document.getElementById('type').dispatchEvent(new Event('change'));

                        // Show form and hide loading
                        document.getElementById('loading').classList.add('hidden');
                        document.getElementById('edit-form-container').classList.remove('hidden');
                    } else {
                        alert('Error loading template: ' + (data.message || 'Unknown error'));
                        window.location.href = '{{ route('admin.notifications.index') }}';
                    }
                } catch (error) {
                    console.error('Error loading template:', error);
                    if (error.message.includes('HTTP error')) {
                        alert(`Network error: ${error.message}. Please check if the server is running.`);
                    } else {
                        alert('An error occurred while loading the template.');
                    }
                    window.location.href = '{{ route('admin.notifications.index') }}';
                }
            }

            // Show/hide fields based on template type (same as create form)
            document.getElementById('type').addEventListener('change', function() {
                const type = this.value;
                const subjectField = document.getElementById('subject-field');
                const emailTemplateField = document.getElementById('email-template-field');
                const actionFields = document.getElementById('action-fields');

                // Reset visibility
                subjectField.classList.add('hidden');
                emailTemplateField.classList.add('hidden');
                actionFields.classList.add('hidden');

                // Show relevant fields
                if (type === 'email') {
                    subjectField.classList.remove('hidden');
                    emailTemplateField.classList.remove('hidden');
                    document.getElementById('subject').required = true;
                } else {
                    document.getElementById('subject').required = false;
                }

                if (type === 'push' || type === 'in_app') {
                    actionFields.classList.remove('hidden');
                }
            });

            // Form submission
            document.getElementById('template-form').addEventListener('submit', async function(e) {
                e.preventDefault();

                // Clear previous errors
                document.querySelectorAll('[id$="-error"]').forEach(el => {
                    el.classList.add('hidden');
                    el.textContent = '';
                });

                const formData = new FormData(this);
                const data = Object.fromEntries(formData.entries());

                // Handle checkboxes
                data.is_active = document.getElementById('is_active').checked;
                data.allow_variables = document.getElementById('allow_variables').checked;

                try {
                    const response = await fetch(`/admin/notifications/data/templates/${templateId}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                                'content')
                        },
                        body: JSON.stringify(data)
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const result = await response.json();

                    if (result.success) {
                        alert('Template updated successfully!');
                        window.location.href = '{{ route('admin.notifications.index') }}';
                    } else {
                        // Show validation errors
                        if (result.errors) {
                            Object.keys(result.errors).forEach(field => {
                                const errorEl = document.getElementById(field + '-error');
                                if (errorEl) {
                                    errorEl.textContent = result.errors[field][0];
                                    errorEl.classList.remove('hidden');
                                }
                            });
                        } else {
                            alert('Error updating template: ' + (result.message || 'Unknown error'));
                        }
                    }
                } catch (error) {
                    console.error('Error updating template:', error);
                    if (error.message.includes('HTTP error')) {
                        alert(`Network error: ${error.message}. Please check if the server is running.`);
                    } else {
                        alert('An error occurred while updating the template. Please try again.');
                    }
                }
            });

            // Delete template
            async function deleteTemplate() {
                if (!confirm('Are you sure you want to delete this template? This action cannot be undone.')) {
                    return;
                }

                try {
                    const response = await fetch(`/admin/notifications/data/templates/${templateId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                                'content')
                        }
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const result = await response.json();

                    if (result.success) {
                        alert('Template deleted successfully!');
                        window.location.href = '{{ route('admin.notifications.index') }}';
                    } else {
                        alert('Error deleting template: ' + (result.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error deleting template:', error);
                    if (error.message.includes('HTTP error')) {
                        alert(`Network error: ${error.message}. Please check if the server is running.`);
                    } else {
                        alert('An error occurred while deleting the template. Please try again.');
                    }
                }
            }

            // Preview functionality (same as create form)
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
        </script>
    @endpush
@endsection
