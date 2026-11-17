@extends('layouts.app')

@section('title', 'Category Details')

@section('content')
    <div class="max-w-4xl mx-auto">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">{{ $category->name }}</h1>
                    <p class="text-gray-600 mt-2">Category details and services</p>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="{{ route('admin.categories.edit', $category) }}"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-edit"></i>
                        <span>Edit</span>
                    </a>
                    <a href="{{ route('admin.categories.index') }}"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Category Details -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6">Category Information</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Basic Info -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Name</label>
                                <p class="text-lg text-gray-900">{{ $category->name }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Status</label>
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $category->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $category->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>

                            @if ($category->icon)
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Icon</label>
                                    <div class="flex items-center space-x-3">
                                        <i class="{{ $category->icon }} text-2xl"
                                            style="color: {{ $category->color ?? '#3B82F6' }}"></i>
                                        <span class="text-gray-700">{{ $category->icon }}</span>
                                    </div>
                                </div>
                            @endif

                            @if ($category->color)
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Color</label>
                                    <div class="flex items-center space-x-3">
                                        <div class="w-6 h-6 rounded border border-gray-300"
                                            style="background-color: {{ $category->color }}"></div>
                                        <span class="text-gray-700">{{ $category->color }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Additional Info -->
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Sort Order</label>
                                <p class="text-lg text-gray-900">{{ $category->sort_order ?? 0 }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Services Count</label>
                                <p class="text-lg text-gray-900">
                                    {{ $category->services_count ?? $category->services->count() }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Created</label>
                                <p class="text-gray-700">{{ $category->created_at->format('M d, Y h:i A') }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Last Updated</label>
                                <p class="text-gray-700">{{ $category->updated_at->format('M d, Y h:i A') }}</p>
                            </div>
                        </div>
                    </div>

                    @if ($category->description)
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <label class="block text-sm font-medium text-gray-500 mb-2">Description</label>
                            <p class="text-gray-700 leading-relaxed">{{ $category->description }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Category Image & Quick Actions -->
            <div class="space-y-6">
                <!-- Category Image -->
                @if ($category->image)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Category Image</h3>
                        <img src="{{ asset('storage/' . $category->image) }}" alt="{{ $category->name }}"
                            class="w-full rounded-lg border border-gray-200">
                    </div>
                @endif

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <form action="{{ route('admin.categories.toggle-status', $category) }}" method="POST"
                            class="inline-block w-full">
                            @csrf
                            <button type="submit"
                                class="w-full text-left px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center space-x-3 {{ $category->is_active ? 'text-red-600' : 'text-green-600' }}">
                                <i class="fas {{ $category->is_active ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                                <span>{{ $category->is_active ? 'Deactivate' : 'Activate' }} Category</span>
                            </button>
                        </form>

                        <a href="{{ route('admin.services.index', ['category' => $category->category_id]) }}"
                            class="block px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center space-x-3 text-blue-600">
                            <i class="fas fa-cogs"></i>
                            <span>View Services</span>
                        </a>

                        <form action="{{ route('admin.categories.destroy', $category) }}" method="POST"
                            onsubmit="return confirm('Are you sure you want to delete this category? This action cannot be undone.')"
                            class="inline-block w-full">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="w-full text-left px-4 py-2 rounded-lg hover:bg-red-50 flex items-center space-x-3 text-red-600">
                                <i class="fas fa-trash"></i>
                                <span>Delete Category</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Services in this Category -->
        @if ($category->services && $category->services->count() > 0)
            <div class="mt-8">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xl font-semibold text-gray-900">Services in this Category</h2>
                            <a href="{{ route('admin.services.create', ['category' => $category->id]) }}"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                                <i class="fas fa-plus"></i>
                                <span>Add Service</span>
                            </a>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Service</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Price</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Duration</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($category->services->take(5) as $service)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                @if ($service->image)
                                                    <img src="{{ asset('storage/' . $service->image) }}"
                                                        alt="{{ $service->name }}"
                                                        class="w-10 h-10 rounded-lg object-cover mr-3">
                                                @endif
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">{{ $service->name }}
                                                    </div>
                                                    @if ($service->description)
                                                        <div class="text-sm text-gray-500">
                                                            {{ Str::limit($service->description, 50) }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            ${{ number_format($service->price, 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $service->duration }} mins
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $service->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $service->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="{{ route('admin.services.show', $service) }}"
                                                class="text-blue-600 hover:text-blue-900 mr-3">View</a>
                                            <a href="{{ route('admin.services.edit', $service) }}"
                                                class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($category->services->count() > 5)
                        <div class="p-6 border-t border-gray-200 text-center">
                            <a href="{{ route('admin.services.index', ['category' => $category->id]) }}"
                                class="text-blue-600 hover:text-blue-900 font-medium">
                                View all {{ $category->services->count() }} services →
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
