{{-- filepath: c:\Users\kchnp\Desktop\Urban\be\resources\views\admin\earnings\edit-commission-rule.blade.php --}}
@extends('layouts.app')

@section('title', 'Edit Commission Rule')

@section('content')
    <div class="p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Edit Commission Rule</h1>
                <p class="text-gray-600 mt-1">Update commission rates for services and partners</p>
            </div>
            <a href="{{ route('admin.earnings.commission-rules') }}"
                class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Commission Rules
            </a>
        </div>

        <!-- Form -->
        <div class="bg-white rounded-lg shadow">
            <form action="{{ route('admin.earnings.commission-rules.update', $rule->rule_id) }}" method="POST"
                class="p-6 space-y-6">
                @csrf
                @method('PUT')

                <!-- Service and Location -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Service and Location</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="service_id" class="block text-sm font-medium text-gray-700">Service *</label>
                            <select id="service_id" name="service_id" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select Service</option>
                                @foreach ($services as $service)
                                    <option value="{{ $service->service_id }}"
                                        {{ old('service_id', $rule->service_id) == $service->service_id ? 'selected' : '' }}>
                                        {{ $service->name }} ({{ $service->category?->name }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">Leave blank for a rule that applies to all services</p>
                            @error('service_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-700">City</label>
                            <input type="text" id="city" name="city" value="{{ old('city', $rule->city) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p class="mt-1 text-sm text-gray-500">Leave blank for a rule that applies to all cities</p>
                            @error('city')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Amount Slab -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Amount Range</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="slab_start" class="block text-sm font-medium text-gray-700">Minimum Amount (₹)
                                *</label>
                            <input type="number" id="slab_start" name="slab_start"
                                value="{{ old('slab_start', $rule->slab_start) }}" min="0" step="0.01" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('slab_start')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="slab_end" class="block text-sm font-medium text-gray-700">Maximum Amount (₹)</label>
                            <input type="number" id="slab_end" name="slab_end"
                                value="{{ old('slab_end', $rule->slab_end) }}" min="0" step="0.01"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p class="mt-1 text-sm text-gray-500">Leave blank for unlimited maximum</p>
                            @error('slab_end')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Commission Percentages -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Commission Split</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="partner_pct" class="block text-sm font-medium text-gray-700">Partner Percentage (%)
                                *</label>
                            <input type="number" id="partner_pct" name="partner_pct"
                                value="{{ old('partner_pct', $rule->partner_pct) }}" min="0" max="100"
                                step="0.01" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('partner_pct')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="platform_pct" class="block text-sm font-medium text-gray-700">Platform Percentage
                                (%) *</label>
                            <input type="number" id="platform_pct" name="platform_pct"
                                value="{{ old('platform_pct', $rule->platform_pct) }}" min="0" max="100"
                                step="0.01" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('platform_pct')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="mt-2 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                        <div class="flex">
                            <i class="fas fa-exclamation-triangle text-yellow-400 mt-0.5 mr-2"></i>
                            <div class="text-sm">
                                <strong class="text-yellow-800">Note:</strong>
                                <span class="text-yellow-700">Partner and platform percentages must add up to exactly
                                    100%.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Validity Period -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Validity Period</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="valid_from" class="block text-sm font-medium text-gray-700">Valid From *</label>
                            <input type="date" id="valid_from" name="valid_from"
                                value="{{ old('valid_from', $rule->valid_from->format('Y-m-d')) }}" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('valid_from')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="valid_to" class="block text-sm font-medium text-gray-700">Valid To</label>
                            <input type="date" id="valid_to" name="valid_to"
                                value="{{ old('valid_to', $rule->valid_to?->format('Y-m-d')) }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p class="mt-1 text-sm text-gray-500">Leave blank for no expiry date</p>
                            @error('valid_to')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                    <a href="{{ route('admin.earnings.commission-rules') }}"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
                        Cancel
                    </a>
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                        <i class="fas fa-save mr-2"></i>
                        Update Commission Rule
                    </button>
                </div>
            </form>
        </div>

        <!-- Rule Usage Information -->
        <div class="mt-6 bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Rule Usage Impact</h3>
            <div class="space-y-3">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                    <div>
                        <p class="text-sm text-gray-700">
                            <strong>Current Rule:</strong>
                            {{ $rule->service?->name ?? 'All Services' }}
                            @if ($rule->city)
                                in {{ $rule->city }}
                            @endif
                            (₹{{ number_format($rule->slab_start) }}{{ $rule->slab_end ? ' - ₹' . number_format($rule->slab_end) : '+' }})
                        </p>
                        <p class="text-sm text-gray-500 mt-1">
                            Partner: {{ $rule->partner_pct }}% | Platform: {{ $rule->platform_pct }}%
                        </p>
                    </div>
                </div>

                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-yellow-500 mt-1 mr-3"></i>
                    <div>
                        <p class="text-sm text-gray-700">
                            <strong>Impact:</strong>
                            Changes to this rule will affect future earnings calculations. Existing earnings records remain
                            unchanged.
                        </p>
                    </div>
                </div>

                <div class="flex items-start">
                    <i class="fas fa-clock text-purple-500 mt-1 mr-3"></i>
                    <div>
                        <p class="text-sm text-gray-700">
                            <strong>Validity:</strong>
                            Active from {{ $rule->valid_from ? $rule->valid_from->format('M d, Y') : 'N/A' }}
                            @if ($rule->valid_to)
                                to {{ $rule->valid_to->format('M d, Y') }}
                            @else
                                (ongoing)
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-calculate platform percentage when partner percentage changes
        document.getElementById('partner_pct').addEventListener('input', function() {
            const partnerPct = parseFloat(this.value) || 0;
            const platformPct = 100 - partnerPct;
            document.getElementById('platform_pct').value = platformPct.toFixed(2);
        });

        // Auto-calculate partner percentage when platform percentage changes
        document.getElementById('platform_pct').addEventListener('input', function() {
            const platformPct = parseFloat(this.value) || 0;
            const partnerPct = 100 - platformPct;
            document.getElementById('partner_pct').value = partnerPct.toFixed(2);
        });

        // Validate slab_end is greater than slab_start
        document.getElementById('slab_end').addEventListener('input', function() {
            const slabStart = parseFloat(document.getElementById('slab_start').value) || 0;
            const slabEnd = parseFloat(this.value) || 0;

            if (slabEnd > 0 && slabEnd <= slabStart) {
                this.setCustomValidity('Maximum amount must be greater than minimum amount');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
@endsection
