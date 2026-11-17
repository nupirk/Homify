{{-- filepath: c:\Users\kchnp\Desktop\Urban\be\resources\views\admin\earnings\commission-rules.blade.php --}}
@extends('layouts.app')

@section('title', 'Commission Rules')

@section('content')
    <div class="p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Commission Rules</h1>
                <p class="text-gray-600 mt-1">Manage commission rates for services and partners</p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('admin.earnings.commission-rules.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                    <i class="fas fa-plus mr-2"></i>
                    Create Rule
                </a>
                <a href="{{ route('admin.earnings.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Earnings
                </a>
            </div>
        </div>

        <!-- Commission Rules Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Active Commission Rules</h3>
                <p class="text-sm text-gray-600 mt-1">Rules are applied in order of specificity (service-specific before
                    global rules)</p>
            </div>

            @if ($rules->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Service</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    City</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Amount Range</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Partner %</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Platform %</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Valid Period</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($rules as $rule)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $rule->service?->name ?? 'All Services' }}
                                        </div>
                                        @if ($rule->service)
                                            <div class="text-sm text-gray-500">{{ $rule->service->category?->name }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $rule->city ?? 'All Cities' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        ₹{{ number_format($rule->slab_start, 0) }}
                                        @if ($rule->slab_end)
                                            - ₹{{ number_format($rule->slab_end, 0) }}
                                        @else
                                            +
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            {{ $rule->partner_pct }}%
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $rule->platform_pct }}%
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div>From: {{ $rule->valid_from ? $rule->valid_from->format('M d, Y') : 'N/A' }}
                                        </div>
                                        @if ($rule->valid_to)
                                            <div>To: {{ $rule->valid_to->format('M d, Y') }}</div>
                                        @else
                                            <div class="text-green-600">Ongoing</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @php
                                            $now = now();
                                            $isActive =
                                                $now >= $rule->valid_from &&
                                                ($rule->valid_to === null || $now <= $rule->valid_to);
                                        @endphp
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            {{ $isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ $isActive ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                        <a href="{{ route('admin.earnings.commission-rules.edit', $rule->rule_id) }}"
                                            class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form
                                            action="{{ route('admin.earnings.commission-rules.destroy', $rule->rule_id) }}"
                                            method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                onclick="return confirm('Are you sure you want to delete this commission rule?')"
                                                class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-3 border-t border-gray-200">
                    {{ $rules->links() }}
                </div>
            @else
                <div class="p-6 text-center">
                    <i class="fas fa-percentage text-gray-400 text-3xl mb-2"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Commission Rules</h3>
                    <p class="text-gray-500 mb-4">Create your first commission rule to start managing partner earnings.</p>
                    <a href="{{ route('admin.earnings.commission-rules.create') }}"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                        <i class="fas fa-plus mr-2"></i>
                        Create Commission Rule
                    </a>
                </div>
            @endif
        </div>

        <!-- Information Cards -->
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">Rule Priority</h3>
                        <p class="text-sm text-gray-600 mt-1">More specific rules (service + city) take priority over
                            general rules (all services/cities).</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-calculator text-green-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">Amount Slabs</h3>
                        <p class="text-sm text-gray-600 mt-1">Different commission rates can apply based on order amount
                            ranges.</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-calendar text-purple-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">Date Ranges</h3>
                        <p class="text-sm text-gray-600 mt-1">Rules can have validity periods for temporary promotions or
                            rate changes.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
