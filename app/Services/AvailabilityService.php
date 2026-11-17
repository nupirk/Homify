<?php

namespace App\Services;

use App\Models\PartnerAvailability;
use App\Models\ServicePricing;
use App\Models\ServiceRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Single source of truth for "can this partner take this job?".
 *
 * Working hours come from `partner_availability` rows for the date. A partner
 * with no rows for that date is treated as available on the platform default
 * window (09:00-18:00) so newly approved partners are bookable immediately
 * instead of being invisible until someone seeds rows for them.
 *
 * Existing bookings block a new job when the two time ranges overlap, using
 * `services.estimated_duration_min` (falling back to the default duration)
 * when the booking has no explicit `end_time`.
 */
class AvailabilityService
{
    public const DEFAULT_START = '09:00';
    public const DEFAULT_END = '18:00';
    public const DEFAULT_DURATION_MIN = 60;
    public const SLOT_STEP_MIN = 30;
    public const LEAD_TIME_MIN = 30;

    /** Job states that occupy a partner's calendar. */
    public const BLOCKING_STATUSES = [
        'pending',
        'assigned',
        'accepted',
        'in_progress',
        'working',
    ];

    /**
     * Duration used for a job: the longest service in the cart, never below
     * one slot. Unknown services fall back to the platform default.
     */
    public function durationForServices(array $serviceIds = [], ?int $fallback = null): int
    {
        $serviceIds = array_values(array_filter(array_map('intval', $serviceIds)));

        if ($serviceIds !== []) {
            // Duration lives on the pricing tiers, not the service row.
            $duration = ServicePricing::whereIn('service_id', $serviceIds)
                ->where('is_active', true)
                ->whereNotNull('estimated_duration_min')
                ->max('estimated_duration_min');

            if ($duration) {
                return max(self::SLOT_STEP_MIN, (int) $duration);
            }
        }

        return max(self::SLOT_STEP_MIN, (int) ($fallback ?: self::DEFAULT_DURATION_MIN));
    }

    public function durationForService(?int $serviceId, ?int $fallback = null): int
    {
        return $this->durationForServices($serviceId ? [$serviceId] : [], $fallback);
    }

    /**
     * Working windows for a partner on a date.
     *
     * @return array<int, array{start:string,end:string,booked:bool}>
     */
    public function windowsFor(int $partnerId, Carbon $date): array
    {
        $rows = PartnerAvailability::query()
            ->where('partner_id', $partnerId)
            ->where('service_date', $date->toDateString())
            ->orderBy('start_time')
            ->get();

        if ($rows->isEmpty()) {
            return [[
                'start' => self::DEFAULT_START,
                'end' => self::DEFAULT_END,
                'booked' => false,
            ]];
        }

        return $rows->map(function (PartnerAvailability $row) {
            return [
                'start' => $row->start_time->format('H:i'),
                'end' => $row->end_time->format('H:i'),
                'booked' => (bool) $row->booked,
            ];
        })->all();
    }

    /**
     * Existing bookings for a partner on a date as [start, end] intervals.
     *
     * `$ignoreRequestId` accepts a single request id or a list of ids. The
     * admin assignment UI passes every request of the order being assigned, so
     * that partner's own job does not count as a conflict against itself.
     *
     * @param int|array<int,int>|null $ignoreRequestId
     * @return array<int, array{start:Carbon,end:Carbon}>
     */
    public function dayBookings(int $partnerId, Carbon $date, int|array|null $ignoreRequestId = null): array
    {
        $ignoreIds = $this->normalizeIgnoreIds($ignoreRequestId);

        $requests = ServiceRequest::query()
            ->with('orderItem.service')
            ->where('partner_id', $partnerId)
            ->where('service_date', $date->toDateString())
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->when($ignoreIds !== [], function ($query) use ($ignoreIds) {
                $query->whereNotIn('request_id', $ignoreIds);
            })
            ->get(['request_id', 'order_item_id', 'start_time', 'end_time']);

        return $requests->map(function (ServiceRequest $request) use ($date) {
            $start = $date->copy()->setTimeFromTimeString($request->start_time->format('H:i'));

            if ($request->end_time) {
                $end = $date->copy()->setTimeFromTimeString($request->end_time->format('H:i'));
            } else {
                $duration = $this->durationForService($request->orderItem?->service_id);
                $end = $start->copy()->addMinutes($duration);
            }

            return ['start' => $start, 'end' => $end];
        })->all();
    }

    /**
     * Full answer for a requested start time.
     *
     * @return array{available:bool,reason:?string,status:string,duration:int,start:Carbon,end:Carbon}
     */
    public function check(int $partnerId, Carbon $start, int $duration, int|array|null $ignoreRequestId = null): array
    {
        $start = $start->copy()->seconds(0);
        $end = $start->copy()->addMinutes($duration);

        if ($start->isPast()) {
            return $this->result(false, 'past', $start, $end, $duration);
        }

        $windows = $this->windowsFor($partnerId, $start);

        $insideWindow = false;
        foreach ($windows as $window) {
            if ($window['booked']) {
                continue;
            }

            $windowStart = $start->copy()->setTimeFromTimeString($window['start']);
            $windowEnd = $start->copy()->setTimeFromTimeString($window['end']);

            if ($start->gte($windowStart) && $end->lte($windowEnd)) {
                $insideWindow = true;
                break;
            }
        }

        if (!$insideWindow) {
            return $this->result(false, 'outside_hours', $start, $end, $duration);
        }

        foreach ($this->dayBookings($partnerId, $start, $ignoreRequestId) as $booking) {
            if ($booking['start'] < $end && $booking['end'] > $start) {
                return $this->result(false, 'conflict', $start, $end, $duration);
            }
        }

        return $this->result(true, null, $start, $end, $duration);
    }

    public function isAvailable(int $partnerId, Carbon $start, ?int $serviceId = null, ?int $duration = null): bool
    {
        $duration ??= $this->durationForService($serviceId);

        return $this->check($partnerId, $start, $duration)['available'];
    }

    /**
     * Bookable start times for a date, derived from the working windows and
     * subtracted against real bookings.
     *
     * @return array<int, array{time:string,start:string,end:string,available:bool}>
     */
    public function slotsFor(int $partnerId, Carbon $date, ?int $serviceId = null, ?int $duration = null, int|array|null $ignoreRequestId = null): array
    {
        $duration ??= $this->durationForService($serviceId);
        $bookings = $this->dayBookings($partnerId, $date, $ignoreRequestId);
        $slots = [];

        foreach ($this->windowsFor($partnerId, $date) as $window) {
            if ($window['booked']) {
                continue;
            }

            $cursor = $date->copy()->setTimeFromTimeString($window['start']);
            $windowEnd = $date->copy()->setTimeFromTimeString($window['end']);

            while ($cursor->copy()->addMinutes($duration)->lte($windowEnd)) {
                $slotEnd = $cursor->copy()->addMinutes($duration);
                $available = !$cursor->isPast();

                foreach ($bookings as $booking) {
                    if ($booking['start'] < $slotEnd && $booking['end'] > $cursor) {
                        $available = false;
                        break;
                    }
                }

                $slots[] = [
                    'time' => $cursor->format('H:i'),
                    'start' => $cursor->toDateTimeString(),
                    'end' => $slotEnd->toDateTimeString(),
                    'available' => $available,
                ];

                $cursor->addMinutes(self::SLOT_STEP_MIN);
            }
        }

        return $slots;
    }

    /**
     * Search-result label: is the partner free for the requested slot, and how
     * loaded is the rest of their day?
     */
    public function statusFor(int $partnerId, Carbon $start, int $duration, int|array|null $ignoreRequestId = null): string
    {
        $check = $this->check($partnerId, $start, $duration, $ignoreRequestId);

        if (!$check['available']) {
            return 'unavailable';
        }

        $windowMinutes = 0;
        foreach ($this->windowsFor($partnerId, $start) as $window) {
            if ($window['booked']) {
                continue;
            }
            $from = $start->copy()->setTimeFromTimeString($window['start']);
            $to = $start->copy()->setTimeFromTimeString($window['end']);
            $windowMinutes += max(0, $from->diffInMinutes($to));
        }

        $bookedMinutes = 0;
        foreach ($this->dayBookings($partnerId, $start, $ignoreRequestId) as $booking) {
            $bookedMinutes += max(0, $booking['start']->diffInMinutes($booking['end']));
        }

        if ($windowMinutes > 0 && ($bookedMinutes / $windowMinutes) >= 0.7) {
            return 'limited';
        }

        return 'available';
    }

    /**
     * Day-level label used when the caller has no specific start time.
     */
    public function dayStatus(int $partnerId, Carbon $date, ?int $serviceId = null, ?int $duration = null, int|array|null $ignoreRequestId = null): string
    {
        $duration ??= $this->durationForService($serviceId);

        $hasOpenSlot = collect($this->slotsFor($partnerId, $date, $serviceId, $duration, $ignoreRequestId))
            ->contains(fn ($slot) => $slot['available']);

        if (!$hasOpenSlot) {
            return 'unavailable';
        }

        $windowMinutes = 0;
        foreach ($this->windowsFor($partnerId, $date) as $window) {
            if ($window['booked']) {
                continue;
            }
            $from = $date->copy()->setTimeFromTimeString($window['start']);
            $to = $date->copy()->setTimeFromTimeString($window['end']);
            $windowMinutes += max(0, $from->diffInMinutes($to));
        }

        $bookedMinutes = 0;
        foreach ($this->dayBookings($partnerId, $date, $ignoreRequestId) as $booking) {
            $bookedMinutes += max(0, $booking['start']->diffInMinutes($booking['end']));
        }

        if ($windowMinutes > 0 && ($bookedMinutes / $windowMinutes) >= 0.7) {
            return 'limited';
        }

        return 'available';
    }

    /**
     * Parse a booking-style datetime (`Y-m-d H:i` or ISO) into a Carbon.
     */
    public function parse(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return Collection<int, int>
     */
    public function blockingStatuses(): Collection
    {
        return collect(self::BLOCKING_STATUSES);
    }

    /**
     * Normalise the "ignore these requests" argument used by the availability
     * queries. Accepts null, a single id or a list of ids.
     *
     * @param int|array<int,int>|null $ignoreRequestId
     * @return array<int, int>
     */
    private function normalizeIgnoreIds(int|array|null $ignoreRequestId): array
    {
        if ($ignoreRequestId === null) {
            return [];
        }

        $ids = is_array($ignoreRequestId) ? $ignoreRequestId : [$ignoreRequestId];

        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn (int $id) => $id > 0
        )));
    }

    private function result(bool $available, ?string $reason, Carbon $start, Carbon $end, int $duration): array
    {
        return [
            'available' => $available,
            'reason' => $reason,
            'status' => $available ? 'available' : 'unavailable',
            'duration' => $duration,
            'start' => $start,
            'end' => $end,
        ];
    }
}
