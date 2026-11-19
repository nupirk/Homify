<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Support\SchemaCompat;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    /**
     * Display a listing of clients (customers).
     */
    public function index(Request $request): View
    {
        $query = User::where('user_type', 'customer')
            ->with(['orders', 'ratings']);

        // Filter by status
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Filter by email verification
        if ($request->filled('verified')) {
            if ($request->verified === 'yes') {
                $query->whereNotNull('email_verified_at');
            } elseif ($request->verified === 'no') {
                $query->whereNull('email_verified_at');
            }
        }

        // Search by name, email, or phone
        if ($request->filled('search')) {
            $searchTerm = '%' . $request->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", [$searchTerm])
                    ->orWhere('email', SchemaCompat::likeOperator(), $searchTerm)
                    ->orWhere('phone', SchemaCompat::likeOperator(), $searchTerm);
            });
        }

        // Sort options
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        $query->orderBy($sortBy, $sortOrder);

        $clients = $query->paginate(15)->withQueryString();

        // Statistics
        $stats = [
            'total_customers' => User::where('user_type', 'customer')->count(),
            'active_customers' => User::where('user_type', 'customer')->where('is_active', true)->count(),
            'verified_customers' => User::where('user_type', 'customer')->whereNotNull('email_verified_at')->count(),
            'new_this_month' => User::where('user_type', 'customer')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        return view('admin.clients.index', compact('clients', 'stats'));
    }

    /**
     * Display the specified user.
     */
    public function show(User $client): View
    {
        // Basic stats calculation
        $stats = [
            'total_orders' => $client->orders()->count(),
            'completed_orders' => $client->orders()->where('status', 'completed')->count(),
            'cancelled_orders' => $client->orders()->where('status', 'cancelled')->count(),
            'total_spent' => $client->orders()->where('status', 'completed')->sum('total_amount'),
            'reviews_given' => $client->ratings()->count(),
            'average_rating_given' => $client->ratings()->avg('rating_value') ?? 0,
        ];

        // Recent activity (simplified)
        $recentOrders = $client->orders()
            ->with(['orderItems.service'])
            ->latest()
            ->take(5)
            ->get();

        $recentRatings = $client->ratings()
            ->with(['order'])
            ->latest()
            ->take(3)
            ->get();

        return view('admin.clients.show', compact('client', 'stats', 'recentOrders', 'recentRatings'));
    }

    /**
     * Show the form for editing the user.
     */
    public function edit(User $client): View
    {
        return view('admin.clients.edit', compact('client'));
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $client): RedirectResponse
    {
        try {
            $request->validate([
                'first_name' => 'required|string|max:50',
                'last_name' => 'required|string|max:50',
                'email' => ['required', 'email', Rule::unique('clients')->ignore($client->user_id, 'user_id')],
                'phone' => ['required', 'string', Rule::unique('clients')->ignore($client->user_id, 'user_id')],
                'password' => 'nullable|string|min:8|confirmed',
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'is_active' => 'boolean',
            ]);

            // Update user data
            $userData = [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'is_active' => $request->boolean('is_active'),
            ];

            if ($request->filled('password')) {
                $userData['password'] = Hash::make($request->password);
            }

            // Handle avatar upload
            if ($request->hasFile('avatar')) {
                $userData['avatar_url'] = '/storage/' . $request->file('avatar')->store('clients/avatars', 'public');
            }

            $client->update($userData);

            return redirect()->route('admin.clients.show', $client)
                ->with('success', 'Customer "' . $client->full_name . '" updated successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to update customer. Please try again.')
                ->withInput();
        }
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $client): RedirectResponse
    {
        try {
            // Check if user has active orders
            $activeOrders = $client->orders()
                ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
                ->count();

            if ($activeOrders > 0) {
                return back()->with('error', 'Cannot delete customer "' . $client->full_name . '" because they have ' . $activeOrders . ' active order(s).');
            }

            $userName = $client->full_name;
            $client->delete();

            return redirect()->route('admin.clients.index')
                ->with('success', 'Customer "' . $userName . '" deleted successfully!');
        } catch (\Exception $e) {
            return redirect()->route('admin.clients.index')
                ->with('error', 'Failed to delete customer. Please try again.');
        }
    }

    /**
     * Toggle user status.
     */
    public function toggleStatus(User $client): RedirectResponse
    {
        $client->update(['is_active' => !$client->is_active]);

        $status = $client->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Customer {$status} successfully!");
    }

    /**
     * Verify user email.
     */
    public function verifyEmail(User $client): RedirectResponse
    {
        if ($client->email_verified_at) {
            return back()->with('info', 'Email is already verified.');
        }

        $client->update(['email_verified_at' => now()]);

        return back()->with('success', 'Email verified successfully!');
    }

    /**
     * Get clients statistics.
     */
    /**
     * The sidebar has no statistics page, so this answers JSON like the other
     * admin statistics endpoints instead of rendering a view that never existed.
     */
    public function statistics(): \Illuminate\Http\JsonResponse
    {
        $stats = [
            'total_customers' => User::where('user_type', 'customer')->count(),
            'active_customers' => User::where('user_type', 'customer')->where('is_active', true)->count(),
            'verified_customers' => User::where('user_type', 'customer')->whereNotNull('email_verified_at')->count(),
            'top_customers' => User::where('user_type', 'customer')
                ->withCount('orders')
                ->orderByDesc('orders_count')
                ->take(10)
                ->get(),
            'recent_registrations' => User::where('user_type', 'customer')
                ->latest()
                ->take(10)
                ->get(),
            'monthly_registrations' => User::where('user_type', 'customer')
                ->selectRaw('DATE_TRUNC(\'month\', created_at) as month, COUNT(*) as count')
                ->whereYear('created_at', now()->year)
                ->groupBy('month')
                ->orderBy('month')
                ->get(),
        ];

        return response()->json(['success' => true, 'data' => $stats]);
    }
}
