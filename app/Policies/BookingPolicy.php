<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function assignPartner(User $user, Booking $booking)
    {
        return $user->isSuperAdmin() && $booking->status === 'pending';
    }

    public function accept(User $user, Booking $booking)
    {
        return $user->isPartner() && $booking->partner_id === $user->id && $booking->status === 'assigned';
    }

    public function reject(User $user, Booking $booking)
    {
        return $user->isPartner() && $booking->partner_id === $user->id && $booking->status === 'assigned';
    }

    public function complete(User $user, Booking $booking)
    {
        return $user->isPartner() && $booking->partner_id === $user->id && $booking->status === 'in_progress';
    }

    public function cancel(User $user, Booking $booking)
    {
        // customer can cancel until assigned; super-admin anytime
        if ($user->isSuperAdmin()) return true;
        if ($user->isCustomer() && $booking->customer_id === $user->id) {
            return in_array($booking->status, ['pending', 'assigned']);
        }
        return false;
    }
}
