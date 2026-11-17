@extends('layouts.app')

@section('title', 'Edit Category')

@section('content')
    <div class="max-w-4xl mx-auto">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Edit Category</h1>
                    <p class="text-gray-600 mt-2">Update category information</p>
                </div>
                <a href="{{ route('admin.categories.index') }}"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Categories</span>
                </a>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <form action="{{ route('admin.categories.update', $category) }}" method="POST" enctype="multipart/form-data"
                class="p-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Name -->
                    <div class="md:col-span-2">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Category Name *</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $category->name) }}"
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('name') border-red-500 @enderror"
                            placeholder="Enter category name">
                        @error('name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Description -->
                    <div class="md:col-span-2">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" id="description" rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('description') border-red-500 @enderror"
                            placeholder="Enter category description">{{ old('description', $category->description) }}</textarea>
                        @error('description')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Icon -->
                    <div>
                        <label for="icon" class="block text-sm font-medium text-gray-700 mb-2">Icon Class</label>
                        <input type="text" name="icon" id="icon" value="{{ old('icon', $category->icon_url) }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('icon') border-red-500 @enderror"
                            placeholder="e.g., fas fa-home">
                        @error('icon')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Parent Category -->
                    <div>
                        <label for="parent_id" class="block text-sm font-medium text-gray-700 mb-2">Parent Category</label>
                        <select name="parent_id" id="parent_id"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('parent_id') border-red-500 @enderror">
                            <option value="">No Parent (Top Level)</option>
                            @foreach ($parentCategories as $parentCategory)
                                <option value="{{ $parentCategory->category_id }}"
                                    {{ old('parent_id', $category->parent_id) == $parentCategory->category_id ? 'selected' : '' }}>
                                    {{ $parentCategory->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('parent_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Sort Order -->
                    <div>
                        <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                        <input type="number" name="sort_order" id="sort_order"
                            value="{{ old('sort_order', $category->sort_order ?? 0) }}" min="0"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('sort_order') border-red-500 @enderror">
                        @error('sort_order')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="is_active" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="is_active" id="is_active"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('is_active') border-red-500 @enderror">
                            <option value="1" {{ old('is_active', $category->is_active) == '1' ? 'selected' : '' }}>
                                Active</option>
                            <option value="0" {{ old('is_active', $category->is_active) == '0' ? 'selected' : '' }}>
                                Inactive</option>
                        </select>
                        @error('is_active')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Current Image -->
                    @if ($category->image)
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Current Image</label>
                            <div class="mb-4">
                                <img src="{{ asset('storage/' . $category->image) }}" alt="{{ $category->name }}"
                                    class="w-32 h-24 object-cover rounded-lg border border-gray-200">
                            </div>
                        </div>
                    @endif

                    <!-- Image Upload -->
                    <div class="md:col-span-2">
                        <label for="image" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ $category->image ? 'Update Image' : 'Category Image' }}
                        </label>
                        <input type="file" name="image" id="image" accept="image/*"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('image') border-red-500 @enderror">
                        @error('image')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-gray-500 text-sm mt-1">
                            {{ $category->image ? 'Leave empty to keep current image. ' : '' }}Recommended size: 400x300px,
                            Max size: 2MB
                        </p>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex items-center justify-end space-x-4 mt-8 pt-6 border-t border-gray-200">
                    <a href="{{ route('admin.categories.index') }}"
                        class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Update Category
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
