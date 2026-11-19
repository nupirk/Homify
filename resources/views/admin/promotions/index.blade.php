@extends('layouts.app')

@section('title', 'Discount Offers - Homify Admin')

@section('content')
    @php
        $now = now();
        $openModalOnError = $errors->any();
        $oldValues = [
            'promo_code' => old('promo_code'),
            'title' => old('title'),
            'description' => old('description'),
            'discount_percent' => old('discount_percent'),
            'discount_amount' => old('discount_amount'),
            'min_order_value' => old('min_order_value'),
            'max_discount' => old('max_discount'),
            'usage_limit' => old('usage_limit'),
            'valid_from' => old('valid_from'),
            'valid_until' => old('valid_until'),
            'is_active' => old('is_active', 1),
        ];
    @endphp

    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Discount Offers</h1>
                <p class="text-gray-600">Promo codes customers apply at checkout</p>
            </div>
            <button type="button" onclick="openPromotionModal('create')"
                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 self-start">
                <i class="fas fa-plus"></i>
                <span>New Discount Offer</span>
            </button>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-indigo-100 rounded-lg">
                        <i class="fas fa-ticket-alt text-indigo-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Offers</p>
                        <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['total']) }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Running Now</p>
                        <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['active']) }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <i class="fas fa-clock text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Scheduled</p>
                        <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['scheduled']) }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-gray-100 rounded-lg">
                        <i class="fas fa-hourglass-end text-gray-500 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Expired</p>
                        <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['expired']) }}</p>
                    </div>
                </div>
            </div>
        </div>

        @if ($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Promotions Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Offer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Discount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Usage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Valid Window</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($promotions as $promotion)
                            @php
                                $startsInFuture = $promotion->valid_from && $promotion->valid_from->isFuture();
                                $hasExpired = $promotion->valid_until && $promotion->valid_until->isPast();
                                $usageLimit = $promotion->usage_limit;
                                $usageUsed = (int) $promotion->usage_count;

                                if (!$promotion->is_active) {
                                    $stateLabel = 'Inactive';
                                    $stateClass = 'bg-gray-100 text-gray-700';
                                } elseif ($hasExpired) {
                                    $stateLabel = 'Expired';
                                    $stateClass = 'bg-gray-100 text-gray-700';
                                } elseif ($startsInFuture) {
                                    $stateLabel = 'Scheduled';
                                    $stateClass = 'bg-blue-100 text-blue-800';
                                } else {
                                    $stateLabel = 'Active';
                                    $stateClass = 'bg-green-100 text-green-800';
                                }
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-mono font-semibold bg-slate-900 text-white">
                                        {{ $promotion->promo_code }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $promotion->title }}</div>
                                    @if ($promotion->description)
                                        <div class="text-xs text-gray-500 max-w-xs truncate">
                                            {{ $promotion->description }}</div>
                                    @endif
                                    <div class="text-xs {{ $promotion->service_id ? 'text-indigo-600' : 'text-gray-400' }}">
                                        Applies to:
                                        {{ $promotion->service?->name ?? 'All services' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    @if ($promotion->discount_percent !== null)
                                        <span class="font-semibold">{{ rtrim(rtrim(number_format((float) $promotion->discount_percent, 2, '.', ''), '0'), '.') }}% off</span>
                                    @elseif ($promotion->discount_amount !== null)
                                        <span class="font-semibold">Rs. {{ number_format((float) $promotion->discount_amount, 2) }} off</span>
                                    @endif
                                    @if ($promotion->max_discount)
                                        <div class="text-xs text-gray-500">
                                            max Rs. {{ number_format((float) $promotion->max_discount, 0) }}</div>
                                    @endif
                                    @if ($promotion->min_order_value)
                                        <div class="text-xs text-gray-500">
                                            min order Rs. {{ number_format((float) $promotion->min_order_value, 0) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ number_format($usageUsed) }}
                                    @if ($usageLimit !== null)
                                        <span class="text-gray-400">/ {{ number_format((int) $usageLimit) }}</span>
                                    @else
                                        <span class="text-gray-400">/ unlimited</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <div>{{ optional($promotion->valid_from)->format('M j, Y') ?? '—' }}</div>
                                    <div class="text-xs text-gray-500">
                                        to {{ optional($promotion->valid_until)->format('M j, Y') ?? '—' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $stateClass }}">
                                        {{ $stateLabel }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm whitespace-nowrap">
                                    <button type="button"
                                        class="text-indigo-600 hover:text-indigo-900 font-medium mr-3"
                                        onclick="openPromotionModal('edit', @js([
                                            'id' => $promotion->promotion_id,
                                            'promo_code' => $promotion->promo_code,
                                            'title' => $promotion->title,
                                            'description' => $promotion->description,
                                            'discount_percent' => $promotion->discount_percent !== null ? (float) $promotion->discount_percent : '',
                                            'discount_amount' => $promotion->discount_amount !== null ? (float) $promotion->discount_amount : '',
                                            'min_order_value' => $promotion->min_order_value !== null ? (float) $promotion->min_order_value : '',
                                            'max_discount' => $promotion->max_discount !== null ? (float) $promotion->max_discount : '',
                                            'usage_limit' => $promotion->usage_limit,
                                            'service_id' => $promotion->service_id,
                                            'valid_from' => optional($promotion->valid_from)->format('Y-m-d\\TH:i'),
                                            'valid_until' => optional($promotion->valid_until)->format('Y-m-d\\TH:i'),
                                            'is_active' => $promotion->is_active,
                                        ]))">
                                        Edit
                                    </button>
                                    <form action="{{ route('admin.promotions.toggle', $promotion->promotion_id) }}"
                                        method="POST" class="inline">
                                        @csrf
                                        <button type="submit"
                                            class="{{ $promotion->is_active ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900' }} font-medium">
                                            {{ $promotion->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-ticket-alt text-3xl text-gray-300 mb-3 block"></i>
                                    <p class="text-sm">No discount offers yet.</p>
                                    <button type="button" onclick="openPromotionModal('create')"
                                        class="inline-block mt-3 text-indigo-600 hover:text-indigo-800 font-medium text-sm">
                                        Create your first promo code
                                    </button>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($promotions->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $promotions->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Create / Edit Modal -->
    <div id="promotionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-16 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-xl bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 id="promotionModalTitle" class="text-xl font-bold text-gray-900">New Discount Offer</h3>
                <button type="button" onclick="closePromotionModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="promotionForm" action="{{ route('admin.promotions.store') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="_method" id="promotionMethod" value="POST">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="promo_code" class="block text-sm font-medium text-gray-700 mb-1">Promo Code</label>
                        <input type="text" name="promo_code" id="promo_code" maxlength="20" required
                            value="{{ $oldValues['promo_code'] }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm uppercase focus:ring-blue-500 focus:border-blue-500"
                            placeholder="WELCOME25">
                    </div>
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                        <input type="text" name="title" id="title" maxlength="100" required
                            value="{{ $oldValues['title'] }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Welcome discount">
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description
                        <span class="text-gray-400 font-normal">(optional)</span></label>
                    <textarea name="description" id="description" rows="2"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Shown to customers when the code is applied">{{ $oldValues['description'] }}</textarea>
                </div>

                <div>
                    <label for="service_id" class="block text-sm font-medium text-gray-700 mb-1">
                        Applies to <span class="text-gray-400 font-normal">(optional)</span>
                    </label>
                    <select name="service_id" id="service_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All services</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->service_id }}">{{ $service->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">
                        A scoped code only discounts that service's line in the cart, and only
                        checks its minimum spend against it.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="discount_percent" class="block text-sm font-medium text-gray-700 mb-1">
                            Discount Percent
                        </label>
                        <div class="relative">
                            <input type="number" name="discount_percent" id="discount_percent" step="0.01" min="0"
                                max="100" value="{{ $oldValues['discount_percent'] }}"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 pr-8 text-sm focus:ring-blue-500 focus:border-blue-500">
                            <span
                                class="absolute inset-y-0 right-3 flex items-center text-gray-400 text-sm">%</span>
                        </div>
                    </div>
                    <div>
                        <label for="discount_amount" class="block text-sm font-medium text-gray-700 mb-1">
                            Discount Amount
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-sm">Rs.</span>
                            <input type="number" name="discount_amount" id="discount_amount" step="0.01" min="0"
                                value="{{ $oldValues['discount_amount'] }}"
                                class="w-full border border-gray-300 rounded-lg pl-10 pr-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                </div>
                <p class="text-xs text-gray-500 -mt-2">
                    Fill exactly one of the two discount fields.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="min_order_value" class="block text-sm font-medium text-gray-700 mb-1">
                            Min Order <span class="text-gray-400 font-normal">(optional)</span>
                        </label>
                        <input type="number" name="min_order_value" id="min_order_value" step="0.01" min="0"
                            value="{{ $oldValues['min_order_value'] }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="max_discount" class="block text-sm font-medium text-gray-700 mb-1">
                            Max Discount <span class="text-gray-400 font-normal">(optional)</span>
                        </label>
                        <input type="number" name="max_discount" id="max_discount" step="0.01" min="0"
                            value="{{ $oldValues['max_discount'] }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="usage_limit" class="block text-sm font-medium text-gray-700 mb-1">
                            Usage Limit <span class="text-gray-400 font-normal">(blank = unlimited)</span>
                        </label>
                        <input type="number" name="usage_limit" id="usage_limit" step="1" min="0"
                            value="{{ $oldValues['usage_limit'] }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="valid_from" class="block text-sm font-medium text-gray-700 mb-1">Valid From</label>
                        <input type="datetime-local" name="valid_from" id="valid_from" required
                            value="{{ $oldValues['valid_from'] }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="valid_until" class="block text-sm font-medium text-gray-700 mb-1">Valid Until</label>
                        <input type="datetime-local" name="valid_until" id="valid_until" required
                            value="{{ $oldValues['valid_until'] }}"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <label class="inline-flex items-center">
                    <input type="checkbox" name="is_active" id="is_active" value="1"
                        {{ $oldValues['is_active'] ? 'checked' : '' }}
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-gray-700">Active</span>
                </label>

                <div class="flex justify-end space-x-3 pt-2">
                    <button type="button" onclick="closePromotionModal()"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm">
                        Cancel
                    </button>
                    <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm">
                        Save Offer
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            const promotionStoreUrl = @js(route('admin.promotions.store'));

            function promotionUpdateUrl(id) {
                return @js(url('admin/promotions')) + '/' + id;
            }

            function setField(id, value) {
                const el = document.getElementById(id);
                if (!el) return;
                el.value = value === null || value === undefined ? '' : value;
            }

            function openPromotionModal(mode, data) {
                const modal = document.getElementById('promotionModal');
                const form = document.getElementById('promotionForm');
                const method = document.getElementById('promotionMethod');

                if (mode === 'edit' && data) {
                    document.getElementById('promotionModalTitle').textContent = 'Edit Discount Offer';
                    form.action = promotionUpdateUrl(data.id);
                    method.value = 'PUT';

                    setField('promo_code', data.promo_code);
                    setField('title', data.title);
                    setField('description', data.description);
                    setField('discount_percent', data.discount_percent);
                    setField('discount_amount', data.discount_amount);
                    setField('min_order_value', data.min_order_value);
                    setField('max_discount', data.max_discount);
                    setField('usage_limit', data.usage_limit);
                    setField('service_id', data.service_id);
                    setField('valid_from', data.valid_from);
                    setField('valid_until', data.valid_until);
                    document.getElementById('is_active').checked = !!data.is_active;
                } else {
                    document.getElementById('promotionModalTitle').textContent = 'New Discount Offer';
                    form.action = promotionStoreUrl;
                    method.value = 'POST';

                    setField('promo_code', '');
                    setField('title', '');
                    setField('description', '');
                    setField('discount_percent', '');
                    setField('discount_amount', '');
                    setField('min_order_value', '');
                    setField('max_discount', '');
                    setField('usage_limit', '');
                    setField('service_id', '');
                    setField('valid_from', '');
                    setField('valid_until', '');
                    document.getElementById('is_active').checked = true;
                }

                modal.classList.remove('hidden');
            }

            function closePromotionModal() {
                document.getElementById('promotionModal').classList.add('hidden');
            }

            @if ($openModalOnError)
                document.addEventListener('DOMContentLoaded', function() {
                    openPromotionModal('create');
                });
            @endif
        </script>
    @endpush
@endsection
