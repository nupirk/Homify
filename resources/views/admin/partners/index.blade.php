@extends('layouts.app')

@section('title', 'Partners - ServiceCo Admin')

@section('content')
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Partner Management</h1>
                <p class="text-gray-600 mt-1">Manage partners and review applications</p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('admin.partner-applications.index') }}"
                    class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Partner Applications</span>
                    @if (isset($pendingApplicationsCount) && $pendingApplicationsCount > 0)
                        <span
                            class="bg-white text-purple-600 px-2 py-1 rounded-full text-xs font-bold ml-2">{{ $pendingApplicationsCount }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.partners.create') }}"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-plus"></i>
                    <span>Add New Partner</span>
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Partners</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $stats['total_partners'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <i class="fas fa-user-check text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Active Partners</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $stats['active_partners'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 rounded-lg">
                        <i class="fas fa-certificate text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Verified Partners</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $stats['verified_partners'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-yellow-100 rounded-lg">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Pending Verification</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $stats['pending_verification'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-orange-100 rounded-lg">
                        <i class="fas fa-clipboard-list text-orange-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Applications</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $stats['pending_applications'] ?? 0 }}</p>
                        <p class="text-xs text-gray-500">Awaiting review</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="bg-white rounded-lg shadow p-6">
            <form method="GET" action="{{ route('admin.partners.index') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div class="md:col-span-2">
                        <div class="relative">
                            <input type="text" name="search" value="{{ request('search') }}"
                                placeholder="Search partners by name, email, phone..."
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                        </div>
                    </div>

                    <!-- Active Status Filter -->
                    <div>
                        <select name="active_status"
                            class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Activity Status</option>
                            <option value="active" {{ request('active_status') === 'active' ? 'selected' : '' }}>Active
                                Partners</option>
                            <option value="inactive" {{ request('active_status') === 'inactive' ? 'selected' : '' }}>
                                Inactive Partners</option>
                        </select>
                    </div>

                    <!-- Verification Status Filter -->
                    <div>
                        <select name="verification_status"
                            class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Verification Status</option>
                            <option value="verified" {{ request('verification_status') === 'verified' ? 'selected' : '' }}>
                                Verified</option>
                            <option value="pending_verification"
                                {{ request('verification_status') === 'pending_verification' ? 'selected' : '' }}>Pending
                                Verification</option>
                            <option value="suspended"
                                {{ request('verification_status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                            <option value="inactive" {{ request('verification_status') === 'inactive' ? 'selected' : '' }}>
                                Not Verified</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-between items-center">
                    <div class="flex space-x-2">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                        <a href="{{ route('admin.partners.index') }}"
                            class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                    </div>

                    <div class="flex space-x-2">
                        <select name="sort_by" class="py-2 px-3 border border-gray-300 rounded-lg">
                            <option value="created_at" {{ request('sort_by') === 'created_at' ? 'selected' : '' }}>Date
                                Joined</option>
                            <option value="first_name" {{ request('sort_by') === 'first_name' ? 'selected' : '' }}>Name
                            </option>
                            <option value="email" {{ request('sort_by') === 'email' ? 'selected' : '' }}>Email</option>
                        </select>
                        <select name="sort_order" class="py-2 px-3 border border-gray-300 rounded-lg">
                            <option value="desc" {{ request('sort_order') === 'desc' ? 'selected' : '' }}>Descending
                            </option>
                            <option value="asc" {{ request('sort_order') === 'asc' ? 'selected' : '' }}>Ascending
                            </option>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <!-- Recent Applications Section -->
        @if (isset($recentApplications) && $recentApplications->count() > 0)
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Partner Applications</h3>
                    <a href="{{ route('admin.partner-applications.index') }}"
                        class="text-blue-600 hover:text-blue-800 text-sm">
                        View All Applications →
                    </a>
                </div>
                <div class="space-y-3">
                    @foreach ($recentApplications->take(5) as $application)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded border">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    @php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'under_review' => 'bg-blue-100 text-blue-800',
                                            'approved' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800',
                                            'converted' => 'bg-purple-100 text-purple-800',
                                        ];
                                    @endphp
                                    <span
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $statusColors[$application->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst(str_replace('_', ' ', $application->status)) }}
                                    </span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $application->first_name }}
                                        {{ $application->last_name }}</p>
                                    <p class="text-xs text-gray-500">{{ $application->email }} • Applied
                                        {{ $application->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                            <div class="flex space-x-2">
                                <a href="{{ route('admin.partner-applications.show', $application) }}"
                                    class="text-blue-600 hover:text-blue-900 text-sm">
                                    <i class="fas fa-eye"></i>
                                </a>
                                @if ($application->status === 'pending')
                                    <button onclick="quickReview('{{ $application->id }}')"
                                        class="text-green-600 hover:text-green-900 text-sm" title="Quick Review">
                                        <i class="fas fa-check"></i>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Partners Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            @if ($partners->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Partner
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Contact
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Services
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Rating
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Activity & Verification
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Joined
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($partners as $partner)
                                <tr class="hover:bg-gray-50">
                                    <!-- Partner Info -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                @if ($partner->avatar_url)
                                                    <img class="h-10 w-10 rounded-full object-cover"
                                                        src="{{ $partner->avatar_url }}" alt="{{ $partner->name }}">
                                                @else
                                                    <div
                                                        class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                                        <i class="fas fa-user text-gray-600"></i>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $partner->first_name }} {{ $partner->last_name }}
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    ID: #{{ $partner->user_id }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Contact -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ $partner->email }}</div>
                                        <div class="text-sm text-gray-500">{{ $partner->phone }}</div>
                                    </td>

                                    <!-- Services -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            @if ($partner->services && $partner->services->count() > 0)
                                                @foreach ($partner->services->take(2) as $service)
                                                    <span
                                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-1 mb-1">
                                                        {{ $service->name }}
                                                    </span>
                                                @endforeach
                                                @if ($partner->services->count() > 2)
                                                    <span
                                                        class="text-xs text-gray-500">+{{ $partner->services->count() - 2 }}
                                                        more</span>
                                                @endif
                                            @else
                                                <span class="text-sm text-gray-500">No services assigned</span>
                                            @endif
                                        </div>
                                    </td>

                                    <!-- Rating -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if ($partner->partnerProfile && $partner->partnerProfile->rating_avg > 0)
                                            <div class="flex items-center">
                                                <span class="text-yellow-400">★</span>
                                                <span
                                                    class="ml-1">{{ number_format($partner->partnerProfile->rating_avg, 1) }}</span>
                                                <span
                                                    class="text-xs text-gray-500 ml-1">({{ $partner->partnerProfile->total_ratings }})</span>
                                            </div>
                                        @else
                                            <span class="text-gray-500">No ratings</span>
                                        @endif
                                    </td>

                                    <!-- Status Column -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col space-y-2">
                                            <!-- Activity Status -->
                                            <div class="flex items-center">
                                                <span class="text-xs font-medium text-gray-500 w-16">Active:</span>
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    {{ $partner->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                    <span
                                                        class="w-2 h-2 {{ $partner->is_active ? 'bg-green-400' : 'bg-red-400' }} rounded-full mr-1"></span>
                                                    {{ $partner->is_active ? 'Yes' : 'No' }}
                                                </span>
                                            </div>

                                            <!-- Verification Status -->
                                            <div class="flex items-center">
                                                <span class="text-xs font-medium text-gray-500 w-16">Verified:</span>
                                                @if ($partner->partnerProfile)
                                                    @php
                                                        $status = $partner->partnerProfile->status;
                                                        $isVerified = $status === 'active';
                                                        $statusText = $isVerified
                                                            ? 'Yes'
                                                            : ucfirst(str_replace('_', ' ', $status));
                                                        $statusColors = [
                                                            'active' => 'bg-green-100 text-green-800',
                                                            'pending_verification' => 'bg-yellow-100 text-yellow-800',
                                                            'suspended' => 'bg-red-100 text-red-800',
                                                            'inactive' => 'bg-gray-100 text-gray-800',
                                                        ];
                                                    @endphp
                                                    <span
                                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$status] ?? 'bg-gray-100 text-gray-800' }}">
                                                        @if ($isVerified)
                                                            <i class="fas fa-check-circle mr-1"></i>
                                                        @elseif($status === 'pending_verification')
                                                            <i class="fas fa-clock mr-1"></i>
                                                        @elseif($status === 'suspended')
                                                            <i class="fas fa-ban mr-1"></i>
                                                        @else
                                                            <i class="fas fa-times-circle mr-1"></i>
                                                        @endif
                                                        {{ $statusText }}
                                                    </span>
                                                @else
                                                    <span
                                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                        <i class="fas fa-times-circle mr-1"></i>
                                                        No Profile
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Joined Date -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $partner->created_at->format('M d, Y') }}
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="{{ route('admin.partners.show', $partner) }}"
                                                class="text-blue-600 hover:text-blue-900" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.partners.edit', $partner) }}"
                                                class="text-green-600 hover:text-green-900" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>

                                            <!-- Toggle Status -->
                                            <form action="{{ route('admin.partners.toggle-status', $partner) }}"
                                                method="POST" class="inline">
                                                @csrf
                                                <button type="submit"
                                                    class="{{ $partner->is_active ? 'text-orange-600 hover:text-orange-900' : 'text-green-600 hover:text-green-900' }}"
                                                    title="{{ $partner->is_active ? 'Deactivate' : 'Activate' }}"
                                                    onclick="return confirm('Are you sure you want to {{ $partner->is_active ? 'deactivate' : 'activate' }} this partner?')">
                                                    <i
                                                        class="fas fa-{{ $partner->is_active ? 'ban' : 'check-circle' }}"></i>
                                                </button>
                                            </form>

                                            <!-- Delete -->
                                            <form action="{{ route('admin.partners.destroy', $partner) }}" method="POST"
                                                class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900"
                                                    title="Delete"
                                                    onclick="return confirm('Are you sure you want to delete this partner? This action cannot be undone.')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
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
                            {{ $partners->appends(request()->query())->links() }}
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing {{ $partners->firstItem() }} to {{ $partners->lastItem() }} of
                                    {{ $partners->total() }} partners
                                </p>
                            </div>
                            <div>
                                {{ $partners->appends(request()->query())->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <!-- Empty State -->
                <div class="p-6 text-center">
                    <i class="fas fa-users text-gray-400 text-3xl mb-2"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Partners Found</h3>
                    <p class="text-gray-500 mb-4">
                        @if (request()->hasAny(['search', 'status', 'verification']))
                            No partners match your current filters. Try adjusting your search criteria.
                        @else
                            Get started by adding your first partner to the system.
                        @endif
                    </p>
                    <a href="{{ route('admin.partners.create') }}"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>
                        Add First Partner
                    </a>
                </div>
            @endif
        </div>
    </div>

    <script>
        function quickReview(applicationId) {
            if (confirm('Mark this application for review?')) {
                // You could implement AJAX here or redirect to review page
                window.location.href = `/admin/partner-applications/${applicationId}`;
            }
        }
    </script>
@endsection
