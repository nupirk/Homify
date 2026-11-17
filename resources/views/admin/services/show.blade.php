@extends('layouts.app')

@section('title', 'Service Details')
@section('content')

    <div class="max-w-6xl mx-auto">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">{{ $service->name }}</h1>
                    <p class="text-gray-600 mt-2">Service details and booking information</p>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="{{ route('admin.services.edit', $service) }}"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-edit"></i>
                        <span>Edit</span>
                    </a>
                    <a href="{{ route('admin.services.index') }}"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Service Details -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6">Service Information</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Basic Info -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Name</label>
                                <p class="text-lg text-gray-900">{{ $service->name }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Category</label>
                                @if ($service->category)
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                        {{ $service->category->name }}
                                    </span>
                                @else
                                    <span class="text-gray-400">No Category</span>
                                @endif
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Status</label>
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $service->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $service->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Price</label>
                                @if ($service->pricing && $service->pricing->count() > 0)
                                    @if ($service->pricing->count() == 1)
                                        <p class="text-2xl font-bold text-green-600">
                                            Rs.{{ number_format($service->pricing->first()->base_price, 2) }}
                                        </p>
                                    @else
                                        <p class="text-2xl font-bold text-green-600">
                                            Rs.{{ $service->price_range }}
                                        </p>
                                    @endif
                                @else
                                    <p class="text-lg text-gray-400">No pricing set</p>
                                @endif
                            </div>
                        </div>

                        <!-- Additional Info -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Duration</label>
                                @if ($service->pricing && $service->pricing->first())
                                    <p class="text-lg text-gray-900">
                                        {{ $service->pricing->first()->estimated_duration_min ?? 'Not specified' }}
                                        {{ $service->pricing->first()->estimated_duration_min ? 'minutes' : '' }}</p>
                                @else
                                    <p class="text-lg text-gray-400">Not specified</p>
                                @endif
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Total Orders</label>
                                <p class="text-lg text-gray-900">{{ $service->order_items_count ?? 0 }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Created</label>
                                <p class="text-gray-700">{{ $service->created_at->format('M d, Y h:i A') }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Last Updated</label>
                                <p class="text-gray-700">{{ $service->updated_at->format('M d, Y h:i A') }}</p>
                            </div>
                        </div>
                    </div>

                    @if ($service->description)
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <label class="block text-sm font-medium text-gray-500 mb-2">Description</label>
                            <p class="text-gray-700 leading-relaxed">{{ $service->description }}</p>
                        </div>
                    @endif

                    {{-- Features section - converted from JSON --}}
                    @if ($service->features)
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <label class="block text-sm font-medium text-gray-500 mb-2">Features</label>
                            @php
                                $features = is_string($service->features)
                                    ? json_decode($service->features, true)
                                    : $service->features;
                            @endphp
                            @if (is_array($features) && count($features) > 0)
                                <ul class="space-y-1">
                                    @foreach ($features as $feature)
                                        <li class="flex items-center text-gray-700">
                                            <i class="fas fa-check text-green-500 mr-2"></i>
                                            {{ $feature }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif

                    @if ($service->terms_conditions)
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <label class="block text-sm font-medium text-gray-500 mb-2">Terms & Conditions</label>
                            <p class="text-gray-700 leading-relaxed">{{ $service->terms_conditions }}</p>
                        </div>
                    @endif

                    {{-- Pricing Tiers --}}
                    @if ($service->pricing && $service->pricing->count() > 0)
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <label class="block text-sm font-medium text-gray-500 mb-4">Pricing Tiers</label>
                            <div class="space-y-3">
                                @foreach ($service->pricing as $pricing)
                                    <div class="flex items-center justify-between bg-gray-50 rounded-lg p-3">
                                        <div>
                                            <h4 class="font-medium text-gray-900">{{ $pricing->tier_display_name }}</h4>
                                            @if ($pricing->description)
                                                <p class="text-sm text-gray-600">{{ $pricing->description }}</p>
                                            @endif
                                            @if ($pricing->estimated_duration_min)
                                                <p class="text-xs text-gray-500">{{ $pricing->estimated_duration_min }}
                                                    minutes</p>
                                            @endif
                                        </div>
                                        <div class="text-right">
                                            <p class="font-semibold text-green-600">
                                                Rs.{{ number_format($pricing->base_price, 2) }}</p>
                                            @if ($pricing->unit)
                                                <p class="text-xs text-gray-500">per {{ $pricing->unit }}</p>
                                            @endif
                                            <span
                                                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $pricing->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $pricing->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Service Image & Quick Actions -->
            <div class="space-y-6">
                <!-- Service Image -->
                @if ($service->image_url)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Service Image</h3>
                        <img src="{{ asset('storage/' . $service->image_url) }}" alt="{{ $service->name }}"
                            class="w-full rounded-lg border border-gray-200">
                    </div>
                @endif

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <form action="{{ route('admin.services.toggle-status', $service) }}" method="POST"
                            class="inline-block w-full">
                            @csrf
                            <button type="submit"
                                class="w-full text-left px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center space-x-3 {{ $service->is_active ? 'text-red-600' : 'text-green-600' }}">
                                <i class="fas {{ $service->is_active ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                                <span>{{ $service->is_active ? 'Deactivate' : 'Activate' }} Service</span>
                            </button>
                        </form>

                        <a href="{{ route('admin.orders.index', ['service' => $service->service_id]) }}"
                            class="block px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center space-x-3 text-blue-600">
                            <i class="fas fa-shopping-cart"></i>
                            <span>View Orders</span>
                        </a>

                        <form action="{{ route('admin.services.destroy', $service) }}" method="POST"
                            onsubmit="return confirm('Are you sure you want to delete this service? This action cannot be undone.')"
                            class="inline-block w-full">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="w-full text-left px-4 py-2 rounded-lg hover:bg-red-50 flex items-center space-x-3 text-red-600">
                                <i class="fas fa-trash"></i>
                                <span>Delete Service</span>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Service Statistics</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">Total Bookings</span>
                            <span class="text-lg font-semibold text-gray-900">{{ $service->bookings_count ?? 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">Total Revenue</span>
                            <span class="text-lg font-semibold text-green-600">
                                Rs.{{ number_format(($service->bookings_count ?? 0) * ($service->base_price ?? 0), 2) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Bookings -->
        @if ($service->bookings && $service->bookings->count() > 0)
            <div class="mt-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-semibold text-gray-900">Recent Bookings</h2>
                            <a href="{{ route('admin.bookings.index', ['service' => $service->id]) }}"
                                class="text-blue-600 hover:text-blue-900 font-medium">
                                View All →
                            </a>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Customer</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Amount</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($service->bookings->take(5) as $booking)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div
                                                    class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                                                    <i class="fas fa-user text-gray-500 text-sm"></i>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        {{ $booking->customer->name ?? 'Customer' }}</div>
                                                    <div class="text-sm text-gray-500">
                                                        {{ $booking->customer->email ?? 'No email' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $booking->scheduled_at ? $booking->scheduled_at->format('M d, Y') : 'TBD' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $booking->status === 'completed'
                                        ? 'bg-green-100 text-green-800'
                                        : ($booking->status === 'cancelled'
                                            ? 'bg-red-100 text-red-800'
                                            : 'bg-yellow-100 text-yellow-800') }}">
                                                {{ ucfirst($booking->status ?? 'pending') }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            Rs.{{ number_format($booking->price_agreed ?? ($service->base_price ?? 0), 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="{{ route('admin.bookings.show', $booking->id) }}"
                                                class="text-blue-600 hover:text-blue-900">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
