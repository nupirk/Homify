@extends('layouts.app')

@section('title', 'Clients - ServiceCo Admin')

@section('content')
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Clients</h1>
            <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                <i class="fas fa-plus"></i>
                <span>Add Client</span>
            </button>
        </div>

        <!-- Search Bar -->
        <div class="bg-white rounded-lg shadow p-6">
            <form method="GET" action="{{ route('admin.clients.index') }}">
                <div class="relative">
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Search clients by name or contact..."
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                </div>
            </form>
        </div>

        <!-- Clients Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Service Requests</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Feedback</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($clients as $client)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gray-300 rounded-full overflow-hidden">
                                            <img src="https://ui-avatars.com/api/?name={{ urlencode($client->full_name) }}&background=random"
                                                alt="{{ $client->full_name }}" class="w-full h-full object-cover">
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ $client->full_name }}</div>
                                            <div class="text-sm text-gray-500">{{ $client->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $client->phone ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $client->orders->count() ?? 0 }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    @if ($client->ratings->count() > 0)
                                        <div class="flex items-center">
                                            <span class="text-yellow-400">★</span>
                                            <span
                                                class="ml-1">{{ number_format($client->ratings->avg('rating_value') ?? 0, 1) }}
                                                stars</span>
                                        </div>
                                    @else
                                        <span class="text-gray-500">No ratings</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('admin.clients.show', $client->user_id) }}"
                                        class="text-blue-600 hover:text-blue-900 mr-3">View Profile</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                    No clients found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($clients->hasPages())
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    {{ $clients->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
