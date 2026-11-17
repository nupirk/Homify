# Enum Reference Guide

## Order Status Enum (`order_status_enum`)

The Order status enum represents the customer/admin view of the service lifecycle.

```
order_status_enum: 
- 'created'               - Initial order creation
- 'pending'               - Order is pending processing
- 'awaiting_partner'      - Order is waiting for partner assignment
- 'partner_assigned'      - Partner has been assigned to the order
- 'assigned'              - Legacy value, same as partner_assigned
- 'accepted'              - Partner has accepted the order
- 'confirmed'             - Order has been confirmed
- 'enroute'               - Partner is on the way to service location
- 'in_progress'           - Service is currently being performed
- 'working'               - Legacy value, same as in_progress
- 'completed'             - Service has been completed
- 'closed'                - Order has been administratively closed
- 'cancelled_by_customer' - Order was cancelled by the customer
- 'cancelled_by_partner'  - Order was cancelled by the partner
- 'cancelled_by_admin'    - Order was cancelled by an administrator
- 'cancelled'             - Generic cancellation (legacy value)
- 'rejected'              - Order was rejected by partner
- 'refunded'              - Order payment has been refunded
```

## Service Request Status Enum (`service_request_status_enum`)

The Service Request status enum represents the partner workflow view of the service lifecycle.

```
service_request_status_enum:
- 'pending'      - Request is pending partner assignment
- 'assigned'     - Request has been assigned to a partner
- 'accepted'     - Partner has accepted the service request
- 'rejected'     - Partner has rejected the service request
- 'in_progress'  - Service is currently being performed
- 'working'      - Legacy value, same as in_progress
- 'completed'    - Service has been completed
- 'cancelled'    - Service request has been cancelled
```

## Status Mapping Logic

Order status (customer/admin view) maps to Service Request status (partner view) as follows:

```
- 'created', 'pending', 'awaiting_partner' → 'pending'
- 'partner_assigned' → 'assigned'
- 'accepted' → 'accepted'
- 'confirmed', 'enroute' → 'accepted'
- 'in_progress', 'working' → 'in_progress'
- 'completed', 'closed' → 'completed'
- 'rejected' → 'rejected'
- 'cancelled', 'cancelled_by_customer', 'cancelled_by_partner', 'cancelled_by_admin', 'refunded' → 'cancelled'
```