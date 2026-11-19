@extends('layouts.app')

@section('title', ($banner->exists ? 'Edit Banner' : 'New Banner') . ' - Homify Admin')

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <div>
            <a href="{{ route('admin.banners.index') }}"
                class="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-800 mb-2">
                <i class="fas fa-arrow-left mr-2"></i> All banners
            </a>
            <h1 class="text-2xl font-bold text-gray-900">{{ $banner->exists ? 'Edit banner' : 'New banner' }}</h1>
            <p class="text-gray-600">Shown inside the customer app while it is switched on and inside its run window.</p>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                <p class="text-sm font-medium text-red-800 mb-2">Please fix the following:</p>
                <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
            action="{{ $banner->exists ? route('admin.banners.update', $banner) : route('admin.banners.store') }}"
            enctype="multipart/form-data" class="bg-white rounded-lg shadow border border-gray-200">
            @csrf
            @if ($banner->exists)
                @method('PUT')
            @endif

            <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Title *</label>
                        <input type="text" id="title" name="title" required maxlength="100"
                            value="{{ old('title', $banner->title) }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="e.g. Monsoon cleaning offer">
                    </div>
                    <div>
                        <label for="subtitle" class="block text-sm font-medium text-gray-700 mb-2">Subtitle</label>
                        <input type="text" id="subtitle" name="subtitle" maxlength="255"
                            value="{{ old('subtitle', $banner->subtitle) }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="Optional second line">
                    </div>
                </div>

                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-base font-medium text-gray-900 mb-4">Creative</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="image" class="block text-sm font-medium text-gray-700 mb-2">Upload image
                                @unless ($banner->exists)
                                    <span class="text-red-500">*</span>
                                @endunless
                            </label>
                            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"
                                class="w-full text-sm text-gray-600 file:mr-3 file:px-4 file:py-2 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700">
                            <p class="text-xs text-gray-500 mt-1">JPEG, PNG or WebP up to 5MB. Landing pages look best
                                around 1200×600.</p>
                        </div>
                        <div>
                            <label for="image_url" class="block text-sm font-medium text-gray-700 mb-2">…or image
                                URL</label>
                            <input type="url" id="image_url" name="image_url"
                                value="{{ old('image_url', $banner->image_url) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                placeholder="https://…">
                            <p class="text-xs text-gray-500 mt-1">An upload replaces this URL.</p>
                        </div>
                    </div>
                    @if ($banner->image_url)
                        <div class="mt-4">
                            <p class="text-xs text-gray-500 mb-2">Current creative</p>
                            <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}"
                                class="max-h-40 rounded-lg border border-gray-200">
                        </div>
                    @endif
                </div>

                <div class="border-t border-gray-200 pt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="placement" class="block text-sm font-medium text-gray-700 mb-2">Placement *</label>
                        <select id="placement" name="placement" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            @php
                                $placementLabels = [
                                    'home_mid' => 'Home — below categories',
                                    'explore_top' => 'Explore — top',
                                    'cart_top' => 'Cart — top',
                                ];
                            @endphp
                            @foreach ($placements as $placement)
                                <option value="{{ $placement }}"
                                    {{ old('placement', $banner->placement) === $placement ? 'selected' : '' }}>
                                    {{ $placementLabels[$placement] ?? ucfirst(str_replace('_', ' ', $placement)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="display_style" class="block text-sm font-medium text-gray-700 mb-2">Display
                            style *</label>
                        <select id="display_style" name="display_style" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            @foreach ($displayStyles as $style)
                                <option value="{{ $style }}"
                                    {{ old('display_style', $banner->display_style ?? 'banner') === $style ? 'selected' : '' }}>
                                    {{ ucfirst($style) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-2">Sort order</label>
                        <input type="number" id="sort_order" name="sort_order" min="0"
                            value="{{ old('sort_order', $banner->sort_order ?? 0) }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                </div>

                <div class="border-t border-gray-200 pt-6">
                    <label for="service_id" class="block text-sm font-medium text-gray-700 mb-2">Show only for a
                        service</label>
                    <select id="service_id" name="service_id"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <option value="">Every screen</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->service_id }}"
                                {{ (string) old('service_id', $banner->service_id) === (string) $service->service_id ? 'selected' : '' }}>
                                {{ $service->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">
                        A scoped banner renders on the cart only while that service is in it. Home and explore show
                        every-screen banners only.
                    </p>
                </div>

                <div class="border-t border-gray-200 pt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="link_type" class="block text-sm font-medium text-gray-700 mb-2">Tap opens *</label>
                        <select id="link_type" name="link_type" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            @foreach ($linkTypes as $type)
                                <option value="{{ $type }}"
                                    {{ old('link_type', $banner->link_type ?? 'none') === $type ? 'selected' : '' }}>
                                    {{ ucfirst($type) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="link_value" id="link-value-label"
                            class="block text-sm font-medium text-gray-700 mb-2">Link value</label>
                        <input type="text" id="link_value" name="link_value" maxlength="255"
                            value="{{ old('link_value', $banner->link_value) }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            placeholder="Service ID, category ID, URL or promo code">
                        <p id="link-value-hint" class="text-xs text-gray-500 mt-1">Required unless the banner opens nothing.
                            Promo codes must be active and redeemable.</p>
                    </div>
                </div>

                <div class="border-t border-gray-200 pt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="valid_from" class="block text-sm font-medium text-gray-700 mb-2">Starts *</label>
                        <input type="datetime-local" id="valid_from" name="valid_from" required
                            value="{{ old('valid_from', $banner->valid_from?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')) }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                    <div>
                        <label for="valid_until" class="block text-sm font-medium text-gray-700 mb-2">Ends</label>
                        <input type="datetime-local" id="valid_until" name="valid_until"
                            value="{{ old('valid_until', $banner->valid_until?->format('Y-m-d\TH:i')) }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">Leave empty to run until switched off.</p>
                    </div>
                    <div class="flex items-end">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="is_active" value="1"
                                {{ old('is_active', $banner->is_active ?? true) ? 'checked' : '' }}
                                class="h-4 w-4 text-indigo-600 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">Switched on</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-end space-x-3 rounded-b-lg">
                <a href="{{ route('admin.banners.index') }}"
                    class="px-4 py-2 text-sm text-gray-700 hover:text-gray-900">Cancel</a>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-sm">
                    {{ $banner->exists ? 'Save changes' : 'Create banner' }}
                </button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        // Mirrors the app admin's per-link-type copy: what the field means
        // changes with the destination, so the label must change with it.
        (function() {
            const LINK_COPY = {
                none: {
                    label: 'Link value',
                    hint: 'Not used — this banner opens nothing.',
                    placeholder: '',
                },
                service: {
                    label: 'Service ID *',
                    hint: 'Numeric ID of the service this banner opens.',
                    placeholder: 'e.g. 12',
                },
                category: {
                    label: 'Category ID *',
                    hint: 'Numeric ID of the category this banner opens.',
                    placeholder: 'e.g. 3',
                },
                url: {
                    label: 'Destination URL *',
                    hint: 'Must start with http:// or https://.',
                    placeholder: 'https://…',
                },
                promo: {
                    label: 'Promo code *',
                    hint: 'Must be an active code customers can still redeem.',
                    placeholder: 'e.g. WELCOME20',
                },
            };

            const select = document.getElementById('link_type');
            const label = document.getElementById('link-value-label');
            const input = document.getElementById('link_value');
            const hint = document.getElementById('link-value-hint');
            if (!select || !label || !input || !hint) return;

            function sync() {
                const copy = LINK_COPY[select.value] || LINK_COPY.none;
                label.textContent = copy.label;
                hint.textContent = copy.hint;
                input.placeholder = copy.placeholder;
                input.disabled = select.value === 'none';
            }

            select.addEventListener('change', sync);
            sync();
        })();
    </script>
@endpush
