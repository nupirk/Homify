<?php

namespace App\Helpers;

class StatusEnumHelper
{
    // Order Status Enum Values
    const ORDER_STATUS_CREATED = 'created';
    const ORDER_STATUS_PENDING = 'pending';
    const ORDER_STATUS_PENDING_PAYMENT = 'pending_payment';
    const ORDER_STATUS_AWAITING_PARTNER = 'awaiting_partner';
    const ORDER_STATUS_PARTNER_ASSIGNED = 'partner_assigned';
    const ORDER_STATUS_ASSIGNED = 'assigned'; // Legacy value
    const ORDER_STATUS_ACCEPTED = 'accepted';
    const ORDER_STATUS_CONFIRMED = 'confirmed';
    const ORDER_STATUS_ENROUTE = 'enroute';
    const ORDER_STATUS_IN_PROGRESS = 'in_progress';
    const ORDER_STATUS_WORKING = 'working'; // Legacy value
    const ORDER_STATUS_COMPLETED = 'completed';
    const ORDER_STATUS_CLOSED = 'closed';
    const ORDER_STATUS_CANCELLED_BY_CUSTOMER = 'cancelled_by_customer';
    const ORDER_STATUS_CANCELLED_BY_PARTNER = 'cancelled_by_partner';
    const ORDER_STATUS_CANCELLED_BY_ADMIN = 'cancelled_by_admin';
    const ORDER_STATUS_CANCELLED = 'cancelled'; // Generic cancellation
    const ORDER_STATUS_REJECTED = 'rejected';
    const ORDER_STATUS_REFUNDED = 'refunded';
    const ORDER_STATUS_PENDING_REASSIGNMENT = 'pending_reassignment';

    // Service Request Status Enum Values
    const SERVICE_REQUEST_STATUS_PENDING = 'pending';
    const SERVICE_REQUEST_STATUS_ASSIGNED = 'assigned';
    const SERVICE_REQUEST_STATUS_ACCEPTED = 'accepted';
    const SERVICE_REQUEST_STATUS_ENROUTE = 'enroute';
    const SERVICE_REQUEST_STATUS_REJECTED = 'rejected';
    const SERVICE_REQUEST_STATUS_IN_PROGRESS = 'in_progress';
    const SERVICE_REQUEST_STATUS_WORKING = 'working'; // Legacy value
    const SERVICE_REQUEST_STATUS_COMPLETED = 'completed';
    const SERVICE_REQUEST_STATUS_CANCELLED = 'cancelled';

    /**
     * Get all order status values
     *
     * @return array
     */
    public static function getOrderStatusValues(): array
    {
        return [
            self::ORDER_STATUS_CREATED,
            self::ORDER_STATUS_PENDING,
            self::ORDER_STATUS_PENDING_PAYMENT,
            self::ORDER_STATUS_AWAITING_PARTNER,
            self::ORDER_STATUS_PARTNER_ASSIGNED,
            self::ORDER_STATUS_ASSIGNED,
            self::ORDER_STATUS_ACCEPTED,
            self::ORDER_STATUS_CONFIRMED,
            self::ORDER_STATUS_ENROUTE,
            self::ORDER_STATUS_IN_PROGRESS,
            self::ORDER_STATUS_WORKING,
            self::ORDER_STATUS_COMPLETED,
            self::ORDER_STATUS_CLOSED,
            self::ORDER_STATUS_CANCELLED_BY_CUSTOMER,
            self::ORDER_STATUS_CANCELLED_BY_PARTNER,
            self::ORDER_STATUS_CANCELLED_BY_ADMIN,
            self::ORDER_STATUS_CANCELLED,
            self::ORDER_STATUS_REJECTED,
            self::ORDER_STATUS_REFUNDED,
            self::ORDER_STATUS_PENDING_REASSIGNMENT,
        ];
    }

    /**
     * Get all service request status values
     *
     * @return array
     */
    public static function getServiceRequestStatusValues(): array
    {
        return [
            self::SERVICE_REQUEST_STATUS_PENDING,
            self::SERVICE_REQUEST_STATUS_ASSIGNED,
            self::SERVICE_REQUEST_STATUS_ACCEPTED,
            self::SERVICE_REQUEST_STATUS_ENROUTE,
            self::SERVICE_REQUEST_STATUS_REJECTED,
            self::SERVICE_REQUEST_STATUS_IN_PROGRESS,
            self::SERVICE_REQUEST_STATUS_WORKING,
            self::SERVICE_REQUEST_STATUS_COMPLETED,
            self::SERVICE_REQUEST_STATUS_CANCELLED,
        ];
    }

    /**
     * Map order status to service request status
     *
     * @param string $orderStatus
     * @return string
     */
    public static function mapOrderStatusToServiceRequestStatus(string $orderStatus): string
    {
        return match ($orderStatus) {
            // Order pending states -> Service request pending
            self::ORDER_STATUS_CREATED, 
            self::ORDER_STATUS_PENDING, 
            self::ORDER_STATUS_PENDING_PAYMENT,
            self::ORDER_STATUS_AWAITING_PARTNER => self::SERVICE_REQUEST_STATUS_PENDING,

            // Partner assignment states -> Service request assigned
            self::ORDER_STATUS_PARTNER_ASSIGNED, 
            self::ORDER_STATUS_ASSIGNED => self::SERVICE_REQUEST_STATUS_ASSIGNED,
            self::ORDER_STATUS_PENDING_REASSIGNMENT => self::SERVICE_REQUEST_STATUS_PENDING,

            // Work progression states -> Direct mapping
            self::ORDER_STATUS_ACCEPTED => self::SERVICE_REQUEST_STATUS_ACCEPTED,
            self::ORDER_STATUS_IN_PROGRESS, 
            self::ORDER_STATUS_WORKING => self::SERVICE_REQUEST_STATUS_IN_PROGRESS,
            self::ORDER_STATUS_COMPLETED, 
            self::ORDER_STATUS_CLOSED => self::SERVICE_REQUEST_STATUS_COMPLETED,
            self::ORDER_STATUS_REJECTED => self::SERVICE_REQUEST_STATUS_REJECTED,

            // All cancellation types -> Service request cancelled
            self::ORDER_STATUS_CANCELLED, 
            self::ORDER_STATUS_CANCELLED_BY_CUSTOMER, 
            self::ORDER_STATUS_CANCELLED_BY_PARTNER, 
            self::ORDER_STATUS_CANCELLED_BY_ADMIN => self::SERVICE_REQUEST_STATUS_CANCELLED,

            // Intermediate states
            self::ORDER_STATUS_CONFIRMED, 
            self::ORDER_STATUS_ENROUTE => self::SERVICE_REQUEST_STATUS_ACCEPTED,
            self::ORDER_STATUS_REFUNDED => self::SERVICE_REQUEST_STATUS_CANCELLED,

            default => self::SERVICE_REQUEST_STATUS_CANCELLED
        };
    }

    /**
     * Get human-readable status label
     *
     * @param string $status
     * @return string
     */
    public static function getStatusLabel(string $status): string
    {
        return ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * Get CSS classes for order status
     *
     * @param string $status
     * @return string
     */
    public static function getOrderStatusClasses(string $status): string
    {
        return match ($status) {
            self::ORDER_STATUS_COMPLETED => 'bg-green-100 text-green-800',
            self::ORDER_STATUS_CREATED, 
            self::ORDER_STATUS_PENDING, 
            self::ORDER_STATUS_PENDING_PAYMENT,
            self::ORDER_STATUS_AWAITING_PARTNER => 'bg-yellow-100 text-yellow-800',
            self::ORDER_STATUS_PARTNER_ASSIGNED, 
            self::ORDER_STATUS_ASSIGNED => 'bg-indigo-100 text-indigo-800',
            self::ORDER_STATUS_PENDING_REASSIGNMENT => 'bg-purple-100 text-purple-800',
            self::ORDER_STATUS_ACCEPTED, 
            self::ORDER_STATUS_CONFIRMED, 
            self::ORDER_STATUS_ENROUTE => 'bg-blue-100 text-blue-800',
            self::ORDER_STATUS_IN_PROGRESS, 
            self::ORDER_STATUS_WORKING => 'bg-cyan-100 text-cyan-800',
            self::ORDER_STATUS_REJECTED => 'bg-purple-100 text-purple-800',
            self::ORDER_STATUS_CANCELLED, 
            self::ORDER_STATUS_CANCELLED_BY_CUSTOMER, 
            self::ORDER_STATUS_CANCELLED_BY_PARTNER, 
            self::ORDER_STATUS_CANCELLED_BY_ADMIN => 'bg-red-100 text-red-800',
            self::ORDER_STATUS_REFUNDED => 'bg-orange-100 text-orange-800',
            self::ORDER_STATUS_CLOSED => 'bg-gray-300 text-gray-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }

    /**
     * Get CSS classes for service request status
     *
     * @param string $status
     * @return string
     */
    public static function getServiceRequestStatusClasses(string $status): string
    {
        return match ($status) {
            self::SERVICE_REQUEST_STATUS_COMPLETED => 'bg-green-100 text-green-800',
            self::SERVICE_REQUEST_STATUS_PENDING => 'bg-yellow-100 text-yellow-800',
            self::SERVICE_REQUEST_STATUS_ASSIGNED => 'bg-indigo-100 text-indigo-800',
            self::SERVICE_REQUEST_STATUS_ACCEPTED => 'bg-blue-100 text-blue-800',
            self::SERVICE_REQUEST_STATUS_REJECTED => 'bg-purple-100 text-purple-800',
            self::SERVICE_REQUEST_STATUS_IN_PROGRESS, 
            self::SERVICE_REQUEST_STATUS_WORKING => 'bg-cyan-100 text-cyan-800',
            self::SERVICE_REQUEST_STATUS_CANCELLED => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800'
        };
    }
}