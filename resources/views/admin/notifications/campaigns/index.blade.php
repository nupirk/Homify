@extends('layouts.app')

@section('title', 'Campaigns - Homify Admin')

@section('content')
    @php
        $statusStyles = [
            'sent' => 'bg-green-100 text-green-800',
            'completed' => 'bg-green-100 text-green-800',
            'scheduled' => 'bg-blue-100 text-blue-800',
            'processing' => 'bg-indigo-100 text-indigo-800',
            'pending' => 'bg-yellow-100 text-yellow-800',
            'failed' => 'bg-red-100 text-red-800',
            'cancelled' => 'bg-gray-100 text-gray-700',
        ];

        $targetLabels = [
            'all_users' => 'All Users',
            'all_partners' => 'All Partners',
            'active_partners' => 'Active Partners',
            'inactive_partners' => 'Inactive Partners',
            'verified_partners' => 'Verified Partners',
            'negative_balance_partners' => 'Negative Balance Partners',
            'high_earning_partners' => 'High Earning Partners',
            'new_partners' => 'New Partners',
            'active_customers' => 'Active Customers',
            'inactive_customers' => 'Inactive Customers',
            'custom' => 'Custom Selection',
        ];
    @endphp

    <div class="max-w-7xl mx-auto space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Campaigns</h1>
                <p class="text-gray-600">Notification campaigns sent to customers and partners</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('admin.notifications.index') }}"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-arrow-left"></i>
                    <span>Notifications</span>
                </a>
                <a href="{{ route('admin.notifications.campaigns.create') }}"
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-plus"></i>
                    <span>Create Campaign</span>
                </a>
            </div>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-indigo-100 rounded-lg">
                        <i class="fas fa-bullhorn text-indigo-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Campaigns</p>
                        <p class="text-2xl font-bold text-gray-900">{{ number_format($campaigns->total()) }}</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <i class="fas fa-check text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Sent</p>
                        <p class="text-2xl font-bold text-gray-900">
                            {{ number_format($campaigns->getCollection()->whereIn('status', ['sent', 'completed'])->count()) }}
                            <span class="text-sm font-normal text-gray-500">on this page</span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <i class="fas fa-clock text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Scheduled / Pending</p>
                        <p class="text-2xl font-bold text-gray-900">
                            {{ number_format($campaigns->getCollection()->whereIn('status', ['scheduled', 'pending', 'processing'])->count()) }}
                            <span class="text-sm font-normal text-gray-500">on this page</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Campaigns Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Campaign</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Template</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Audience</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Recipients</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Scheduled</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($campaigns as $campaign)
                            @php
                                $status = strtolower($campaign->status ?? 'pending');
                                $statusClass = $statusStyles[$status] ?? 'bg-gray-100 text-gray-700';
                                $sent = (int) ($campaign->successful_sends ?? 0);
                                $total = (int) ($campaign->total_recipients ?? 0);
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $campaign->name }}</div>
                                    <div class="text-xs text-gray-500">
                                        Created {{ optional($campaign->created_at)->diffForHumans() ?? '—' }}
                                        @if ($campaign->creator)
                                            by {{ $campaign->creator->full_name }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ $campaign->template->name ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ $targetLabels[$campaign->target_type] ?? ucfirst(str_replace('_', ' ', (string) $campaign->target_type)) }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    {{ number_format($total) }}
                                    @if ($total > 0)
                                        <div class="text-xs text-gray-500">{{ number_format($sent) }} sent</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                                        {{ ucfirst($status) }}
                                    </span>
                                    @if ((int) ($campaign->failed_sends ?? 0) > 0)
                                        <div class="text-xs text-red-600 mt-1">
                                            {{ number_format((int) $campaign->failed_sends) }} failed
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    @if ($campaign->scheduled_at)
                                        {{ $campaign->scheduled_at->format('M j, Y g:i A') }}
                                    @elseif ($campaign->sent_at)
                                        Sent {{ $campaign->sent_at->format('M j, Y g:i A') }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <a href="{{ route('admin.notifications.campaigns.show', $campaign->campaign_id) }}"
                                        class="text-indigo-600 hover:text-indigo-900 font-medium">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-bullhorn text-3xl text-gray-300 mb-3 block"></i>
                                    <p class="text-sm">No campaigns yet.</p>
                                    <a href="{{ route('admin.notifications.campaigns.create') }}"
                                        class="inline-block mt-3 text-indigo-600 hover:text-indigo-800 font-medium text-sm">
                                        Create your first campaign
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($campaigns->hasPages())
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $campaigns->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
