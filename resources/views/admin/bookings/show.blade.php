@extends('layouts.app')

@section('title', 'Booking Details')

@section('content')
    <div class="max-w-6xl mx-auto">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Booking #{{ str_pad($booking->id, 6, '0', STR_PAD_LEFT) }}
                    </h1>
                    <p class="text-gray-600 mt-2">Booking details and management</p>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="{{ route('admin.bookings.edit', $booking) }}"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-edit"></i>
                        <span>Edit</span>
                    </a>
                    <a href="{{ route('admin.bookings.index') }}"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center space-x-2">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Booking Details -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Basic Information -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-6">Booking Information</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Booking ID</label>
                                <p class="text-lg font-medium text-gray-900">
                                    #{{ str_pad($booking->id, 6, '0', STR_PAD_LEFT) }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Status</label>
                                <span
                                    class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                {{ $booking->status === 'completed'
                                    ? 'bg-green-100 text-green-800'
                                    : ($booking->status === 'cancelled'
                                        ? 'bg-red-100 text-red-800'
                                        : ($booking->status === 'confirmed'
                                            ? 'bg-blue-100 text-blue-800'
                                            : ($booking->status === 'in_progress'
                                                ? 'bg-purple-100 text-purple-800'
                                                : 'bg-yellow-100 text-yellow-800'))) }}">
                                    {{ ucfirst($booking->status ?? 'pending') }}
                                </span>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Total Amount</label>
                                <p class="text-2xl font-bold text-green-600">
                                    ${{ number_format($booking->total_amount ?? ($booking->service ? $booking->service->price : 0), 2) }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Created Date</label>
                                <p class="text-gray-700">{{ $booking->created_at->format('M d, Y h:i A') }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-500">Last Updated</label>
                                <p class="text-gray-700">{{ $booking->updated_at->format('M d, Y h:i A') }}</p>
                            </div>

                            @if ($booking->scheduled_date)
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Scheduled Date & Time</label>
                                    <p class="text-gray-900">{{ $booking->scheduled_date->format('M d, Y') }}</p>
                                    <p class="text-gray-700">{{ $booking->scheduled_time ?? 'Time TBD' }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Customer Information -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Customer Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Name</label>
                            <p class="text-gray-900">{{ $booking->customer_name ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Email</label>
                            <p class="text-gray-900">{{ $booking->customer_email ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Phone</label>
                            <p class="text-gray-900">{{ $booking->customer_phone ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Address</label>
                            <p class="text-gray-900">{{ $booking->customer_address ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Service Information -->
                @if ($booking->service)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Service Information</h3>
                        <div class="flex items-start space-x-4">
                            @if ($booking->service->image)
                                <img src="{{ asset('storage/' . $booking->service->image) }}"
                                    alt="{{ $booking->service->name }}" class="w-20 h-20 rounded-lg object-cover">
                            @endif
                            <div class="flex-1">
                                <h4 class="text-lg font-medium text-gray-900">{{ $booking->service->name }}</h4>
                                @if ($booking->service->description)
                                    <p class="text-gray-600 mt-1">{{ $booking->service->description }}</p>
                                @endif
                                <div class="mt-3 flex items-center space-x-4 text-sm text-gray-500">
                                    <span><i class="fas fa-clock mr-1"></i>{{ $booking->service->duration }} minutes</span>
                                    <span><i
                                            class="fas fa-dollar-sign mr-1"></i>${{ number_format($booking->service->price, 2) }}</span>
                                    @if ($booking->service->category)
                                        <span><i class="fas fa-tag mr-1"></i>{{ $booking->service->category->name }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Partner Information -->
                @if ($booking->partner)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Assigned Partner</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Name</label>
                                <p class="text-gray-900">{{ $booking->partner->name ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Email</label>
                                <p class="text-gray-900">{{ $booking->partner->email ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Phone</label>
                                <p class="text-gray-900">{{ $booking->partner->phone ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Rating</label>
                                <div class="flex items-center">
                                    <span
                                        class="text-gray-900 mr-2">{{ $booking->partner->average_rating ?? 'N/A' }}</span>
                                    @if ($booking->partner->average_rating)
                                        <div class="flex">
                                            @for ($i = 1; $i <= 5; $i++)
                                                <i
                                                    class="fas fa-star text-sm {{ $i <= ($booking->partner->average_rating ?? 0) ? 'text-yellow-400' : 'text-gray-300' }}"></i>
                                            @endfor
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Additional Notes -->
                @if ($booking->notes)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Notes</h3>
                        <p class="text-gray-700 leading-relaxed">{{ $booking->notes }}</p>
                    </div>
                @endif
            </div>

            <!-- Actions & Quick Info -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        @if ($booking->status === 'pending')
                            <form action="{{ route('admin.bookings.confirm', $booking) }}" method="POST"
                                class="inline-block w-full">
                                @csrf
                                <button type="submit"
                                    class="w-full text-left px-4 py-2 rounded-lg hover:bg-blue-50 flex items-center space-x-3 text-blue-600">
                                    <i class="fas fa-check"></i>
                                    <span>Confirm Booking</span>
                                </button>
                            </form>
                        @endif

                        @if (!$booking->partner && $booking->status !== 'cancelled')
                            <button onclick="openAssignPartnerModal()"
                                class="w-full text-left px-4 py-2 rounded-lg hover:bg-green-50 flex items-center space-x-3 text-green-600">
                                <i class="fas fa-user-plus"></i>
                                <span>Assign Partner</span>
                            </button>
                        @endif

                        @if ($booking->status === 'confirmed' || $booking->status === 'in_progress')
                            <form action="{{ route('admin.bookings.complete', $booking) }}" method="POST"
                                class="inline-block w-full">
                                @csrf
                                <button type="submit"
                                    class="w-full text-left px-4 py-2 rounded-lg hover:bg-green-50 flex items-center space-x-3 text-green-600">
                                    <i class="fas fa-check-double"></i>
                                    <span>Mark as Completed</span>
                                </button>
                            </form>
                        @endif

                        @if ($booking->status !== 'cancelled' && $booking->status !== 'completed')
                            <form action="{{ route('admin.bookings.cancel', $booking) }}" method="POST"
                                onsubmit="return confirm('Are you sure you want to cancel this booking?')"
                                class="inline-block w-full">
                                @csrf
                                <button type="submit"
                                    class="w-full text-left px-4 py-2 rounded-lg hover:bg-red-50 flex items-center space-x-3 text-red-600">
                                    <i class="fas fa-times"></i>
                                    <span>Cancel Booking</span>
                                </button>
                            </form>
                        @endif

                        <button onclick="window.print()"
                            class="w-full text-left px-4 py-2 rounded-lg hover:bg-gray-50 flex items-center space-x-3 text-gray-600">
                            <i class="fas fa-print"></i>
                            <span>Print Details</span>
                        </button>
                    </div>
                </div>

                <!-- Booking Timeline -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Booking Timeline</h3>
                    <div class="space-y-4">
                        <div class="flex items-start space-x-3">
                            <div class="w-2 h-2 bg-blue-500 rounded-full mt-2"></div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Booking Created</p>
                                <p class="text-xs text-gray-500">{{ $booking->created_at->format('M d, Y h:i A') }}</p>
                            </div>
                        </div>

                        @if ($booking->confirmed_at)
                            <div class="flex items-start space-x-3">
                                <div class="w-2 h-2 bg-green-500 rounded-full mt-2"></div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Booking Confirmed</p>
                                    <p class="text-xs text-gray-500">{{ $booking->confirmed_at->format('M d, Y h:i A') }}
                                    </p>
                                </div>
                            </div>
                        @endif

                        @if ($booking->partner_assigned_at)
                            <div class="flex items-start space-x-3">
                                <div class="w-2 h-2 bg-purple-500 rounded-full mt-2"></div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Partner Assigned</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $booking->partner_assigned_at->format('M d, Y h:i A') }}</p>
                                </div>
                            </div>
                        @endif

                        @if ($booking->completed_at)
                            <div class="flex items-start space-x-3">
                                <div class="w-2 h-2 bg-green-600 rounded-full mt-2"></div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Service Completed</p>
                                    <p class="text-xs text-gray-500">{{ $booking->completed_at->format('M d, Y h:i A') }}
                                    </p>
                                </div>
                            </div>
                        @endif

                        @if ($booking->cancelled_at)
                            <div class="flex items-start space-x-3">
                                <div class="w-2 h-2 bg-red-500 rounded-full mt-2"></div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Booking Cancelled</p>
                                    <p class="text-xs text-gray-500">{{ $booking->cancelled_at->format('M d, Y h:i A') }}
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Payment Information -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Information</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Service Price</span>
                            <span
                                class="text-gray-900">${{ number_format($booking->service ? $booking->service->price : 0, 2) }}</span>
                        </div>
                        @if ($booking->discount_amount)
                            <div class="flex justify-between">
                                <span class="text-gray-500">Discount</span>
                                <span class="text-red-600">-${{ number_format($booking->discount_amount, 2) }}</span>
                            </div>
                        @endif
                        @if ($booking->tax_amount)
                            <div class="flex justify-between">
                                <span class="text-gray-500">Tax</span>
                                <span class="text-gray-900">${{ number_format($booking->tax_amount, 2) }}</span>
                            </div>
                        @endif
                        <div class="border-t border-gray-200 pt-3">
                            <div class="flex justify-between">
                                <span class="text-lg font-medium text-gray-900">Total</span>
                                <span
                                    class="text-lg font-bold text-green-600">${{ number_format($booking->total_amount ?? ($booking->service ? $booking->service->price : 0), 2) }}</span>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            {{ $booking->payment_status === 'paid'
                                ? 'bg-green-100 text-green-800'
                                : ($booking->payment_status === 'refunded'
                                    ? 'bg-red-100 text-red-800'
                                    : 'bg-yellow-100 text-yellow-800') }}">
                                {{ ucfirst($booking->payment_status ?? 'pending') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Partner Modal -->
    <div id="assignPartnerModal"
        class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full mx-4">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Assign Partner</h3>
                <form action="{{ route('admin.bookings.assign-partner', $booking) }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Partner</label>
                        <select name="partner_id" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Choose a partner...</option>
                            @foreach ($availablePartners ?? [] as $partner)
                                <option value="{{ $partner->id }}">{{ $partner->name }} -
                                    {{ $partner->rating ?? 'N/A' }}★</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAssignPartnerModal()"
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Assign Partner
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openAssignPartnerModal() {
            document.getElementById('assignPartnerModal').classList.remove('hidden');
        }

        function closeAssignPartnerModal() {
            document.getElementById('assignPartnerModal').classList.add('hidden');
        }
    </script>
@endsection
