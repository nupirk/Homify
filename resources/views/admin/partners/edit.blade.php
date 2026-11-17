@extends('layouts.app')

@section('title', 'Edit Partner')

@section('content')
    <div class="p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Edit Partner: {{ $partner->name }}</h1>
                <p class="text-gray-600 mt-1">Update partner information and settings</p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('admin.partners.show', $partner) }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                    <i class="fas fa-eye mr-2"></i>
                    View Partner
                </a>
                <a href="{{ route('admin.partners.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Partners
                </a>
            </div>
        </div>

        <!-- Form -->
        <div class="bg-white rounded-lg shadow">
            <form action="{{ route('admin.partners.update', $partner) }}" method="POST" enctype="multipart/form-data"
                class="p-6 space-y-6">
                @csrf
                @method('PUT')

                <!-- Personal Information -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Personal Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700">First Name *</label>
                            <input type="text" id="first_name" name="first_name"
                                value="{{ old('first_name', $partner->first_name) }}" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('first_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                            <input type="text" id="last_name" name="last_name"
                                value="{{ old('last_name', $partner->last_name) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('last_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email *</label>
                            <input type="email" id="email" name="email" value="{{ old('email', $partner->email) }}"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">Phone *</label>
                            <input type="tel" id="phone" name="phone" value="{{ old('phone', $partner->phone) }}"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('phone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">New Password</label>
                            <input type="password" id="password" name="password"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p class="mt-1 text-sm text-gray-500">Leave blank to keep current password</p>
                            @error('password')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm New
                                Password</label>
                            <input type="password" id="password_confirmation" name="password_confirmation"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Current Avatar</label>
                            @if ($partner->avatar_url)
                                <div class="mt-2">
                                    <img src="{{ asset($partner->avatar_url) }}" alt="Current Avatar"
                                        class="w-16 h-16 rounded-full object-cover">
                                </div>
                            @else
                                <p class="mt-2 text-sm text-gray-500">No avatar uploaded</p>
                            @endif
                        </div>

                        <div>
                            <label for="avatar" class="block text-sm font-medium text-gray-700">New Avatar</label>
                            <input type="file" id="avatar" name="avatar" accept="image/*"
                                class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            <p class="mt-1 text-sm text-gray-500">JPG, PNG. Max 2MB</p>
                            @error('avatar')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Business Information -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Business Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="business_name" class="block text-sm font-medium text-gray-700">Business Name</label>
                            <input type="text" id="business_name" name="business_name"
                                value="{{ old('business_name', $partner->partnerProfile?->business_name) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('business_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="hourly_rate" class="block text-sm font-medium text-gray-700">Hourly Rate (NPR)</label>
                            <input type="number" step="0.01" min="0" id="hourly_rate" name="hourly_rate"
                                value="{{ old('hourly_rate', $partner->partnerProfile?->hourly_rate) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('hourly_rate')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="business_address" class="block text-sm font-medium text-gray-700">Business
                                Address</label>
                            <textarea id="business_address" name="business_address" rows="2"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('business_address', $partner->partnerProfile?->business_address) }}</textarea>
                            @error('business_address')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="experience_years" class="block text-sm font-medium text-gray-700">Experience
                                (Years)</label>
                            <input type="number" min="0" max="60" id="experience_years" name="experience_years"
                                value="{{ old('experience_years', $partner->partnerProfile?->experience_years) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('experience_years')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="bio" class="block text-sm font-medium text-gray-700">Bio</label>
                            <textarea id="bio" name="bio" rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('bio', $partner->partnerProfile?->bio) }}</textarea>
                            @error('bio')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Services -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Services *</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($services as $service)
                            <div class="flex items-center">
                                <input type="checkbox" id="service_{{ $service->service_id }}" name="services[]"
                                    value="{{ $service->service_id }}"
                                    {{ in_array($service->service_id, old('services', $partner->services->pluck('service_id')->toArray())) ? 'checked' : '' }}
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="service_{{ $service->service_id }}" class="ml-3 block text-sm text-gray-700">
                                    {{ $service->name }}
                                    <span class="text-gray-500">({{ $service->category->name ?? 'Uncategorized' }})</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('services')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Status -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Verification Status *</h3>
                        <select name="status" id="status"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @foreach (['onboarding', 'pending_verification', 'active', 'inactive', 'suspended'] as $statusOption)
                                <option value="{{ $statusOption }}"
                                    {{ old('status', $partner->partnerProfile?->status ?? 'onboarding') === $statusOption ? 'selected' : '' }}>
                                    {{ ucfirst(str_replace('_', ' ', $statusOption)) }}
                                </option>
                            @endforeach
                        </select>
                        @error('status')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Account Status</h3>
                        <label class="flex items-center">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" id="is_active" name="is_active" value="1"
                                {{ old('is_active', $partner->is_active) ? 'checked' : '' }}
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <span class="ml-3 block text-sm text-gray-700">Active Partner (can log in and receive jobs)</span>
                        </label>
                        @error('is_active')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                    <a href="{{ route('admin.partners.index') }}"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                        Cancel
                    </a>
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                        <i class="fas fa-save mr-2"></i>
                        Update Partner
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
