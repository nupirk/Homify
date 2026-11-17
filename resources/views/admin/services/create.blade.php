@extends('layouts.app')

@section('title', 'Create Service')

@section('content')
    <div class="max-w-4xl mx-auto">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Create Service</h1>
                    <p class="text-gray-600 mt-2">Add a new service to your platform</p>
                </div>
                <a href="{{ route('admin.services.index') }}"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Services</span>
                </a>
            </div>
        </div>

        <!-- Create Form -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <form action="{{ route('admin.services.store') }}" method="POST" enctype="multipart/form-data" class="p-6">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Name -->
                    <div class="md:col-span-2">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Service Name *</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('name') border-red-500 @enderror"
                            placeholder="Enter service name">
                        @error('name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Category -->
                    <div>
                        <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                        <select name="category_id" id="category_id" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('category_id') border-red-500 @enderror">
                            <option value="">Select a category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->category_id }}"
                                    {{ old('category_id') == $category->category_id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Sort Order -->
                    <div>
                        <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                        <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', 0) }}"
                            min="0"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('sort_order') border-red-500 @enderror"
                            placeholder="0">
                        @error('sort_order')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="is_active" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="is_active" id="is_active"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('is_active') border-red-500 @enderror">
                            <option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ old('is_active') == '0' ? 'selected' : '' }}>Inactive</option>
                        </select>
                        @error('is_active')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Pre-payment -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pre-payment</label>
                        <div class="flex items-center space-x-3 border border-gray-300 rounded-lg px-4 py-2">
                            <input type="checkbox" name="requires_prepay" id="requires_prepay" value="1"
                                {{ old('requires_prepay', '1') ? 'checked' : '' }}
                                class="h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <div>
                                <label for="requires_prepay" class="text-sm font-medium text-gray-900">Requires
                                    pre-payment</label>
                                <p class="text-xs text-gray-500">Customers must pay online (eSewa) in advance. Uncheck
                                    to make this service Cash on Delivery only.</p>
                            </div>
                        </div>
                        @error('requires_prepay')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Description -->
                    <div class="md:col-span-2">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" id="description" rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('description') border-red-500 @enderror"
                            placeholder="Enter service description">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Features -->
                    <div class="md:col-span-2">
                        <label for="features" class="block text-sm font-medium text-gray-700 mb-2">Features</label>
                        <div id="features-container">
                            <div class="feature-item flex items-center space-x-2 mb-2">
                                <input type="text" name="features[]"
                                    class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Enter a feature">
                                <button type="button" onclick="removeFeature(this)"
                                    class="text-red-500 hover:text-red-700">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" onclick="addFeature()" class="mt-2 text-blue-600 hover:text-blue-800">
                            <i class="fas fa-plus"></i> Add Feature
                        </button>
                        @error('features')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Terms & Conditions -->
                    <div class="md:col-span-2">
                        <label for="terms_conditions" class="block text-sm font-medium text-gray-700 mb-2">Terms &
                            Conditions</label>
                        <textarea name="terms_conditions" id="terms_conditions" rows="4"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('terms_conditions') border-red-500 @enderror"
                            placeholder="Enter terms and conditions for this service">{{ old('terms_conditions') }}</textarea>
                        @error('terms_conditions')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Service Image -->
                    <div class="md:col-span-2">
                        <label for="image" class="block text-sm font-medium text-gray-700 mb-2">Service Image</label>

                        <!-- Image Upload Area -->
                        <div
                            class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-gray-400 transition-colors">
                            <div class="space-y-1 text-center">
                                <!-- Image Preview -->
                                <div id="image-preview" class="hidden mb-4">
                                    <img id="preview-img" src="" alt="Preview"
                                        class="mx-auto h-32 w-auto rounded-lg shadow-md">
                                </div>

                                <!-- Upload Icon -->
                                <div id="upload-placeholder">
                                    <i class="fas fa-image mx-auto h-12 w-12 text-gray-400"></i>
                                    <div class="flex text-sm text-gray-600">
                                        <label for="image"
                                            class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                            <span>Upload a file</span>
                                            <input id="image" name="image" type="file" class="sr-only"
                                                accept="image/*" onchange="previewImage(this)">
                                        </label>
                                        <p class="pl-1">or drag and drop</p>
                                    </div>
                                    <p class="text-xs text-gray-500">PNG, JPG, WEBP up to 2MB</p>
                                </div>

                                <!-- Change Image Button (hidden initially) -->
                                <button type="button" id="change-image-btn"
                                    class="hidden mt-2 text-sm text-blue-600 hover:text-blue-500" onclick="changeImage()">
                                    Change Image
                                </button>
                            </div>
                        </div>

                        @error('image')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-gray-500 text-sm mt-1">Recommended size: 800x600px, Max size: 2MB, Formats: JPEG,
                            PNG, WEBP</p>
                    </div>

                    <!-- Additional Options -->
                    <div class="md:col-span-2 border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Additional Options</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Is Featured -->
                            <div class="flex items-center">
                                <input type="checkbox" name="is_featured" id="is_featured" value="1"
                                    {{ old('is_featured') ? 'checked' : '' }}
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="is_featured" class="ml-2 block text-sm text-gray-700">
                                    Featured Service
                                </label>
                            </div>

                            <!-- Is Popular -->
                            <div class="flex items-center">
                                <input type="checkbox" name="is_popular" id="is_popular" value="1"
                                    {{ old('is_popular') ? 'checked' : '' }}
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="is_popular" class="ml-2 block text-sm text-gray-700">
                                    Popular Service
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex items-center justify-end space-x-4 mt-8 pt-6 border-t border-gray-200">
                    <a href="{{ route('admin.services.index') }}"
                        class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Create Service
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function previewImage(input) {
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Show preview
                    document.getElementById('image-preview').classList.remove('hidden');
                    document.getElementById('preview-img').src = e.target.result;

                    // Hide upload placeholder
                    document.getElementById('upload-placeholder').classList.add('hidden');

                    // Show change button
                    document.getElementById('change-image-btn').classList.remove('hidden');
                }
                reader.readAsDataURL(file);
            }
        }

        function changeImage() {
            // Reset file input
            document.getElementById('image').value = '';

            // Hide preview
            document.getElementById('image-preview').classList.add('hidden');

            // Show upload placeholder
            document.getElementById('upload-placeholder').classList.remove('hidden');

            // Hide change button
            document.getElementById('change-image-btn').classList.add('hidden');
        }

        // Drag and drop functionality
        const dropZone = document.querySelector('.border-dashed');

        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('border-blue-500', 'bg-blue-50');
        });

        dropZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('border-blue-500', 'bg-blue-50');
        });

        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('border-blue-500', 'bg-blue-50');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const imageInput = document.getElementById('image');
                imageInput.files = files;
                previewImage(imageInput);
            }
        });

        // Features management
        function addFeature() {
            const container = document.getElementById('features-container');
            const newFeature = document.createElement('div');
            newFeature.className = 'feature-item flex items-center space-x-2 mb-2';
            newFeature.innerHTML = `
                <input type="text" name="features[]"
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Enter a feature">
                <button type="button" onclick="removeFeature(this)" class="text-red-500 hover:text-red-700">
                    <i class="fas fa-trash"></i>
                </button>
            `;
            container.appendChild(newFeature);
        }

        function removeFeature(button) {
            const container = document.getElementById('features-container');
            if (container.children.length > 1) {
                button.parentElement.remove();
            }
        }
    </script>
@endpush
