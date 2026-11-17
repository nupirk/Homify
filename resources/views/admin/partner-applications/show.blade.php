@extends('layouts.app')

@section('title', 'Partner Application Details - ' . $application->full_name)

@section('content')
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('admin.partner-applications.index') }}" class="text-gray-600 hover:text-gray-900">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Application Details</h1>
                    <p class="text-gray-600 mt-1">{{ $application->full_name }} - {{ $application->email }}</p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <span class="px-3 py-1 rounded-full text-sm font-medium {{ $application->status_color }}">
                    {{ $application->status_label }}
                </span>
                @if ($application->canBeApproved() || $application->canBeRejected())
                    <a href="{{ route('admin.partner-applications.review', $application) }}"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        Review Application
                    </a>
                @endif
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
                                <p class="text-gray-900">${{ number_format($application->hourly_rate, 2) }}/hour</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Why do you want to be a
                                partner?</label>
                            <p class="text-gray-900 bg-gray-50 p-4 rounded-lg">{{ $application->why_partner }}</p>
                        </div>

                        @if ($application->bio)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Bio</label>
                                <p class="text-gray-900 bg-gray-50 p-4 rounded-lg">{{ $application->bio }}</p>
                            </div>
                        @endif

                        @if ($application->previous_work)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Previous Work Experience</label>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <ul class="list-disc list-inside space-y-1">
                                        @foreach ($application->previous_work as $work)
                                            <li class="text-gray-900">{{ $work }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif

                        @if ($application->references)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Professional References</label>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <ul class="list-disc list-inside space-y-1">
                                        @foreach ($application->references as $reference)
                                            <li class="text-gray-900">{{ $reference }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif

                        @if ($application->portfolio_links)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Portfolio Links</label>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <ul class="list-disc list-inside space-y-1">
                                        @foreach ($application->portfolio_links as $link)
                                            <li>
                                                <a href="{{ $link }}" target="_blank"
                                                    class="text-blue-600 hover:text-blue-800 underline">
                                                    {{ $link }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif

                        @if ($application->availability_hours)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Availability Hours</label>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <ul class="list-disc list-inside space-y-1">
                                        @foreach ($application->availability_hours as $day => $hours)
                                            <li class="text-gray-900">
                                                <strong>{{ ucfirst($day) }}:</strong> {{ $hours }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Services Applied For -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-tools text-orange-600"></i>
                        </div>
                        <h2 class="text-xl font-semibold text-gray-900">Services Applied For</h2>
                    </div>

                    @if ($application->services->count() > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach ($application->services as $service)
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h3 class="font-medium text-gray-900">{{ $service->name }}</h3>
                                    <p class="text-sm text-gray-600 mt-1">{{ $service->description }}</p>
                                    <div class="flex items-center mt-2">
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                                            {{ $service->category->name ?? 'No Category' }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500">No services selected</p>
                    @endif
                </div>

                <!-- Documents -->
                @if ($application->hasDocuments())
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-file-alt text-red-600"></i>
                            </div>
                            <h2 class="text-xl font-semibold text-gray-900">Documents</h2>
                        </div>

                        <div class="space-y-3">
                            @foreach ($application->documents as $index => $document)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center">
                                        <i class="fas fa-file text-gray-400 mr-3"></i>
                                        <div>
                                            <span class="text-gray-900 font-medium">{{ $document['name'] }}</span>
                                            <span
                                                class="text-gray-500 text-sm ml-2">({{ ucfirst(str_replace('_', ' ', $document['type'])) }})</span>
                                            @if (isset($document['file_size']))
                                                <span
                                                    class="text-gray-400 text-xs ml-2">{{ number_format($document['file_size'] / 1024, 1) }}
                                                    KB</span>
                                            @endif
                                        </div>
                                    </div>
                                    <a href="{{ route('admin.partner-applications.download-document', [$application, $index]) }}"
                                        class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            @endforeach
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

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Quick Actions</h2>

                    <div class="space-y-3">
                        @if ($application->canBeApproved() || $application->canBeRejected())
                            <a href="{{ route('admin.partner-applications.review', $application) }}"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-center block">
                                Review Application
                            </a>
                        @endif

                        @if ($application->status === 'approved' && $application->status !== 'converted')
                            <form action="{{ route('admin.partner-applications.convert', $application) }}" method="POST"
                                class="w-full">
                                @csrf
                                <button type="submit"
                                    class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                                    Convert to Partner
                                </button>
                            </form>
                        @endif

                        @if ($application->status === 'converted' && $application->createdPartner)
                            <a href="{{ route('admin.partners.show', $application->createdPartner) }}"
                                class="w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-center block">
                                View Partner Profile
                            </a>
                        @endif

                        @if ($application->status !== 'converted')
                            <form action="{{ route('admin.partner-applications.destroy', $application) }}" method="POST"
                                class="w-full"
                                onsubmit="return confirm('Are you sure you want to delete this application?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg">
                                    Delete Application
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <!-- Application Status -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Application Status</h2>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Current Status:</span>
                            <span class="px-2 py-1 rounded-full text-xs font-medium {{ $application->status_color }}">
                                {{ $application->status_label }}
                            </span>
                        </div>

                        @if ($application->reviewed_at)
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Reviewed By:</span>
                                <span class="text-sm text-gray-900">{{ $application->reviewedBy->name ?? 'Admin' }}</span>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Reviewed At:</span>
                                <span class="text-sm text-gray-900">
                                    {{ $application->reviewed_at->format('M j, Y g:i A') }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Contact Information</h2>

                    <div class="space-y-3">
                        <div class="flex items-center">
                            <i class="fas fa-envelope text-gray-400 w-5 mr-3"></i>
                            <a href="mailto:{{ $application->email }}" class="text-blue-600 hover:text-blue-800">
                                {{ $application->email }}
                            </a>
                        </div>

                        <div class="flex items-center">
                            <i class="fas fa-phone text-gray-400 w-5 mr-3"></i>
                            <a href="tel:{{ $application->phone }}" class="text-blue-600 hover:text-blue-800">
                                {{ $application->phone }}
                            </a>
                        </div>

                        @if ($application->emergency_contact)
                            <div class="flex items-center">
                                <i class="fas fa-phone-alt text-gray-400 w-5 mr-3"></i>
                                <a href="tel:{{ $application->emergency_contact }}"
                                    class="text-blue-600 hover:text-blue-800">
                                    {{ $application->emergency_contact }} (Emergency)
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
