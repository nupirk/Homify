@extends('layouts.app')

@section('title', 'Error Report - ServiceCo Admin')

@section('content')
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div class="min-w-0">
                <a href="{{ route('admin.error-reports.index') }}"
                    class="text-sm text-blue-600 hover:text-blue-800 inline-flex items-center space-x-1">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to error reports</span>
                </a>
                <h1 class="mt-2 text-2xl font-bold text-gray-900 break-words">{{ $report->message }}</h1>
                <p class="text-gray-600">
                    First seen {{ $report->first_seen_at?->format('M j, Y H:i') ?? '—' }}
                    · Last seen {{ $report->last_seen_at?->format('M j, Y H:i') ?? '—' }}
                    · {{ number_format($report->occurrences) }}
                    {{ \Illuminate\Support\Str::plural('occurrence', $report->occurrences) }}
                </p>
            </div>
        </div>

        <!-- Summary -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Details</h2>
            <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-gray-500">Level</dt>
                    <dd class="mt-1 font-medium text-gray-900">{{ ucfirst($report->level ?? 'error') }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Exception</dt>
                    <dd class="mt-1 font-medium text-gray-900 break-words">{{ $report->exception_class ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Location</dt>
                    <dd class="mt-1 font-medium text-gray-900 break-words">
                        @if ($report->file)
                            {{ $report->file }}:{{ $report->line }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Screen</dt>
                    <dd class="mt-1 font-medium text-gray-900 break-words">{{ $report->screen ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Platform / version</dt>
                    <dd class="mt-1 font-medium text-gray-900">
                        {{ $report->platform ?: '—' }}{{ $report->app_version ? ' · v' . $report->app_version : '' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Reported by</dt>
                    <dd class="mt-1 font-medium text-gray-900">
                        @if ($report->user)
                            {{ trim($report->user->first_name . ' ' . $report->user->last_name) }}
                            <span class="text-gray-500">({{ $report->user->email }})</span>
                        @else
                            Anonymous
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">IP address</dt>
                    <dd class="mt-1 font-medium text-gray-900">{{ $report->ip_address ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">User agent</dt>
                    <dd class="mt-1 font-medium text-gray-900 break-words">{{ $report->user_agent ?: '—' }}</dd>
                </div>
            </dl>
        </div>

        <!-- Stack trace -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Stack trace</h2>
            @if ($report->stack)
                <pre
                    class="mt-4 p-4 bg-slate-900 text-slate-100 text-xs rounded-lg overflow-x-auto whitespace-pre-wrap break-words">{{ $report->stack }}</pre>
            @else
                <p class="mt-4 text-sm text-gray-500">No stack trace was captured.</p>
            @endif
        </div>

        <!-- Context -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Context</h2>
            @if (!empty($report->context))
                <pre
                    class="mt-4 p-4 bg-gray-50 border border-gray-200 text-xs rounded-lg overflow-x-auto whitespace-pre-wrap break-words">{{ json_encode($report->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @else
                <p class="mt-4 text-sm text-gray-500">No extra context was attached.</p>
            @endif
        </div>
    </div>
@endsection
