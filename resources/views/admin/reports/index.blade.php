@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Reports</h1>
            <p class="text-gray-600 mt-1">
                Money and messaging, from the same ledger and notification log the rest of the panel uses.
                Every report takes a date range and exports as CSV.
            </p>
        </div>

        <form method="GET" action="{{ route('admin.reports.revenue') }}"
            class="bg-white rounded-lg shadow p-6 mb-6 flex flex-wrap items-end gap-4">
            <div>
                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">From</label>
                <input type="date" id="date_from" name="date_from" value="{{ $from->toDateString() }}"
                    class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">To</label>
                <input type="date" id="date_to" name="date_to" value="{{ $to->toDateString() }}"
                    class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="submit"
                class="inline-flex items-center px-4 py-2 bg-blue-600 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                <i class="fas fa-filter mr-2"></i>
                Apply Range
            </button>
            <p class="text-xs text-gray-500">Defaults to this month. Both reports below use the range you pick here.</p>
        </form>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <a href="{{ route('admin.reports.revenue', ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]) }}"
                class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-semibold text-gray-900">Revenue</h2>
                    <i class="fas fa-coins text-2xl text-amber-500"></i>
                </div>
                <p class="text-sm text-gray-600">
                    Platform fees and tax owed, straight from <code class="text-xs">ledger_entries</code> — the
                    same figures the dashboard card shows.
                </p>
            </a>

            <a href="{{ route('admin.reports.delivery', ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]) }}"
                class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-semibold text-gray-900">Notification Delivery</h2>
                    <i class="fas fa-paper-plane text-2xl text-indigo-500"></i>
                </div>
                <p class="text-sm text-gray-600">
                    Sent, delivered, read and failed counts per platform from
                    <code class="text-xs">notification_logs</code>.
                </p>
            </a>

            <a href="{{ route('admin.orders.index') }}"
                class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-semibold text-gray-900">Orders</h2>
                    <i class="fas fa-shopping-cart text-2xl text-blue-500"></i>
                </div>
                <p class="text-sm text-gray-600">
                    Filter by status, payment and dates on the Orders page, then use Export CSV there.
                    The export now streams and ends with a totals row.
                </p>
            </a>

            <a href="{{ route('admin.reviews.index') }}"
                class="bg-white rounded-lg shadow p-6 hover:shadow-md transition">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-semibold text-gray-900">Reviews</h2>
                    <i class="fas fa-star text-2xl text-yellow-500"></i>
                </div>
                <p class="text-sm text-gray-600">
                    Browse and moderate reviews; the JSON export lives at
                    <code class="text-xs">/api/v1/admin/reviews/export</code>.
                </p>
            </a>
        </div>
    </div>
@endsection
