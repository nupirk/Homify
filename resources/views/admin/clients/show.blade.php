{{-- filepath: c:\Users\kchnp\Desktop\Homify\be\resources\views\admin\clients\show.blade.php --}}
@extends('layouts.app')

@section('title', 'Client Details')

@section('content')
    <div class="p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $client->first_name }} {{ $client->last_name }}</h1>
                <p class="text-gray-600 mt-1">Client details and activity overview</p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('admin.clients.edit', $client) }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                    <i class="fas fa-edit mr-2"></i>
                    Edit Client
                </a>
                <a href="{{ route('admin.clients.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Clients
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Client Information -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Basic Information -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Basic Information</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Full Name</label>
                                <p class="mt-1 text-sm text-gray-900">{{ $client->first_name }} {{ $client->last_name }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Email</label>
                                <p class="mt-1 text-sm text-gray-900">{{ $client->email }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Phone</label>
                                <p class="mt-1 text-sm text-gray-900">{{ $client->phone ?? 'Not provided' }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Date of Birth</label>
                                <p class="mt-1 text-sm text-gray-900">
                                    {{ $client->date_of_birth ? $client->date_of_birth->format('M d, Y') : 'Not provided' }}
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Gender</label>
                                <p class="mt-1 text-sm text-gray-900">{{ ucfirst($client->gender ?? 'Not specified') }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Joined</label>
                                <p class="mt-1 text-sm text-gray-900">{{ $client->created_at->format('M d, Y') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Recent Orders</h3>
                    </div>
                    <div class="overflow-hidden">
                        @if ($recentOrders->count() > 0)
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Order ID</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Service</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Amount</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Date</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($recentOrders as $order)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                                <a href="{{ route('admin.orders.show', $order) }}">
                                                    #{{ substr($order->id, 0, 8) }}
                                                </a>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ $order->orderItems?->first()?->service?->name ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    {{ $order->status === 'completed'
                                                        ? 'bg-green-100 text-green-800'
                                                        : ($order->status === 'pending'
                                                            ? 'bg-yellow-100 text-yellow-800'
                                                            : ($order->status === 'cancelled'
                                                                ? 'bg-red-100 text-red-800'
                                                                : 'bg-blue-100 text-blue-800')) }}">
                                                    {{ ucfirst($order->status) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                Rs {{ number_format($order->total_amount, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $order->created_at->format('M d, Y') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <div class="p-6 text-center">
                                <i class="fas fa-shopping-cart text-gray-400 text-3xl mb-2"></i>
                                <p class="text-gray-500">No orders found</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Recent Reviews -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Recent Reviews</h3>
                    </div>
                    <div class="p-6">
                        @if ($recentRatings->count() > 0)
                            <div class="space-y-4">
                                @foreach ($recentRatings as $review)
                                    <div class="border border-gray-200 rounded-lg p-4">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="flex items-center">
                                                <div class="flex text-yellow-400">
                                                    @for ($i = 1; $i <= 5; $i++)
                                                        <i
                                                            class="fas fa-star {{ $i <= $review->rating_value ? '' : 'text-gray-300' }}"></i>
                                                    @endfor
                                                </div>
                                                <span
                                                    class="ml-2 text-sm text-gray-600">{{ $review->rating_value }}/5</span>
                                            </div>
                                            <span
                                                class="text-sm text-gray-500">{{ $review->created_at->format('M d, Y') }}</span>
                                        </div>
                                        @if ($review->review_text)
                                            <p class="text-sm text-gray-700">{{ $review->review_text }}</p>
                                        @endif
                                        <p class="text-xs text-gray-500 mt-2">
                                            Service: {{ $review->service?->name ?? 'N/A' }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center">
                                <i class="fas fa-star text-gray-400 text-3xl mb-2"></i>
                                <p class="text-gray-500">No reviews found</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Status Card -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Status</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Account Status</span>
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $client->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $client->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Email Verified</span>
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $client->email_verified_at ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                {{ $client->email_verified_at ? 'Verified' : 'Unverified' }}
                            </span>
                        </div>

                        <div class="pt-4 border-t border-gray-200">
                            <div class="flex space-x-2">
                                @if (!$client->is_active)
                                    <form action="{{ route('admin.clients.toggle-status', $client) }}" method="POST"
                                        class="flex-1">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                            class="w-full inline-flex items-center justify-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 transition">
                                            <i class="fas fa-check mr-1"></i>
                                            Activate
                                        </button>
                                    </form>
                                @else
                                    <form action="{{ route('admin.clients.toggle-status', $client) }}" method="POST"
                                        class="flex-1">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                            onclick="return confirm('Are you sure you want to deactivate this client?')"
                                            class="w-full inline-flex items-center justify-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 transition">
                                            <i class="fas fa-ban mr-1"></i>
                                            Deactivate
                                        </button>
                                    </form>
                                @endif
                            </div>

                            @if (!$client->email_verified_at)
                                <form action="{{ route('admin.clients.verify-email', $client) }}" method="POST"
                                    class="mt-2">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                        class="w-full inline-flex items-center justify-center px-3 py-2 border border-gray-300 text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
                                        <i class="fas fa-envelope-check mr-1"></i>
                                        Verify Email
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Statistics</h3>
                    </div>
                    <div class="p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Total Orders</span>
                            <span class="text-lg font-semibold text-gray-900">{{ $stats['total_orders'] }}</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Completed Orders</span>
                            <span class="text-lg font-semibold text-green-600">{{ $stats['completed_orders'] }}</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Total Spent</span>
                            <span class="text-lg font-semibold text-gray-900">Rs
                                {{ number_format($stats['total_spent'], 2) }}</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Reviews Given</span>
                            <span class="text-lg font-semibold text-gray-900">{{ $stats['reviews_given'] }}</span>
                        </div>

                        @if ($stats['average_rating_given'] > 0)
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Average Rating</span>
                                <div class="flex items-center">
                                    <span
                                        class="text-lg font-semibold text-gray-900">{{ number_format($stats['average_rating_given'], 1) }}</span>
                                    <div class="flex text-yellow-400 ml-1">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <i
                                                class="fas fa-star text-xs {{ $i <= round($stats['average_rating_given']) ? '' : 'text-gray-300' }}"></i>
                                        @endfor
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
