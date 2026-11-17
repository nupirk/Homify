<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\User;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BookingController extends Controller
{
    /**
     * Display a listing of bookings.
     */
    public function index(Request $request): View
    {
        $query = Booking::with(['customer', 'service', 'partner']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('scheduled_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('scheduled_at', '<=', $request->date_to);
        }

        // Filter by service
        if ($request->filled('service_id')) {
            $query->where('service_id', $request->service_id);
        }

        // Search by customer name
        if ($request->filled('search')) {
            $query->whereHas('customer', function ($q) use ($request) {
                $q->where('name', 'ILIKE', '%' . $request->search . '%');
            });
        }

        // Sort options
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $bookings = $query->paginate($request->get('per_page', 15));
        $services = Service::where('is_active', true)->orderBy('name')->get();

        $statusOptions = [
            'pending' => 'Pending',
            'assigned' => 'Assigned',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled'
        ];

        return view('admin.bookings.index', compact('bookings', 'services', 'statusOptions'));
    }

    /**
     * Display the specified booking.
     */
    public function show(Booking $booking): View
    {
        $booking->load(['customer', 'service.category', 'partner.partnerProfile', 'review']);
        return view('admin.bookings.show', compact('booking'));
    }

    /**
     * Show the form for editing the specified booking.
     */
    public function edit(Booking $booking): View
    {
        $booking->load(['customer', 'service', 'partner']);
        $partners = User::where('role', 'partner')
            ->whereHas('partnerProfile', function ($q) {
                $q->where('is_active', true);
            })
            ->orderBy('name')
            ->get();

        $statusOptions = [
            'pending' => 'Pending',
            'assigned' => 'Assigned',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled'
        ];

        return view('admin.bookings.edit', compact('booking', 'partners', 'statusOptions'));
    }

    /**
     * Update the specified booking in storage.
     */
    public function update(Request $request, Booking $booking): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'partner_id' => 'nullable|exists:users,id',
                'status' => 'required|in:pending,assigned,accepted,rejected,in_progress,completed,cancelled',
                'scheduled_at' => 'sometimes|required|date|after:now',
                'price_agreed' => 'sometimes|required|numeric|min:0|max:99999.99',
                'notes' => 'nullable|string|max:1000',
            ]);

            // Additional validation for partner assignment
            if ($validated['partner_id']) {
                $partner = User::where('id', $validated['partner_id'])
                    ->where('role', 'partner')
                    ->whereHas('partnerProfile', function ($q) {
                        $q->where('is_active', true);
                    })
                    ->first();

                if (!$partner) {
                    return redirect()->back()
                        ->with('error', 'Selected partner is not valid or not active.')
                        ->withInput();
                }
            }

            $booking->update($validated);

            return redirect()->route('admin.bookings.index')
                ->with('success', 'Booking #' . $booking->id . ' updated successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to update booking. Please try again.')
                ->withInput();
        }
    }

    /**
     * Assign a partner to the booking.
     */
    public function assignPartner(Request $request, Booking $booking): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'partner_id' => 'required|exists:users,id',
            ]);

            $partner = User::where('id', $validated['partner_id'])
                ->where('role', 'partner')
                ->whereHas('partnerProfile', function ($q) {
                    $q->where('is_active', true);
                })
                ->first();

            if (!$partner) {
                return redirect()->back()
                    ->with('error', 'Selected partner is not valid or not active.')
                    ->withInput();
            }

            $booking->update([
                'partner_id' => $validated['partner_id'],
                'status' => 'assigned'
            ]);

            return redirect()->route('admin.bookings.show', $booking)
                ->with('success', 'Partner "' . $partner->name . '" assigned to booking #' . $booking->id . ' successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to assign partner. Please try again.')
                ->withInput();
        }
    }

    /**
     * Cancel the booking.
     */
    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'cancellation_reason' => 'required|string|max:500',
            ]);

            if (in_array($booking->status, ['completed', 'cancelled'])) {
                return redirect()->back()
                    ->with('error', 'Cannot cancel booking #' . $booking->id . ' because it is already ' . $booking->status . '.');
            }

            $booking->update([
                'status' => 'cancelled',
                'cancellation_reason' => $validated['cancellation_reason']
            ]);

            return redirect()->route('admin.bookings.index')
                ->with('success', 'Booking #' . $booking->id . ' cancelled successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to cancel booking. Please try again.')
                ->withInput();
        }
    }

    /**
     * Get booking statistics.
     */
    public function statistics(): View
    {
        $stats = [
            'total_bookings' => Booking::count(),
            'pending_bookings' => Booking::where('status', 'pending')->count(),
            'in_progress_bookings' => Booking::where('status', 'in_progress')->count(),
            'completed_bookings' => Booking::where('status', 'completed')->count(),
            'cancelled_bookings' => Booking::where('status', 'cancelled')->count(),
            'today_bookings' => Booking::whereDate('created_at', today())->count(),
            'this_week_bookings' => Booking::whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count(),
            'this_month_bookings' => Booking::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'average_booking_value' => Booking::where('status', 'completed')->avg('price_agreed'),
            'total_revenue' => Booking::where('status', 'completed')->sum('price_agreed'),
        ];

        $recentBookings = Booking::with(['customer', 'service', 'partner'])
            ->latest()
            ->limit(10)
            ->get();

        $topServices = Service::withCount(['bookings' => function ($query) {
            $query->where('status', 'completed');
        }])
            ->having('bookings_count', '>', 0)
            ->orderByDesc('bookings_count')
            ->limit(5)
            ->get();

        return view('admin.bookings.statistics', compact('stats', 'recentBookings', 'topServices'));
    }
}
