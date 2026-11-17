@extends('layouts.app')

@section('title', 'Partner Applications')

@section('content')
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Partner Applications</h1>
                <p class="text-gray-600 mt-1">Review and manage partner applications</p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('admin.partners.create') }}"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-user-plus"></i>
                    <span>Create Partner Directly</span>
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <i class="fas fa-file-alt text-blue-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-600">Total Applications</p>
                        <p class="text-xl font-bold text-gray-900">{{ $stats['total_applications'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-yellow-100 rounded-lg">
                        <i class="fas fa-clock text-yellow-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-600">Pending</p>
                        <p class="text-xl font-bold text-gray-900">{{ $stats['pending_applications'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <i class="fas fa-search text-blue-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-600">Under Review</p>
                        <p class="text-xl font-bold text-gray-900">{{ $stats['under_review'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <i class="fas fa-check text-green-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-600">Approved</p>
                        <p class="text-xl font-bold text-gray-900">{{ $stats['approved_applications'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-red-100 rounded-lg">
                        <i class="fas fa-times text-red-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-600">Rejected</p>
                        <p class="text-xl font-bold text-gray-900">{{ $stats['rejected_applications'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 rounded-lg">
                        <i class="fas fa-users text-purple-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-600">Converted</p>
                        <p class="text-xl font-bold text-gray-900">{{ $stats['converted_applications'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="bg-white rounded-lg shadow p-6">
            <form method="GET" action="{{ route('admin.partner-applications.index') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" id="search" name="search" value="{{ request('search') }}"
                            placeholder="Name, email, or phone..."
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All Statuses</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="under_review" {{ request('status') === 'under_review' ? 'selected' : '' }}>Under
                                Review</option>
                            <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved
                            </option>
                            <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected
                            </option>
                            <option value="converted" {{ request('status') === 'converted' ? 'selected' : '' }}>Converted
                            </option>
                        </select>
                    </div>

                    <!-- Sort By -->
                    <div>
                        <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                        <select id="sort_by" name="sort_by"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="created_at" {{ request('sort_by') === 'created_at' ? 'selected' : '' }}>
                                Application Date</option>
                            <option value="first_name" {{ request('sort_by') === 'first_name' ? 'selected' : '' }}>Name
                            </option>
                            <option value="email" {{ request('sort_by') === 'email' ? 'selected' : '' }}>Email</option>
                            <option value="status" {{ request('sort_by') === 'status' ? 'selected' : '' }}>Status</option>
                        </select>
                    </div>

                    <!-- Sort Order -->
                    <div>
                        <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-1">Order</label>
                        <select id="sort_order" name="sort_order"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="desc" {{ request('sort_order') === 'desc' ? 'selected' : '' }}>Newest First
                            </option>
                            <option value="asc" {{ request('sort_order') === 'asc' ? 'selected' : '' }}>Oldest First
                            </option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-search"></i>
                        <span>Filter</span>
                    </button>

                    @if (request()->hasAny(['search', 'status', 'sort_by']))
                        <a href="{{ route('admin.partner-applications.index') }}"
                            class="text-gray-600 hover:text-gray-800">
                            <i class="fas fa-times mr-1"></i> Clear Filters
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Applications Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            @if ($applications->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" id="select-all" class="rounded border-gray-300">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Applicant
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Contact
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Services
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Experience
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Applied Date
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($applications as $application)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" name="applications[]"
                                            value="{{ $application->application_id }}"
                                            class="rounded border-gray-300 application-checkbox">
                                    </td>

                                    <!-- Applicant -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $application->full_name }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            ID: #{{ $application->application_id }}
                                        </div>
                                    </td>

                                    <!-- Contact -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $application->email }}</div>
                                        <div class="text-sm text-gray-500">{{ $application->phone }}</div>
                                    </td>

                                    <!-- Services -->
                                    <td class="px-6 py-4">
                                        <div class="flex flex-wrap gap-1">
                                            @forelse($application->services->take(3) as $service)
                                                <span
                                                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    {{ $service->name }}
                                                </span>
                                            @empty
                                                <span class="text-sm text-gray-500">No services</span>
                                            @endforelse
                                            @if ($application->services->count() > 3)
                                                <span
                                                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                    +{{ $application->services->count() - 3 }} more
                                                </span>
                                            @endif
                                        </div>
                                    </td>

                                    <!-- Experience -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $application->experience_years_label }}
                                    </td>

                                    <!-- Status -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $application->status_color }}">
                                            {{ $application->status_label }}
                                        </span>
                                    </td>

                                    <!-- Applied Date -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $application->created_at->format('M d, Y') }}
                                        <div class="text-xs text-gray-500">
                                            {{ $application->created_at->format('g:i A') }}
                                        </div>
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <a href="{{ route('admin.partner-applications.show', $application) }}"
                                                class="text-blue-600 hover:text-blue-900" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>

                                            @if ($application->canBeApproved() || $application->canBeRejected())
                                                <a href="{{ route('admin.partner-applications.review', $application) }}"
                                                    class="text-yellow-600 hover:text-yellow-900" title="Review">
                                                    <i class="fas fa-gavel"></i>
                                                </a>
                                            @endif

                                            @if ($application->isApproved() && $application->status !== 'converted')
                                                <button
                                                    onclick="showConvertModal({{ $application->application_id }}, '{{ $application->full_name }}')"
                                                    class="text-green-600 hover:text-green-900"
                                                    title="Convert to Partner">
                                                    <i class="fas fa-user-plus"></i>
                                                </button>
                                            @endif

                                            @if ($application->status !== 'converted')
                                                <form
                                                    action="{{ route('admin.partner-applications.destroy', $application) }}"
                                                    method="POST" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900"
                                                        title="Delete"
                                                        onclick="return confirm('Are you sure you want to delete this application?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 flex justify-between sm:hidden">
                            {{ $applications->appends(request()->query())->links() }}
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing {{ $applications->firstItem() }} to {{ $applications->lastItem() }} of
                                    {{ $applications->total() }} applications
                                </p>
                            </div>
                            <div>
                                {{ $applications->appends(request()->query())->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <!-- Empty State -->
                <div class="p-6 text-center">
                    <i class="fas fa-file-alt text-gray-400 text-3xl mb-2"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Applications Found</h3>
                    <p class="text-gray-500 mb-4">
                        @if (request()->hasAny(['search', 'status']))
                            No applications match your current filters. Try adjusting your search criteria.
                        @else
                            No partner applications have been submitted yet.
                        @endif
                    </p>
                </div>
            @endif
        </div>

        <!-- Bulk Actions -->
        @if ($applications->count() > 0)
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">Bulk Actions</h3>
                    <div class="flex space-x-2">
                        <button onclick="bulkAction('approve')"
                            class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">
                            <i class="fas fa-check mr-1"></i> Approve Selected
                        </button>
                        <button onclick="bulkAction('reject')"
                            class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">
                            <i class="fas fa-times mr-1"></i> Reject Selected
                        </button>
                        <button onclick="bulkAction('delete')"
                            class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm">
                            <i class="fas fa-trash mr-1"></i> Delete Selected
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Convert to Partner Modal -->
    <div id="convertModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Convert Application to Partner</h3>
                <form id="convertForm" method="POST">
                    @csrf
                    <div class="space-y-4">
                        <p class="text-sm text-gray-600">
                            Convert "<span id="applicantName"></span>" to a partner account? This will change their user
                            role to partner and create a partner profile.
                        </p>

                        <div class="flex items-center">
                            <input type="checkbox" id="send_welcome_email" name="send_welcome_email" checked
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="send_welcome_email" class="ml-2 block text-sm text-gray-900">
                                Send welcome email to partner
                            </label>
                        </div>
                        <input type="checkbox" id="send_welcome_email" name="send_welcome_email" value="1" checked
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="send_welcome_email" class="ml-2 block text-sm text-gray-900">
                            Send welcome email with login credentials
                        </label>
                    </div>
            </div>

            <div class="flex justify-end space-x-3 mt-6">
                <button type="button" onclick="hideConvertModal()"
                    class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit"
                    class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-blue-700">
                    Create Partner Account
                </button>
            </div>
            </form>
        </div>
    </div>
    </div>

    <script>
        // Select all functionality
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.application-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
        });

        // Convert modal functions
        function showConvertModal(applicationId, applicantName) {
            document.getElementById('applicantName').textContent = applicantName;
            document.getElementById('convertForm').action = `/admin/partner-applications/${applicationId}/convert`;
            document.getElementById('convertModal').classList.remove('hidden');
        }

        function hideConvertModal() {
            document.getElementById('convertModal').classList.add('hidden');
        }

        // Bulk actions
        function bulkAction(action) {
            const selectedCheckboxes = document.querySelectorAll('.application-checkbox:checked');

            if (selectedCheckboxes.length === 0) {
                alert('Please select at least one application.');
                return;
            }

            let confirmMessage = `Are you sure you want to ${action} ${selectedCheckboxes.length} application(s)?`;

            if (action === 'reject') {
                const rejectionReason = prompt('Please provide a rejection reason:');
                if (!rejectionReason) return;

                confirmMessage += '\n\nRejection reason: ' + rejectionReason;
            }

            if (confirm(confirmMessage)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route('admin.partner-applications.bulk-action') }}';

                // CSRF token
                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = '{{ csrf_token() }}';
                form.appendChild(csrfToken);

                // Action
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = action;
                form.appendChild(actionInput);

                // Selected applications
                selectedCheckboxes.forEach(checkbox => {
                    const applicationInput = document.createElement('input');
                    applicationInput.type = 'hidden';
                    applicationInput.name = 'applications[]';
                    applicationInput.value = checkbox.value;
                    form.appendChild(applicationInput);
                });

                // Rejection reason if needed
                if (action === 'reject') {
                    const reasonInput = document.createElement('input');
                    reasonInput.type = 'hidden';
                    reasonInput.name = 'rejection_reason';
                    reasonInput.value = rejectionReason;
                    form.appendChild(reasonInput);
                }

                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
@endsection
