@extends('layouts.app')

@section('title', 'Review Details - ServiceCo Admin')

@section('content')
    <div class="max-w-4xl mx-auto space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Review Details</h1>
                <p class="text-gray-600">Review from {{ $review->customer->first_name ?? 'Unknown' }}
                    {{ $review->customer->last_name ?? 'User' }}</p>
            </div>
            <a href="{{ route('admin.reviews.index') }}"
                class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg flex items-center space-x-2">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Reviews</span>
            </a>
        </div>

        <!-- Review Details Card -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-6">
                <!-- Review Header -->
                <div class="flex items-start justify-between mb-6">
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                            <span class="text-blue-600 font-bold text-xl">
                                {{ substr($review->customer->first_name ?? 'U', 0, 1) }}{{ substr($review->customer->last_name ?? 'ser', 0, 1) }}
                            </span>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">
                                {{ $review->customer->first_name ?? 'Unknown' }}
                                {{ $review->customer->last_name ?? 'User' }}
                            </h2>
                            <p class="text-gray-600">{{ $review->customer->email ?? 'No email' }}</p>
                            <p class="text-sm text-gray-500">
                                Reviewed on {{ $review->created_at->format('F j, Y \a\t g:i A') }}
                            </p>
                        </div>
                    </div>

                    <!-- Rating Display -->
                    <div class="text-right">
                        <div class="flex text-yellow-400 text-2xl mb-1">
                            @for ($i = 1; $i <= 5; $i++)
                                @if ($i <= $review->rating_value)
                                    <i class="fas fa-star"></i>
                                @else
                                    <i class="far fa-star"></i>
                                @endif
                            @endfor
                        </div>
                        <p class="text-lg font-semibold text-gray-900">{{ $review->rating_value }}/5</p>
                    </div>
                </div>

                <!-- Review Text -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Review</h3>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-gray-700 leading-relaxed">{{ $review->review_text }}</p>
                    </div>
                </div>

                <!-- Order Information -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Order Information</h3>
                    <div class="bg-blue-50 rounded-lg p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm font-medium text-blue-900">Order ID</p>
                                <p class="text-blue-800">#{{ $review->order_id }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-blue-900">Order Status</p>
                                <p class="text-blue-800">{{ ucfirst($review->order->status ?? 'Unknown') }}</p>
                            </div>
                            @if ($review->order && $review->order->orderItems->count() > 0)
                                <div class="md:col-span-2">
                                    <p class="text-sm font-medium text-blue-900 mb-2">Services</p>
                                    <div class="space-y-1">
                                        @foreach ($review->order->orderItems as $item)
                                            <p class="text-blue-800">
                                                • {{ $item->service->name ?? 'Service not found' }}
                                                @if ($item->quantity > 1)
                                                    ({{ $item->quantity }}x)
                                                @endif
                                            </p>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Partner Information -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Partner Information</h3>
                    <div class="bg-green-50 rounded-lg p-4">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                <span class="text-green-600 font-medium">
                                    {{ substr($review->partner->user->first_name ?? 'P', 0, 1) }}{{ substr($review->partner->user->last_name ?? 'artner', 0, 1) }}
                                </span>
                            </div>
                            <div>
                                <p class="font-medium text-green-900">
                                    {{ $review->partner->user->first_name ?? 'Unknown' }}
                                    {{ $review->partner->user->last_name ?? 'Partner' }}
                                </p>
                                <p class="text-green-700">{{ $review->partner->user->email ?? 'No email' }}</p>
                                <p class="text-sm text-green-600">
                                    Average Rating: {{ number_format($review->partner->rating_avg ?? 0, 1) }}/5
                                    ({{ $review->partner->total_ratings ?? 0 }} reviews)
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Partner Response -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Partner Response</h3>
                    @if ($review->hasPartnerResponse())
                        <div class="bg-purple-50 border-l-4 border-purple-200 p-4">
                            <div class="flex items-center space-x-2 mb-3">
                                <i class="fas fa-reply text-purple-600"></i>
                                <span class="font-medium text-purple-900">Response provided</span>
                                <span class="text-sm text-purple-600">
                                    on {{ $review->partner_responded_at->format('F j, Y \a\t g:i A') }}
                                </span>
                            </div>
                            <p class="text-purple-800 leading-relaxed">{{ $review->partner_response }}</p>
                        </div>
                    @else
                        <div class="bg-amber-50 border-l-4 border-amber-200 p-4">
                            <div class="flex items-center space-x-2 mb-3">
                                <i class="fas fa-clock text-amber-600"></i>
                                <span class="font-medium text-amber-900">No response yet</span>
                            </div>
                            <p class="text-amber-700">The partner has not responded to this review yet.</p>

                            <!-- Admin can add response on behalf of partner -->
                            <div class="mt-4">
                                <button onclick="showResponseForm()"
                                    class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-lg text-sm">
                                    Add Response on Behalf of Partner
                                </button>
                            </div>
                        </div>

                        <!-- Response Form (Hidden initially) -->
                        <div id="response-form" class="hidden mt-4">
                            <form onsubmit="submitResponse(event)" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Partner Response</label>
                                    <textarea id="partner_response" rows="4" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                        placeholder="Enter partner's response to this review..."></textarea>
                                </div>
                                <div class="flex space-x-3">
                                    <button type="submit"
                                        class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg">
                                        Submit Response
                                    </button>
                                    <button type="button" onclick="hideResponseForm()"
                                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif
                </div>

                <!-- Actions -->
                <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                    <form action="{{ route('admin.reviews.destroy', $review) }}" method="POST"
                        onsubmit="return confirm('Are you sure you want to delete this review? This action cannot be undone.')"
                        class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                            <i class="fas fa-trash"></i>
                            <span>Delete Review</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function showResponseForm() {
                document.getElementById('response-form').classList.remove('hidden');
            }

            function hideResponseForm() {
                document.getElementById('response-form').classList.add('hidden');
                document.getElementById('partner_response').value = '';
            }

            async function submitResponse(event) {
                event.preventDefault();

                const response = document.getElementById('partner_response').value;

                try {
                    const result = await fetch(`{{ route('admin.reviews.update-response', $review) }}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            partner_response: response
                        })
                    });

                    const data = await result.json();

                    if (data.success) {
                        // Reload the page to show the updated response
                        window.location.reload();
                    } else {
                        alert('Failed to submit response. Please try again.');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                }
            }
        </script>
    @endpush
@endsection
