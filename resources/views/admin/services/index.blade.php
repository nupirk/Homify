@extends('layouts.app')

@section('title', 'Services - ServiceCo Admin')

@section('content')
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Manage Services</h1>

            <a href="{{ route('admin.services.create') }}"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                <i class="fas fa-plus"></i>
                <span>Add Service</span>
            </a>
        </div>

        <!-- Services Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Image</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Service Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tags</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($services as $service)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if ($service->image_url)
                                        <img src="{{ asset('storage/' . $service->image_url) }}" alt="{{ $service->name }}"
                                            class="w-16 h-12 object-cover rounded-lg border border-gray-200">
                                    @else
                                        <div
                                            class="w-16 h-12 bg-gray-100 rounded-lg flex items-center justify-center border border-gray-200">
                                            <i class="fas fa-image text-gray-400 text-lg"></i>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $service->name }}</div>
                                    <div class="text-sm text-gray-500">{{ Str::limit($service->description, 50) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $service->category->name ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @if ($service->pricing && $service->pricing->count() > 0)
                                        @if ($service->pricing->count() == 1)
                                            Rs {{ number_format($service->pricing->first()->base_price, 2) }}
                                        @else
                                            Rs {{ number_format($service->pricing->min('base_price'), 2) }} -
                                            {{ number_format($service->pricing->max('base_price'), 2) }}
                                        @endif
                                    @else
                                        <span class="text-gray-500">Price not set</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-wrap gap-1">
                                        @if ($service->is_featured)
                                            <span
                                                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <i class="fas fa-star mr-1"></i>
                                                Featured
                                            </span>
                                        @endif
                                        @if ($service->is_popular)
                                            <span
                                                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                <i class="fas fa-fire mr-1"></i>
                                                Popular
                                            </span>
                                        @endif
                                        @if (!$service->is_featured && !$service->is_popular)
                                            <span class="text-gray-400 text-xs">—</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if ($service->is_active)
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            Active
                                        </span>
                                    @else
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            <i class="fas fa-times-circle mr-1"></i>
                                            Inactive
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <a href="{{ route('admin.services.show', $service->service_id) }}"
                                        class="text-indigo-600 hover:text-indigo-900">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </a>
                                    <span class="text-gray-300">|</span>
                                    <a href="{{ route('admin.services.edit', $service->service_id) }}"
                                        class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-edit mr-1"></i>Edit
                                    </a>
                                    <span class="text-gray-300">|</span>
                                    <form action="{{ route('admin.services.destroy', $service->service_id) }}"
                                        method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-900"
                                            onclick="return confirm('Are you sure you want to delete this service?')">
                                            <i class="fas fa-trash mr-1"></i>Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    <div class="flex flex-col items-center py-8">
                                        <i class="fas fa-concierge-bell text-gray-300 text-4xl mb-4"></i>
                                        <p class="text-lg font-medium text-gray-500 mb-2">No services found</p>
                                        <p class="text-sm text-gray-400">Get started by creating your first service</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
