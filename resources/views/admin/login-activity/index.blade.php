@extends('layouts.app')

@section('title', 'Login Activity - Homify Admin')

@section('content')
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Login Activity</h1>
                <p class="text-gray-600">Admin sign-ins, sign-outs and rejected attempts, with source IP and client</p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('admin.login-activity.index') }}"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-sync-alt"></i>
                    <span>Refresh</span>
                </a>
            </div>
        </div>

        <!-- 24 hour summary -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Successful logins (24h)</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($summary['logins_24h']) }}</p>
                </div>
                <div class="w-10 h-10 bg-emerald-50 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-right-to-bracket text-emerald-600"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Failed attempts (24h)</p>
                    <p class="mt-2 text-2xl font-bold {{ $summary['failed_24h'] > 0 ? 'text-rose-600' : 'text-slate-900' }}">
                        {{ number_format($summary['failed_24h']) }}
                    </p>
                </div>
                <div class="w-10 h-10 bg-rose-50 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-triangle-exclamation text-rose-600"></i>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Distinct failing IPs (24h)</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($summary['distinct_ips_24h']) }}</p>
                </div>
                <div class="w-10 h-10 bg-indigo-50 rounded-lg flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-network-wired text-indigo-600"></i>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-6">
            <form method="GET" class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-56">
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Search by email or IP..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <select name="action"
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Events</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $action)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <input type="date" name="date_to" value="{{ request('date_to') }}"
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    Filter
                </button>

                @if (request()->hasAny(['search', 'action', 'date_from', 'date_to']))
                    <a href="{{ route('admin.login-activity.index') }}"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2 rounded-lg">
                        Clear
                    </a>
                @endif
            </form>
        </div>

        <!-- Entries -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            @if ($entries->isEmpty())
                <div class="p-12 text-center">
                    <div class="mx-auto w-16 h-16 rounded-full bg-green-50 flex items-center justify-center">
                        <i class="fas fa-user-shield text-green-500 text-2xl"></i>
                    </div>
                    <h3 class="mt-4 text-lg font-medium text-gray-900">No login activity</h3>
                    <p class="mt-1 text-gray-600">
                        @if (request()->hasAny(['search', 'action', 'date_from', 'date_to']))
                            Nothing matches these filters.
                        @else
                            Admin sign-ins will appear here as soon as someone logs in.
                        @endif
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">When</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Account</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email tried</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP address</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($entries as $entry)
                                @php
                                    $failed = $entry->action === 'admin_login_failed';
                                    $reason = $entry->new_values['reason'] ?? null;
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <span title="{{ $entry->created_at?->format('Y-m-d H:i:s') }}">
                                            {{ $entry->created_at?->format('d M Y, H:i:s') }}
                                        </span>
                                        <p class="text-xs text-gray-400">{{ $entry->created_at?->diffForHumans() }}</p>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full {{ $failed ? 'bg-rose-100 text-rose-800' : ($entry->action === 'admin_logout' ? 'bg-gray-100 text-gray-700' : 'bg-emerald-100 text-emerald-800') }}">
                                            {{ $failed ? 'Failed' : ($entry->action === 'admin_logout' ? 'Logout' : 'Login') }}
                                        </span>
                                        @if ($reason)
                                            <p class="text-xs text-gray-500 mt-1">{{ str_replace('_', ' ', $reason) }}</p>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        @if ($entry->user)
                                            {{ $entry->user->first_name }} {{ $entry->user->last_name }}
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                        {{ $entry->entity_id ?: '—' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-700">
                                        {{ $entry->ip_address ?: '—' }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600 max-w-xs">
                                        <span class="line-clamp-2" title="{{ $entry->user_agent }}">
                                            {{ \Illuminate\Support\Str::limit($entry->user_agent ?: '—', 70) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $entries->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
