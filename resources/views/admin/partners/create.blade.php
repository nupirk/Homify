@extends('layouts.app')

@section('title', 'Create Partner')

@section('content')
    <div class="max-w-6xl mx-auto">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Create Partner</h1>
                    <p class="text-gray-600 mt-2">Add a new service partner to your platform</p>
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('admin.partner-applications.index') }}"
                        class="bg-yellow-100 hover:bg-yellow-200 text-yellow-700 px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-file-alt"></i>
                        <span>View Applications</span>
                    </a>
                    <a href="{{ route('admin.partners.index') }}"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Partners</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Create Form -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <form action="{{ route('admin.partners.store') }}" method="POST" enctype="multipart/form-data" class="p-6">
                @csrf

                <!-- Personal Information Section -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
                        <i class="fas fa-user text-blue-600 mr-2"></i>
                        Personal Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name
                                *</label>
                            <input type="text" name="first_name" id="first_name" value="{{ old('first_name') }}" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('first_name') border-red-500 @enderror"
                                placeholder="Enter first name">
                            @error('first_name')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                            <input type="text" name="last_name" id="last_name" value="{{ old('last_name') }}" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('last_name') border-red-500 @enderror"
                                placeholder="Enter last name">
                            @error('last_name')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address
                                *</label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('email') border-red-500 @enderror"
                                placeholder="Enter email address">
                            @error('email')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone Number
                                *</label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone') }}" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('phone') border-red-500 @enderror"
                                placeholder="Enter phone number">
                            @error('phone')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="emergency_contact" class="block text-sm font-medium text-gray-700 mb-2">Emergency
                                Contact</label>
                            <input type="text" name="emergency_contact" id="emergency_contact"
                                value="{{ old('emergency_contact') }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('emergency_contact') border-red-500 @enderror"
                                placeholder="Enter emergency contact">
                            @error('emergency_contact')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="avatar" class="block text-sm font-medium text-gray-700 mb-2">Profile Photo</label>
                            <input type="file" name="avatar" id="avatar" accept="image/*"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('avatar') border-red-500 @enderror">
                            @error('avatar')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-gray-500 text-sm mt-1">Recommended size: 400x400px, Max size: 2MB</p>
                        </div>
                    </div>
                </div>

                <!-- Account Information Section -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
                        <i class="fas fa-key text-blue-600 mr-2"></i>
                        Account Information
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                            <input type="password" name="password" id="password" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('password') border-red-500 @enderror"
                                placeholder="Enter password">
                            @error('password')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">Confirm
                                Password *</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Confirm password">
                        </div>

                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Initial
                                Status</label>
                            <select name="status" id="status"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('status') border-red-500 @enderror">
                                <option value="pending_verification"
                                    {{ old('status', 'pending_verification') == 'pending_verification' ? 'selected' : '' }}>
                                    Pending Verification</option>
                                <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive
                                </option>
                            </select>
                            @error('status')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Services Section -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">
                        <i class="fas fa-tools text-blue-600 mr-2"></i>
                        Services & Skills *
                    </h3>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600">Select the services this partner can provide:</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @forelse ($services as $service)
                                <div
                                    class="flex items-start space-x-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                                    <input type="checkbox" name="services[]" value="{{ $service->service_id }}"
                                        id="service_{{ $service->service_id }}"
                                        {{ in_array($service->service_id, old('services', [])) ? 'checked' : '' }}
                                        class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <div class="flex-1 min-w-0">
                                        <label for="service_{{ $service->service_id }}"
                                            class="block text-sm font-medium text-gray-900 cursor-pointer">
                                            {{ $service->name }}
                                        </label>
                                        @if ($service->description)
                                            <p class="text-xs text-gray-500 mt-1">
                                                {{ Str::limit($service->description, 100) }}</p>
                                        @endif
                                        @if ($service->base_price)
                                            <p class="text-xs text-green-600 mt-1 font-medium">
                                                ${{ number_format($service->base_price, 2) }}</p>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="col-span-full text-center py-4">
                                    <p class="text-gray-500">No services available. Please create services first.</p>
                                    <a href="{{ route('admin.services.create') }}"
                                        class="text-blue-600 hover:text-blue-800 text-sm">
                                        Create Service →
                                    </a>
                                </div>
                            @endforelse
                        </div>
                        @error('services')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                        @if (count($services) > 0)
                            <div class="flex items-center justify-between pt-2">
                                <button type="button" onclick="selectAllServices()"
                                    class="text-sm text-blue-600 hover:text-blue-800">
                                    Select All
                                </button>
                                <button type="button" onclick="unselectAllServices()"
                                    class="text-sm text-gray-600 hover:text-gray-800">
                                    Unselect All
                                </button>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                    <a href="{{ route('admin.partners.index') }}"
                        class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center space-x-2">
                        <i class="fas fa-plus"></i>
                        <span>Create Partner</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function selectAllServices() {
            const checkboxes = document.querySelectorAll('input[name="services[]"]');
            checkboxes.forEach(checkbox => checkbox.checked = true);
        }

        function unselectAllServices() {
            const checkboxes = document.querySelectorAll('input[name="services[]"]');
            checkboxes.forEach(checkbox => checkbox.checked = false);
        }
    </script>
@endsection
