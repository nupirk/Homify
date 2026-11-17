@extends('layouts.app')

@section('title', 'Review Partner Application - ' . $application->full_name)

@section('content')
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('admin.partner-applications.index') }}" class="text-gray-600 hover:text-gray-900">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Review Application</h1>
                    <p class="text-gray-600 mt-1">{{ $application->full_name }} - {{ $application->email }}</p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <span class="px-3 py-1 rounded-full text-sm font-medium {{ $application->status_color }}">
                    {{ $application->status_label }}
                </span>
                <span class="text-sm text-gray-500">
                    Submitted {{ $application->created_at->format('M j, Y \a\t g:i A') }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Personal Information -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-user text-blue-600"></i>
                        </div>
                        <h2 class="text-xl font-semibold text-gray-900">Personal Information</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                            <p class="text-gray-900">{{ $application->full_name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                            <p class="text-gray-900">{{ $application->email }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                            <p class="text-gray-900">{{ $application->phone }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Emergency Contact</label>
                            <p class="text-gray-900">{{ $application->emergency_contact ?: 'Not provided' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Address Information -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-map-marker-alt text-green-600"></i>
                        </div>
                        <h2 class="text-xl font-semibold text-gray-900">Address Information</h2>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                            <p class="text-gray-900">{{ $application->address }}</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">City</label>
                                <p class="text-gray-900">{{ $application->city }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">State</label>
                                <p class="text-gray-900">{{ $application->state }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Postal Code</label>
                                <p class="text-gray-900">{{ $application->postal_code }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Professional Information -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-briefcase text-purple-600"></i>
                        </div>
                        <h2 class="text-xl font-semibold text-gray-900">Professional Information</h2>
                    </div>

                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Experience</label>
                                <p class="text-gray-900">{{ $application->experience_years_label }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Hourly Rate</label>
                                <p class="text-gray-900">NPR {{ number_format($application->hourly_rate, 2) }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Why do you want to become a
                                partner?</label>
                            <p class="text-gray-900 bg-gray-50 p-4 rounded-lg">{{ $application->why_partner }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Bio/About Yourself</label>
                            <p class="text-gray-900 bg-gray-50 p-4 rounded-lg whitespace-pre-line">{{ $application->bio }}
                            </p>
                        </div>

                        @if ($application->availability_hours)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Availability Hours</label>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <pre class="text-gray-900 text-sm">{{ json_encode($application->availability_hours, JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            </div>
                        @endif

                        @if ($application->portfolio_links && count($application->portfolio_links) > 0)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Portfolio Links</label>
                                <div class="space-y-2">
                                    @foreach ($application->portfolio_links as $link)
                                        <a href="{{ $link }}" target="_blank"
                                            class="text-blue-600 hover:text-blue-800 underline">
                                            {{ $link }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($application->references && count($application->references) > 0)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">References</label>
                                <div class="space-y-3">
                                    @foreach ($application->references as $reference)
                                        <div class="bg-gray-50 p-4 rounded-lg">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <span class="text-sm font-medium text-gray-700">Name:</span>
                                                    <p class="text-gray-900">{{ $reference['name'] ?? 'N/A' }}</p>
                                                </div>
                                                <div>
                                                    <span class="text-sm font-medium text-gray-700">Phone:</span>
                                                    <p class="text-gray-900">{{ $reference['phone'] ?? 'N/A' }}</p>
                                                </div>
                                                @if (isset($reference['email']))
                                                    <div>
                                                        <span class="text-sm font-medium text-gray-700">Email:</span>
                                                        <p class="text-gray-900">{{ $reference['email'] }}</p>
                                                    </div>
                                                @endif
                                                <div>
                                                    <span class="text-sm font-medium text-gray-700">Relationship:</span>
                                                    <p class="text-gray-900">{{ $reference['relationship'] ?? 'N/A' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Services -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-tools text-indigo-600"></i>
                        </div>
                        <h2 class="text-xl font-semibold text-gray-900">Requested Services</h2>
                    </div>

                    @if ($application->services && $application->services->count() > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach ($application->services as $service)
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h3 class="font-medium text-gray-900">{{ $service->name }}</h3>
                                    <p class="text-sm text-gray-600 mt-1">{{ $service->description }}</p>
                                    <p class="text-sm font-medium text-green-600 mt-2">NPR
                                        {{ number_format($service->base_price, 2) }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500">No services selected</p>
                    @endif
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Documents -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-file-alt text-yellow-600"></i>
                        </div>
                        <h2 class="text-xl font-semibold text-gray-900">KYC Documents</h2>
                    </div>

                    @if ($application->hasDocuments())
                        <div class="space-y-3">
                            @foreach ($application->documents as $index => $document)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center">
                                        <i class="fas fa-file text-gray-400 mr-3"></i>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ ucfirst(str_replace('_', ' ', $document['type'])) }}</p>
                                            <p class="text-xs text-gray-500">{{ $document['name'] }}</p>
                                        </div>
                                    </div>
                                    <a href="{{ route('admin.partner-applications.download-document', [$application, $index]) }}"
                                        class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-4">No documents uploaded</p>
                    @endif
                </div>

                <!-- Review Actions -->
                @if ($application->canBeApproved() || $application->canBeRejected())
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-6">Review Actions</h2>

                        <form action="{{ route('admin.partner-applications.update-status', $application) }}"
                            method="POST">
                            @csrf
                            @method('PATCH')

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Decision</label>
                                    <select name="status"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        required>
                                        <option value="">Select decision...</option>
                                        @if ($application->canBeApproved())
                                            <option value="approved">Approve & Convert to Partner</option>
                                        @endif
                                        @if ($application->canBeRejected())
                                            <option value="rejected">Reject Application</option>
                                        @endif
                                        <option value="under_review">Mark as Under Review</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Admin Notes</label>
                                    <textarea name="admin_notes" rows="3"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="Add any notes for this decision...">{{ old('admin_notes') }}</textarea>
                                </div>

                                <div id="rejection-reason" style="display: none;">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Rejection Reason</label>
                                    <textarea name="rejection_reason" rows="3"
                                        class="w-full border border-red-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                        placeholder="Please provide a reason for rejection...">{{ old('rejection_reason') }}</textarea>
                                </div>

                                <button type="submit"
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200">
                                    Submit Decision
                                </button>
                            </div>
                        </form>
                    </div>
                @endif

                <!-- Partner Account Created -->
                @if ($application->status === 'converted' && $application->createdPartner)
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-6">Partner Account</h2>

                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-green-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-green-800">
                                        Partner Account Created
                                    </h3>
                                    <div class="mt-2 text-sm text-green-700">
                                        <p>Partner account has been successfully created for
                                            <strong>{{ $application->createdPartner->full_name }}</strong>.
                                        </p>
                                        <p class="mt-1">The existing user account has been converted to a partner role.
                                        </p>
                                        <p class="mt-1">Email: {{ $application->createdPartner->email }}</p>
                                        <p class="mt-1">
                                            <a href="{{ route('admin.partners.show', $application->createdPartner) }}"
                                                class="text-green-600 hover:text-green-800 underline">
                                                View Partner Profile →
                                            </a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Application Timeline -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6">Application Timeline</h2>

                    <div class="space-y-4">
                        <div class="flex items-start">
                            <div class="w-2 h-2 bg-blue-600 rounded-full mt-2 mr-3"></div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Application Submitted</p>
                                <p class="text-xs text-gray-500">
                                    {{ $application->created_at->format('M j, Y \a\t g:i A') }}</p>
                            </div>
                        </div>

                        @if ($application->reviewed_at)
                            <div class="flex items-start">
                                <div class="w-2 h-2 bg-green-600 rounded-full mt-2 mr-3"></div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Reviewed by
                                        {{ $application->reviewedBy->name ?? 'Admin' }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $application->reviewed_at->format('M j, Y \a\t g:i A') }}</p>
                                </div>
                            </div>
                        @endif

                        @if ($application->status === 'approved' && $application->approved_at)
                            <div class="flex items-start">
                                <div class="w-2 h-2 bg-green-600 rounded-full mt-2 mr-3"></div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Application Approved</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $application->approved_at->format('M j, Y \a\t g:i A') }}</p>
                                </div>
                            </div>
                        @endif

                        @if ($application->status === 'converted' && $application->createdPartner)
                            <div class="flex items-start">
                                <div class="w-2 h-2 bg-purple-600 rounded-full mt-2 mr-3"></div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">User Converted to Partner</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $application->createdPartner->full_name }} converted to partner role</p>
                                </div>
                            </div>
                        @endif

                        @if ($application->status === 'rejected' && $application->rejected_at)
                            <div class="flex items-start">
                                <div class="w-2 h-2 bg-red-600 rounded-full mt-2 mr-3"></div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Application Rejected</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $application->rejected_at->format('M j, Y \a\t g:i A') }}</p>
                                    @if ($application->rejection_reason)
                                        <p class="text-xs text-red-600 mt-1">Reason: {{ $application->rejection_reason }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($application->admin_notes)
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Admin Notes</h2>
                        <p class="text-gray-700 bg-gray-50 p-4 rounded-lg">{{ $application->admin_notes }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelect = document.querySelector('select[name="status"]');
            const rejectionReasonDiv = document.getElementById('rejection-reason');
            const rejectionReasonTextarea = document.querySelector('textarea[name="rejection_reason"]');

            // Initialize state based on current selection
            updateRejectionReasonField(statusSelect.value);

            statusSelect.addEventListener('change', function() {
                updateRejectionReasonField(this.value);
            });

            function updateRejectionReasonField(status) {
                if (status === 'rejected') {
                    rejectionReasonDiv.style.display = 'block';
                    rejectionReasonTextarea.required = true;
                    rejectionReasonTextarea.disabled = false;
                } else {
                    rejectionReasonDiv.style.display = 'none';
                    rejectionReasonTextarea.required = false;
                    rejectionReasonTextarea.disabled = true;
                    rejectionReasonTextarea.value = '';
                }
            }
        });
    </script>
@endsection
