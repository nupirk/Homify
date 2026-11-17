@extends('layouts.app')

@section('title', 'Partner Payout Details')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-4 sm:mb-0">
                Payout Details #{{ $payout->payout_id }}
            </h1>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.ledger.partner-payouts') }}"
                    class="inline-flex items-center px-3 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-md transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Payouts
                </a>
            </div>
        </div>

        <!-- Payout Overview -->
        <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Payout Information</h3>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">Details about this partner payout request</p>
            </div>
            <div class="border-t border-gray-200 px-4 py-5 sm:p-0">
                <dl class="sm:divide-y sm:divide-gray-200">
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Partner</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                            <div class="font-medium">{{ $payout->partner->name ?? 'N/A' }}</div>
                            <div class="text-gray-500">{{ $payout->partner->email ?? 'N/A' }}</div>
                            @if ($payout->partner->phone ?? false)
                                <div class="text-gray-400 text-xs">{{ $payout->partner->phone }}</div>
                            @endif
                        </dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Amount</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-medium">
                            Rs {{ number_format($payout->amount, 2) }}
                        </dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Platform Fee</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                            Rs {{ number_format($payout->platform_fee ?? 0, 2) }}
                        </dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Net Amount</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2 font-medium">
                            Rs {{ number_format($payout->net_amount ?? $payout->amount, 2) }}
                        </dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                            <span
                                class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                @if ($payout->status === 'pending') bg-yellow-100 text-yellow-800
                                @elseif($payout->status === 'processing') bg-blue-100 text-blue-800
                                @elseif($payout->status === 'completed') bg-green-100 text-green-800
                                @else bg-red-100 text-red-800 @endif">
                                {{ ucfirst($payout->status) }}
                            </span>
                            @if ($payout->status === 'processing' && $payout->processed_at)
                                <div class="mt-1 text-xs text-blue-700">
                                    Processing since {{ $payout->processed_at->format('M d, Y H:i') }}
                                    ({{ $payout->processed_at->diffForHumans() }})
                                </div>
                            @endif
                            @if ($payout->status === 'failed' && $payout->failure_reason)
                                <div class="mt-1 text-xs text-red-700">{{ $payout->failure_reason }}</div>
                            @endif
                        </dd>
                    </div>
                    @if ($payout->payment_method)
                        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Payment Method</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                {{ ucfirst(str_replace('_', ' ', $payout->payment_method)) }}
                            </dd>
                        </div>
                    @endif
                    @if ($payout->transaction_reference)
                        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Transaction Reference</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                {{ $payout->transaction_reference }}
                            </dd>
                        </div>
                    @endif
                    @if ($payout->gateway_fee && $payout->gateway_fee > 0)
                        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Gateway Fee</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                Rs {{ number_format($payout->gateway_fee, 2) }}
                            </dd>
                        </div>
                    @endif
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-gray-500">Created</dt>
                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                            {{ $payout->created_at->format('M d, Y H:i:s') }}
                        </dd>
                    </div>
                    @if ($payout->processed_at)
                        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Processed</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                {{ $payout->processed_at->format('M d, Y H:i:s') }}
                                @if ($payout->processedBy)
                                    <div class="text-xs text-gray-500">by {{ $payout->processedBy->name }}</div>
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if ($payout->notes)
                        <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium text-gray-500">Notes</dt>
                            <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                {{ $payout->notes }}
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        @if (in_array($payout->status, ['pending', 'processing'], true))
            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Resolve this payout</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">
                        A payout must never stay in processing. Mark it completed only after the money reached
                        the partner; mark it failed when the transfer never left (this returns any deducted
                        amount to the partner).
                    </p>
                </div>
                <div class="px-4 py-5 sm:p-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <form method="POST" action="{{ route('admin.ledger.partner-payouts.complete') }}"
                        onsubmit="return confirm('Mark payout #{{ $payout->payout_id }} as completed? Only do this after the money reached the partner.');">
                        @csrf
                        <input type="hidden" name="payout_id" value="{{ $payout->payout_id }}">
                        <label for="gateway_payout_id" class="block text-sm font-medium text-gray-700">Transaction ID
                            / Reference</label>
                        <input type="text" name="gateway_payout_id" id="gateway_payout_id"
                            value="{{ $payout->transaction_reference }}"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <button type="submit"
                            class="mt-3 inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-sm font-medium text-white hover:bg-green-700">
                            Mark completed
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.ledger.partner-payouts.fail') }}"
                        onsubmit="return confirm('Mark payout #{{ $payout->payout_id }} as failed? Any deducted amount is returned to the partner.');">
                        @csrf
                        <input type="hidden" name="payout_id" value="{{ $payout->payout_id }}">
                        <label for="failure_reason" class="block text-sm font-medium text-gray-700">Why did it
                            fail?</label>
                        <input type="text" name="failure_reason" id="failure_reason" required minlength="5"
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            placeholder="e.g. bank rejected the transfer">
                        <button type="submit"
                            class="mt-3 inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-sm font-medium text-white hover:bg-red-700">
                            Mark failed
                        </button>
                    </form>
                </div>
            </div>

        @endif

        <!-- Related Ledger Entries -->
        @if ($relatedEntries->count() > 0)
            <div class="bg-white shadow overflow-hidden sm:rounded-md">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Related Ledger Entries</h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Ledger entries around the time of this payout</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Type</th>
                                <th scope="col"
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Amount</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Description</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($relatedEntries as $entry)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $entry->created_at->format('M d, Y H:i') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span
                                            class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                            {{ $entry->type }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium
                                        {{ $entry->amount >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $entry->amount >= 0 ? '+' : '' }}Rs {{ number_format($entry->amount, 2) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ $entry->description ?? 'N/A' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
