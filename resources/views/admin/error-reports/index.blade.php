@extends('layouts.app')

@section('title', 'Error Reports - ServiceCo Admin')

@section('content')
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Error Reports</h1>
                <p class="text-gray-600">Crashes and errors reported by the mobile apps</p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('admin.error-reports.index') }}"
                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-sync-alt"></i>
                    <span>Refresh</span>
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-6">
            <form method="GET" class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-64">
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Search by message, exception or screen..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <select name="level"
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Levels</option>
                        @foreach ($levels as $level)
                            <option value="{{ $level }}" {{ request('level') === $level ? 'selected' : '' }}>
                                {{ ucfirst($level) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    Filter
                </button>

                @if (request()->hasAny(['search', 'level']))
                    <a href="{{ route('admin.error-reports.index') }}"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2 rounded-lg">
                        Clear
                    </a>
                @endif
            </form>
        </div>

        <!-- Reports -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            @if ($reports->isEmpty())
                <div class="p-12 text-center">
                    <div class="mx-auto w-16 h-16 rounded-full bg-green-50 flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-500 text-2xl"></i>
                    </div>
                    <h3 class="mt-4 text-lg font-medium text-gray-900">No error reports</h3>
                    <p class="mt-1 text-gray-600">
                        @if (request()->hasAny(['search', 'level']))
                            Nothing matches these filters.
                        @else
                            The apps have not reported any crashes or errors.
                        @endif
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Level</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Problem</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Screen</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Platform</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Hits</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Last seen</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($reports as $report)
                                @php
                                    $level = strtolower($report->level ?? 'error');
                                    $levelClasses = [
                                        'fatal' => 'bg-red-100 text-red-800',
                                        'error' => 'bg-rose-100 text-rose-800',
                                        'warning' => 'bg-amber-100 text-amber-800',
                                        'info' => 'bg-blue-100 text-blue-800',
                                        'debug' => 'bg-gray-100 text-gray-700',
                                    ][$level] ?? 'bg-gray-100 text-gray-700';
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="px-2 py-1 text-xs font-medium rounded-full {{ $levelClasses }}">{{ ucfirst($level) }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('admin.error-reports.show', $report->id) }}"
                                            class="text-sm font-medium text-gray-900 hover:text-blue-600">
                                            {{ \Illuminate\Support\Str::limit($report->message, 90) }}
                                        </a>
                                        @if ($report->exception_class)
                                            <p class="text-xs text-gray-500 mt-1">
                                                {{ $report->exception_class }}@if ($report->file)
                                                    · {{ basename($report->file) }}:{{ $report->line }}
                                                @endif
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {{ $report->screen ?: '—' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {{ $report->platform ?: '—' }}
                                        @if ($report->app_version)
                                            <span class="text-xs text-gray-400">v{{ $report->app_version }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                        {{ number_format($report->occurrences) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        {{ $report->last_seen_at?->diffForHumans() ?? '—' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        <a href="{{ route('admin.error-reports.show', $report->id) }}"
                                            class="text-blue-600 hover:text-blue-800" title="View details">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $reports->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
