@extends('layouts.app')

@section('title', 'Order Details - UrbanServe')

@section('content')
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <nav class="flex" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-4">
                        <li>
                            <a href="{{ route('admin.orders.index') }}" class="text-gray-500 hover:text-gray-700">
                                Orders
                            </a>
                        </li>
                        <li>
                            <div class="flex items-center">
                                <svg class="flex-shrink-0 h-5 w-5 text-gray-300" xmlns="http://www.w3.org/2000/svg"
                                    fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path d="M5.555 17.776l8-16 .894.448-8 16-.894-.448z" />
                                </svg>
                                <span class="ml-4 text-gray-700 font-medium">
                                    Order #{{ substr($order->order_id ?? 'N/A', 0, 8) }}
                                </span>
                            </div>
                        </li>
                    </ol>
                </nav>
                <h1 class="text-2xl font-bold text-gray-900 mt-2">Order Details</h1>
            </div>
            <div class="flex space-x-3">
                @if (isset($order) && $order->status === 'pending')
                    <button class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                        Confirm Order
                    </button>
                    <button class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg">
                        Cancel Order
                    </button>
                @endif
            </div>
        </div>

        <!-- Success/Error Messages -->
        @if (session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Order Information -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Order Status -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Order Status</h2>
                    <div class="flex items-center justify-between">
                        <span
                            class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                            {{ isset($order) ? $order->status_classes : 'bg-gray-100 text-gray-800' }}">
                            {{ isset($order) ? $order->status_label : 'Unknown' }}
                        </span>
                        <span class="text-sm text-gray-500">
                            Created {{ isset($order) ? $order->created_at->format('M j, Y g:i A') : 'N/A' }}
                        </span>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Order Items</h2>
                    </div>
                    <div class="divide-y divide-gray-200">
                        @if (isset($order) && $order->orderItems)
                            @foreach ($order->orderItems as $item)
                                <div class="px-6 py-4">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <h3 class="text-sm font-medium text-gray-900">
                                                {{ $item->service->name ?? 'Service N/A' }}
                                            </h3>
                                            <p class="text-sm text-gray-500 mt-1">
                                                {{ $item->service->description ?? 'No description' }}
                                            </p>
                                            <div class="flex items-center mt-2 text-xs text-gray-500">
                                                <span>Quantity: {{ $item->quantity }}</span>
                                                <span class="mx-2">•</span>
                                                <span>Unit Price: ${{ number_format($item->unit_price, 2) }}</span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-medium text-gray-900">
                                                Rs.{{ number_format($item->total_price, 2) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="px-6 py-4 text-center text-gray-500">
                                No items found
                            </div>
                        @endif
                    </div>
                    <div class="px-6 py-4 bg-gray-50">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-semibold text-gray-900">Total</span>
                            <span class="text-lg font-bold text-gray-900">
                                Rs.{{ isset($order) ? number_format($order->total_amount, 2) : '0.00' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Service Requests -->
                @if (isset($order) && $order->serviceRequests && $order->serviceRequests->count() > 0)
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900">Service Assignment</h2>
                        </div>
                        <div class="divide-y divide-gray-200">
                            @if ($order->orderItems->count() > 1)
                                <!-- Multiple Services Summary Row -->
                                <div
                                    class="px-6 py-4 bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-blue-400">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <svg class="w-5 h-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-blue-800">
                                                    Multiple Services ({{ $order->orderItems->count() }} services)
                                                </p>
                                                <p class="text-xs text-blue-600">
                                                    {{ $order->serviceRequests->where('status', 'pending')->count() }}
                                                    pending assignments
                                                </p>
                                            </div>
                                        </div>
                                        <button onclick="toggleServiceDetails()"
                                            class="inline-flex items-center px-3 py-1 border border-blue-300 rounded-md text-xs font-medium text-blue-700 bg-white hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <span id="toggle-text">Show Details</span>
                                            <svg id="toggle-icon" class="ml-1 w-3 h-3 transition-transform"
                                                fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Individual Service Rows (Initially Hidden) -->
                                <div id="service-details" class="hidden">
                                    @foreach ($order->orderItems as $orderItem)
                                        @php
                                            $serviceRequest = $serviceRequestsByOrderItem[$orderItem->item_id] ?? null;
                                        @endphp
                                        <div class="px-6 py-4 bg-gray-50">
                                            <div class="flex items-center justify-between">
                                                <div class="flex-1">
                                                    <div class="flex items-center">
                                                        <div
                                                            class="flex-shrink-0 w-8 h-8 bg-white rounded-full flex items-center justify-center border-2 border-gray-200">
                                                            <span
                                                                class="text-xs font-semibold text-gray-600">{{ $loop->iteration }}</span>
                                                        </div>
                                                        <div class="ml-4">
                                                            <p class="text-sm font-medium text-gray-900">
                                                                {{ $orderItem->service->name ?? 'Unknown Service' }}
                                                            </p>
                                                            <p class="text-xs text-gray-500">
                                                                Quantity: {{ $orderItem->quantity }} •
                                                                Price: Rs.{{ number_format($orderItem->total_price, 2) }}
                                                            </p>
                                                            @if ($serviceRequest)
                                                                <p class="text-xs text-gray-500 mt-1">
                                                                    Date:
                                                                    {{ $serviceRequest->service_date ? \Carbon\Carbon::parse($serviceRequest->service_date)->format('M j, Y') : 'TBD' }}
                                                                    •
                                                                    Time: {{ $serviceRequest->start_time ?? 'TBD' }}
                                                                </p>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex items-center space-x-3">
                                                    @if ($serviceRequest)
                                                        <!-- Partner Assignment Dropdown -->
                                                        <div class="min-w-0 flex-1">
                                                            @if ($serviceRequest->partner)
                                                                <p class="text-xs text-gray-600">Assigned to:</p>
                                                                <p class="text-sm font-medium text-gray-900">
                                                                    {{ $serviceRequest->partner->user->full_name }}</p>
                                                            @else
                                                                <form
                                                                    action="{{ route('admin.service-requests.assign', $serviceRequest->request_id) }}"
                                                                    method="POST" class="inline-block">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <select name="partner_id" onchange="this.form.submit()"
                                                                        class="text-xs border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500">
                                                                        <option value="">Assign Partner...</option>
                                                                        @foreach ($partners as $partner)
                                                                            <option value="{{ $partner->partner_id }}"
                                                                                title="Compatibility: {{ $partner->compatibility_score }}%, Workload: {{ $partner->current_workload }}, Availability: {{ ucfirst($partner->availability_status ?? 'unknown') }}">
                                                                                {{ $partner->user->full_name }}
                                                                                ({{ $partner->compatibility_score }}% match ·
                                                                                {{ strtoupper($partner->availability_status ?? 'UNKNOWN') }}{{ $partner->is_busy ? ' · BUSY' : '' }})
                                                                            </option>
                                                                        @endforeach
                                                                    </select>
                                                                </form>
                                                            @endif
                                                        </div>
                                                        <!-- Status Badge -->
                                                        <span
                                                            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $serviceRequest->status_classes }}">
                                                            {{ $serviceRequest->status_label }}
                                                        </span>
                                                    @else
                                                        <span
                                                            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                            No Request
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <!-- Single Service Row -->
                                @foreach ($order->serviceRequests as $request)
                                    <div class="px-6 py-4">
                                        <div class="flex items-center justify-between">
                                            <div class="flex-1">
                                                @php
                                                    $orderItem = $order->orderItems->first();
                                                @endphp
                                                <p class="text-sm font-medium text-gray-900">
                                                    {{ $orderItem->service->name ?? 'Service Request' }}
                                                </p>
                                                <p class="text-sm text-gray-500">
                                                    Date:
                                                    {{ $request->service_date ? \Carbon\Carbon::parse($request->service_date)->format('M j, Y') : 'TBD' }}
                                                    •
                                                    Time: {{ $request->start_time ?? 'TBD' }}
                                                    @if ($request->end_time)
                                                        - {{ $request->end_time }}
                                                    @endif
                                                </p>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    Quantity: {{ $orderItem->quantity }} •
                                                    Price: Rs.{{ number_format($orderItem->total_price, 2) }}
                                                </p>
                                            </div>
                                            <div class="flex items-center space-x-3">
                                                <!-- Partner Assignment -->
                                                @if ($request->partner)
                                                    <div class="text-right">
                                                        <p class="text-xs text-gray-600">Assigned to:</p>
                                                        <p class="text-sm font-medium text-gray-900">
                                                            {{ $request->partner->user->full_name }}</p>
                                                    </div>
                                                @else
                                                    <form
                                                        action="{{ route('admin.service-requests.assign', $request->request_id) }}"
                                                        method="POST" class="inline-block">
                                                        @csrf
                                                        @method('PATCH')
                                                        <select name="partner_id" onchange="this.form.submit()"
                                                            class="text-sm border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500">
                                                            <option value="">Assign Partner...</option>
                                                            @foreach ($partners as $partner)
                                                                <option value="{{ $partner->partner_id }}"
                                                                    title="Compatibility: {{ $partner->compatibility_score }}%, Workload: {{ $partner->current_workload }}, Availability: {{ ucfirst($partner->availability_status ?? 'unknown') }}">
                                                                    {{ $partner->user->full_name }}
                                                                    ({{ $partner->compatibility_score }}% match ·
                                                                    {{ strtoupper($partner->availability_status ?? 'UNKNOWN') }}{{ $partner->is_busy ? ' · BUSY' : '' }})
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </form>
                                                @endif
                                                <!-- Status Badge -->
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $request->status_classes }}">
                                                    {{ $request->status_label }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <!-- Completion Documents Section -->
            @if (isset($order) && $order->serviceRequests)
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Completion Documents</h2>
                        <p class="text-sm text-gray-500 mt-1">Documents uploaded by partners upon service completion</p>
                    </div>
                    <div class="divide-y divide-gray-200">
                        @php $hasDocuments = false; @endphp
                        @foreach ($order->serviceRequests as $request)
                            @if ($request->completionDocuments && $request->completionDocuments->count() > 0)
                                @php $hasDocuments = true; @endphp
                                <div class="px-6 py-4">
                                    <div class="mb-3">
                                        <h3 class="text-sm font-medium text-gray-900">
                                            {{ $request->orderItem->service->name ?? 'Service' }}
                                        </h3>
                                        <p class="text-xs text-gray-500">
                                            Request #{{ $request->request_id }}
                                            @if ($request->partner)
                                                - Partner: {{ $request->partner->user->full_name }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        @foreach ($request->completionDocuments->where('is_deleted', false) as $document)
                                            <div class="border border-gray-200 rounded-lg p-3 hover:bg-gray-50">
                                                <div class="flex items-start justify-between">
                                                    <div class="flex-1">
                                                        <div class="flex items-center">
                                                            <i class="fas fa-file-alt text-blue-500 mr-2"></i>
                                                            <span class="text-sm font-medium text-gray-900">
                                                                {{ $document->original_filename }}
                                                            </span>
                                                            @if ($document->is_verified)
                                                                <i class="fas fa-check-circle text-green-500 ml-2"
                                                                    title="Verified"></i>
                                                            @endif
                                                        </div>
                                                        <div class="mt-1 text-xs text-gray-500">
                                                            <span
                                                                class="capitalize">{{ str_replace('_', ' ', $document->document_type) }}</span>
                                                            <span class="mx-1">•</span>
                                                            <span>{{ $document->human_file_size }}</span>
                                                            <span class="mx-1">•</span>
                                                            <span>{{ $document->created_at->format('M j, Y g:i A') }}</span>
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center space-x-2 ml-3">
                                                        <a href="{{ route('admin.orders.download-document', ['documentId' => $document->document_id]) }}"
                                                            class="inline-flex items-center px-2 py-1 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                                            title="Download Document">
                                                            <i class="fas fa-download mr-1"></i>
                                                            Download
                                                        </a>
                                                        @if (!$document->is_verified && in_array($document->document_type, ['signed_form', 'bill_receipt']))
                                                            <button type="button"
                                                                class="inline-flex items-center px-2 py-1 border border-green-300 shadow-sm text-xs font-medium rounded text-green-700 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                                                onclick="verifyDocument({{ $document->document_id }})"
                                                                title="Mark as Verified">
                                                                <i class="fas fa-check mr-1"></i>
                                                                Verify
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                        @if (!$hasDocuments)
                            <div class="px-6 py-8 text-center">
                                <i class="fas fa-file-alt text-gray-300 text-3xl mb-2"></i>
                                <p class="text-sm text-gray-500">No completion documents uploaded yet</p>
                                <p class="text-xs text-gray-400 mt-1">Documents will appear here when partners complete
                                    their work</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Customer Information -->
        <div class="space-y-6">
            <!-- Customer Details -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Customer Information</h2>
                @if (isset($order) && $order->user)
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-gray-300 rounded-full overflow-hidden mr-3">
                                <img src="https://ui-avatars.com/api/?name={{ urlencode($order->user->full_name) }}&background=random"
                                    alt="{{ $order->user->full_name }}" class="w-full h-full object-cover">
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $order->user->full_name }}</p>
                                <p class="text-sm text-gray-500">{{ $order->user->email }}</p>
                            </div>
                        </div>
                        @if ($order->user->phone)
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-phone mr-2"></i>
                                {{ $order->user->phone }}
                            </div>
                        @endif
                    </div>
                @else
                    <p class="text-gray-500">Customer information not available</p>
                @endif
            </div>

            <!-- Payment Information -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Payment Information</h2>
                @if (isset($order))
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Payment Method:</span>
                            <span class="text-sm font-medium text-gray-900">
                                {{ $order->payment_method ? ucfirst($order->payment_method) : 'N/A' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Payment Status:</span>
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @if ($order->payment_status === 'paid') bg-green-100 text-green-800
                                @elseif($order->payment_status === 'pending') bg-yellow-100 text-yellow-800
                                @elseif($order->payment_status === 'failed') bg-red-100 text-red-800
                                @elseif($order->payment_status === 'refunded') bg-purple-100 text-purple-800
                                @else bg-gray-100 text-gray-800 @endif">
                                {{ $order->payment_status ? ucfirst($order->payment_status) : 'Pending' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Total Amount:</span>
                            <span class="text-sm font-bold text-gray-900">
                                Rs. {{ number_format($order->total_amount, 2) }}
                            </span>
                        </div>
                        @if ($order->payment_method === 'cod' && $order->commission_deducted)
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Commission Deducted:</span>
                                <span class="text-sm font-medium text-green-600">
                                    Yes
                                    ({{ $order->commission_deducted_at ? $order->commission_deducted_at->format('M j, Y g:i A') : 'N/A' }})
                                </span>
                            </div>
                        @endif
                        @if ($order->subtotal)
                            <div class="pt-3 border-t border-gray-200">
                                <div class="space-y-2">
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-600">Subtotal:</span>
                                        <span class="text-gray-900">Rs. {{ number_format($order->subtotal, 2) }}</span>
                                    </div>
                                    @if ($order->service_charge > 0)
                                        <div class="flex justify-between items-center text-sm">
                                            <span class="text-gray-600">Service Charge:</span>
                                            <span class="text-gray-900">Rs.
                                                {{ number_format($order->service_charge, 2) }}</span>
                                        </div>
                                    @endif
                                    @if ($order->tax_amount > 0)
                                        <div class="flex justify-between items-center text-sm">
                                            <span class="text-gray-600">Tax:</span>
                                            <span class="text-gray-900">Rs.
                                                {{ number_format($order->tax_amount, 2) }}</span>
                                        </div>
                                    @endif
                                    @if ($order->discount_amount > 0)
                                        <div class="flex justify-between items-center text-sm">
                                            <span class="text-gray-600">Discount:</span>
                                            <span class="text-red-600">-Rs.
                                                {{ number_format($order->discount_amount, 2) }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <p class="text-gray-500">Payment information not available</p>
                @endif
            </div>

            <!-- Service Address -->
            @if (isset($order) && $order->serviceRequests->first() && $order->serviceRequests->first()->address)
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Service Address</h2>
                    @php $address = $order->serviceRequests->first()->address @endphp
                    <div class="text-sm text-gray-600">
                        <p>{{ $address->address_line_1 }}</p>
                        @if ($address->address_line_2)
                            <p>{{ $address->address_line_2 }}</p>
                        @endif
                        <p>{{ $address->city }}, {{ $address->state }} {{ $address->postal_code }}</p>
                        <p>{{ $address->country }}</p>
                    </div>
                </div>
            @endif

            <!-- Actions -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Actions</h2>
                <div class="space-y-3">
                    <button class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                        Send Message to Customer
                    </button>

                    <!-- Partner Assignment Form -->
                    @if (in_array($order->status, ['awaiting_partner', 'created', 'pending', 'confirmed', 'partner_assigned'], true) && isset($partners) && $partners->count() > 0)
                        <form action="{{ route('admin.orders.assign-partner', $order->order_id) }}" method="POST"
                            class="space-y-3">
                            @csrf
                            <div>
                                <label for="partner_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ $order->serviceRequests->contains(fn ($sr) => $sr->partner_id) ? 'Reassign Partner' : 'Assign Partner' }}
                                </label>
                                <select name="partner_id" id="partner_id" required
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select a partner...</option>
                                    @foreach ($partners as $partner)
                                        <option value="{{ $partner->partner_id }}">
                                            {{ $partner->user->first_name ?? '' }}
                                            {{ $partner->user->last_name ?? '' }}
                                            @if ($partner->rating_avg)
                                                (★{{ number_format($partner->rating_avg, 1) }} -
                                                {{ $partner->total_ratings }} reviews)
                                            @else
                                                (New Partner)
                                            @endif
                                            · {{ $partner->compatibility_score }}% match
                                            · {{ strtoupper($partner->availability_status ?? 'UNKNOWN') }}{{ $partner->is_busy ? ' · BUSY' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-gray-500 mt-1">
                                    Every active partner is listed (admin override). Assigning moves the order to
                                    "partner assigned" and notifies the partner.
                                </p>
                            </div>
                            <button type="submit"
                                class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Assign Partner
                            </button>
                        </form>
                    @elseif(in_array($order->status, ['awaiting_partner', 'created', 'pending', 'confirmed', 'partner_assigned'], true))
                        <div
                            class="w-full bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg text-sm">
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                No active partners available for assignment
                            </div>
                        </div>
                    @elseif($order->status === 'confirmed' && $order->serviceRequests->first()?->partner)
                        @php $assignedPartner = $order->serviceRequests->first()->partner @endphp
                        <div
                            class="w-full bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    <span class="font-medium">Partner Assigned:</span>
                                </div>
                            </div>
                            <div class="mt-2 text-green-700">
                                {{ $assignedPartner->user->first_name }} {{ $assignedPartner->user->last_name }}
                                @if ($assignedPartner->rating_avg)
                                    (★{{ number_format($assignedPartner->rating_avg, 1) }})
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="w-full bg-gray-50 border border-gray-200 text-gray-600 px-4 py-3 rounded-lg text-sm">
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                        clip-rule="evenodd" />
                                </svg>
                                Order status: {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Partner selection is available while the order is
                                created, pending, confirmed, awaiting a partner, or already partner assigned.</p>
                        </div>
                    @endif <button
                        class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm">
                        Mark as Completed
                    </button>
                </div>
            </div>
        </div>
    </div>
    </div>

    <script>
        function toggleServiceDetails() {
            const serviceDetails = document.getElementById('service-details');
            const toggleText = document.getElementById('toggle-text');
            const toggleIcon = document.getElementById('toggle-icon');

            if (serviceDetails.classList.contains('hidden')) {
                serviceDetails.classList.remove('hidden');
                toggleText.textContent = 'Hide Details';
                toggleIcon.style.transform = 'rotate(180deg)';
            } else {
                serviceDetails.classList.add('hidden');
                toggleText.textContent = 'Show Details';
                toggleIcon.style.transform = 'rotate(0deg)';
            }
        }

        // AJAX Partner Assignment (Optional Enhancement)
        function assignPartner(requestId, partnerId) {
            fetch(`/admin/service-requests/${requestId}/assign`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        partner_id: partnerId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload page to show updated assignment
                        window.location.reload();
                    } else {
                        alert('Error assigning partner: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error assigning partner');
                });
        }

        // Document verification function
        function verifyDocument(documentId) {
            if (!confirm('Are you sure you want to mark this document as verified?')) {
                return;
            }

            fetch(`/admin/documents/${documentId}/verify`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload(); // Refresh the page to show updated status
                    } else {
                        alert('Error verifying document: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error verifying document');
                });
        }
    </script>
@endsection
