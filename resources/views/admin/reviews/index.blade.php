@extends('layouts.app')

@section('title', 'Reviews - ServiceCo Admin')

@section('content')
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Reviews & Ratings</h1>
                <p class="text-gray-600">Manage customer reviews and partner responses</p>
            </div>

            <div class="flex space-x-3">
                <button onclick="downloadReviews()"
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2">
                    <i class="fas fa-download"></i>
                    <span>Export</span>
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-6">
            <form method="GET" class="flex flex-wrap gap-4">
                <!-- Search -->
                <div class="flex-1 min-w-64">
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Search by customer name or review text..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <!-- Rating Filter -->
                <div>
                    <select name="rating"
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Ratings</option>
                        @for ($i = 5; $i >= 1; $i--)
                            <option value="{{ $i }}" {{ request('rating') == $i ? 'selected' : '' }}>
                                {{ $i }} Star{{ $i > 1 ? 's' : '' }}
                            </option>
                        @endfor
                    </select>
                </div>

                <!-- Response Filter -->
                <div>
                    <select name="has_response"
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Reviews</option>
                        <option value="1" {{ request('has_response') == '1' ? 'selected' : '' }}>With Response</option>
                        <option value="0" {{ request('has_response') == '0' ? 'selected' : '' }}>Without Response
                        </option>
                    </select>
                </div>

                <!-- Sort -->
                <div>
                    <select name="sort_by"
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="created_at" {{ request('sort_by') == 'created_at' ? 'selected' : '' }}>Date</option>
                        <option value="rating_value" {{ request('sort_by') == 'rating_value' ? 'selected' : '' }}>Rating
                        </option>
                    </select>
                </div>

                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    Filter
                </button>

                @if (request()->hasAny(['search', 'rating', 'has_response', 'sort_by']))
                    <a href="{{ route('admin.reviews.index') }}"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2 rounded-lg">
                        Clear
                    </a>
                @endif
            </form>
        </div>

        <!-- Reviews List -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            @if ($reviews->count() > 0)
                <div class="divide-y divide-gray-200">
                    @foreach ($reviews as $review)
                        <div class="p-6 hover:bg-gray-50">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <!-- Customer Info & Rating -->
                                    <div class="flex items-center space-x-4 mb-3">
                                        <div class="flex-shrink-0">
                                            <div
                                                class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                                <span class="text-blue-600 font-medium">
                                                    {{ substr($review->customer->first_name ?? 'U', 0, 1) }}{{ substr($review->customer->last_name ?? 'ser', 0, 1) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="text-lg font-medium text-gray-900">
                                                {{ $review->customer->first_name ?? 'Unknown' }}
                                                {{ $review->customer->last_name ?? 'User' }}
                                            </h3>
                                            <div class="flex items-center space-x-2">
                                                <div class="flex text-yellow-400">
                                                    @for ($i = 1; $i <= 5; $i++)
                                                        @if ($i <= $review->rating_value)
                                                            <i class="fas fa-star"></i>
                                                        @else
                                                            <i class="far fa-star"></i>
                                                        @endif
                                                    @endfor
                                                </div>
                                                <span class="text-sm text-gray-500">
                                                    {{ $review->created_at->format('M j, Y') }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Review Text -->
                                    <div class="mb-4">
                                        <p class="text-gray-700 leading-relaxed">{{ $review->review_text }}</p>
                                    </div>

                                    <!-- Partner Info -->
                                    <div class="text-sm text-gray-500 mb-3">
                                        Partner: {{ $review->partner->user->first_name ?? 'Unknown' }}
                                        {{ $review->partner->user->last_name ?? 'Partner' }}
                                        | Order: #{{ substr($review->order_id, -8) }}
                                    </div>

                                    <!-- Partner Response -->
                                    @if ($review->hasPartnerResponse())
                                        <div class="bg-blue-50 border-l-4 border-blue-200 p-4 mt-4">
                                            <div class="flex items-center space-x-2 mb-2">
                                                <i class="fas fa-reply text-blue-600"></i>
                                                <span class="font-medium text-blue-900">Partner Response</span>
                                                <span class="text-sm text-blue-600">
                                                    {{ $review->partner_responded_at->format('M j, Y g:i A') }}
                                                </span>
                                            </div>
                                            <p class="text-blue-800">{{ $review->partner_response }}</p>
                                        </div>
                                    @else
                                        <div class="bg-amber-50 border-l-4 border-amber-200 p-3 mt-4">
                                            <span class="text-amber-700 text-sm">
                                                <i class="fas fa-clock mr-1"></i>
                                                Awaiting partner response
                                            </span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Actions -->
                                <div class="flex-shrink-0 ml-4">
                                    <div class="flex items-center space-x-2">
                                        <a href="{{ route('admin.reviews.show', $review) }}"
                                            class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <form action="{{ route('admin.reviews.destroy', $review) }}" method="POST"
                                            onsubmit="return confirm('Are you sure you want to delete this review?')"
                                            class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200">
                    {{ $reviews->appends(request()->query())->links() }}
                </div>
            @else
                <div class="text-center py-12">
                    <i class="fas fa-star text-gray-300 text-6xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No reviews found</h3>
                    <p class="text-gray-500">No reviews match your current filters.</p>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
        <script>
            function downloadReviews() {
                // Implementation for exporting reviews
                alert('Export functionality will be implemented');
            }
        </script>
    @endpush
@endsection
