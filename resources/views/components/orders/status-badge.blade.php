@props(['status'])

@php
    $statusLabels = [
        'created' => 'Created',
        'pending' => 'Pending',
        'pending_payment' => 'Payment Pending',
        'awaiting_partner' => 'Awaiting Partner',
        'partner_assigned' => 'Partner Assigned',
        'assigned' => 'Assigned',
        'accepted' => 'Accepted',
        'confirmed' => 'Confirmed',
        'enroute' => 'En Route',
        'in_progress' => 'In Progress',
        'working' => 'Working',
        'completed' => 'Completed',
        'closed' => 'Closed',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
        'cancelled_by_customer' => 'Cancelled by Customer',
        'cancelled_by_partner' => 'Cancelled by Partner',
        'cancelled_by_admin' => 'Cancelled by Admin',
        'refunded' => 'Refunded',
    ];

    $statusColors = [
        'created' => 'bg-gray-100 text-gray-800',
        'pending' => 'bg-yellow-100 text-yellow-800',
        'pending_payment' => 'bg-amber-100 text-amber-800',
        'awaiting_partner' => 'bg-yellow-100 text-yellow-800',
        'partner_assigned' => 'bg-blue-100 text-blue-800',
        'assigned' => 'bg-blue-100 text-blue-800',
        'accepted' => 'bg-blue-100 text-blue-800',
        'confirmed' => 'bg-blue-100 text-blue-800',
        'enroute' => 'bg-indigo-100 text-indigo-800',
        'in_progress' => 'bg-purple-100 text-purple-800',
        'working' => 'bg-purple-100 text-purple-800',
        'completed' => 'bg-green-100 text-green-800',
        'closed' => 'bg-gray-100 text-gray-800',
        'rejected' => 'bg-red-100 text-red-800',
        'cancelled' => 'bg-red-100 text-red-800',
        'cancelled_by_customer' => 'bg-red-100 text-red-800',
        'cancelled_by_partner' => 'bg-red-100 text-red-800',
        'cancelled_by_admin' => 'bg-red-100 text-red-800',
        'refunded' => 'bg-orange-100 text-orange-800',
    ];

    $label = $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status));
    $colorClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-800';
@endphp

<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $colorClass }}">
    {{ $label }}
</span>
