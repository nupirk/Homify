<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PartnerAvailability;
use App\Models\PartnerProfile;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Partner self-service working hours.
 *
 * `AvailabilityService` reads `partner_availability` rows as the partner's
 * working windows for a date (a missing row means the 09:00-18:00 platform
 * default). Before this controller there was no way for a partner to write
 * those rows, so every partner was stuck on the default window forever.
 */
class PartnerAvailabilityController extends Controller
{
    public function __construct(private AvailabilityService $availability)
    {
    }

    /** The next N days (default 14) with the partner's effective windows. */
    public function index(Request $request): JsonResponse
    {
        $partner = $this->partnerProfile($request);

        if (! $partner) {
            return $this->notAPartner();
        }

        $days = (int) $request->query('days', 14);
        $days = max(1, min(30, $days));

        $payload = [];

        for ($i = 0; $i < $days; $i++) {
            $payload[] = $this->dayPayload($partner->partner_id, Carbon::today()->addDays($i));
        }

        return response()->json([
            'success' => true,
            'data' => ['days' => $payload],
        ]);
    }

    /**
     * Replace the windows for the submitted dates.
     *
     * Rows flagged `booked` are left alone: they mirror a job already sitting
     * on that day, and the availability engine counts the job separately.
     */
    public function update(Request $request): JsonResponse
    {
        $partner = $this->partnerProfile($request);

        if (! $partner) {
            return $this->notAPartner();
        }

        $validated = $request->validate([
            'days' => 'required|array|min:1|max:60',
            'days.*.date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'days.*.is_available' => 'required|boolean',
            'days.*.start_time' => 'nullable|date_format:H:i',
            'days.*.end_time' => 'nullable|date_format:H:i',
        ]);

        $normalised = [];

        foreach ($validated['days'] as $index => $day) {
            $start = $day['start_time'] ?? AvailabilityService::DEFAULT_START;
            $end = $day['end_time'] ?? AvailabilityService::DEFAULT_END;

            if ($day['is_available'] && $end <= $start) {
                return response()->json([
                    'success' => false,
                    'message' => "The end time for {$day['date']} must be after the start time.",
                    'errors' => ["days.{$index}.end_time" => ['End time must be after the start time.']],
                ], 422);
            }

            $normalised[$day['date']] = [
                'is_available' => (bool) $day['is_available'],
                'start_time' => $start,
                'end_time' => $end,
            ];
        }

        DB::transaction(function () use ($partner, $normalised) {
            foreach ($normalised as $date => $window) {
                PartnerAvailability::where('partner_id', $partner->partner_id)
                    ->where('service_date', $date)
                    ->where('booked', false)
                    ->delete();

                if (! $window['is_available']) {
                    // A closed day still needs a row: no row means "platform
                    // default window", which would re-open the day.
                    PartnerAvailability::create([
                        'partner_id' => $partner->partner_id,
                        'service_date' => $date,
                        'start_time' => '00:00',
                        'end_time' => '00:00',
                        'slot_length_min' => AvailabilityService::SLOT_STEP_MIN * 2,
                        'booked' => false,
                    ]);
                    continue;
                }

                $alreadyOpen = PartnerAvailability::where('partner_id', $partner->partner_id)
                    ->where('service_date', $date)
                    ->where('start_time', $window['start_time'] . ':00')
                    ->exists();

                if ($alreadyOpen) {
                    continue;
                }

                PartnerAvailability::create([
                    'partner_id' => $partner->partner_id,
                    'service_date' => $date,
                    'start_time' => $window['start_time'],
                    'end_time' => $window['end_time'],
                    'slot_length_min' => AvailabilityService::SLOT_STEP_MIN * 2,
                    'booked' => false,
                ]);
            }
        });

        $payload = [];

        foreach (array_keys($normalised) as $date) {
            $payload[] = $this->dayPayload($partner->partner_id, Carbon::parse($date));
        }

        return response()->json([
            'success' => true,
            'message' => 'Availability updated',
            'data' => ['days' => $payload],
        ]);
    }

    private function dayPayload(int $partnerId, Carbon $date): array
    {
        $rows = PartnerAvailability::where('partner_id', $partnerId)
            ->where('service_date', $date->toDateString())
            ->orderBy('start_time')
            ->get();

        $windows = $rows->isEmpty()
            ? [[
                'start' => AvailabilityService::DEFAULT_START,
                'end' => AvailabilityService::DEFAULT_END,
                'booked' => false,
            ]]
            : $rows->map(fn (PartnerAvailability $row) => [
                'start' => substr($row->start_time->format('H:i'), 0, 5),
                'end' => substr($row->end_time->format('H:i'), 0, 5),
                'booked' => (bool) $row->booked,
            ])->all();

        // Closed days are stored as a zero-length window (00:00-00:00).
        $open = array_values(array_filter(
            $windows,
            fn ($window) => ! $window['booked'] && $window['end'] > $window['start']
        ));

        return [
            'date' => $date->toDateString(),
            // A day with no rows still counts as open: it falls back to the
            // platform default window, which is what booking actually uses.
            'is_available' => $open !== [],
            'start_time' => $open[0]['start'] ?? null,
            'end_time' => $open[0]['end'] ?? null,
            'windows' => $windows,
            'is_default' => $rows->isEmpty(),
            'booked_count' => $rows->where('booked', true)->count(),
        ];
    }

    private function partnerProfile(Request $request): ?PartnerProfile
    {
        $user = $request->user();

        if (! $user || $user->user_type !== 'partner') {
            return null;
        }

        return $user->partnerProfile
            ?? PartnerProfile::firstOrCreate(
                ['partner_id' => $user->user_id],
                ['status' => 'onboarding', 'rating_avg' => null, 'total_ratings' => 0]
            );
    }

    private function notAPartner(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Only partners can manage their availability',
        ], 403);
    }
}
