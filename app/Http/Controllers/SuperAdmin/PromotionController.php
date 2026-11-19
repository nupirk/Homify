<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Web admin for discount offers (promo codes).
 *
 * The JSON API version lives in Api\V1\PromotionController and is what the
 * mobile app management screen uses. This controller mirrors the same rules so
 * the dashboard can manage promo codes without going through the API.
 */
class PromotionController extends Controller
{
    /**
     * List every promo code with usage and validity state.
     */
    public function index(): View
    {
        $promotions = Promotion::with('service')->orderByDesc('created_at')->paginate(20);

        $now = now();
        $stats = [
            'total' => Promotion::count(),
            'active' => Promotion::where('is_active', true)
                ->where('valid_from', '<=', $now)
                ->where('valid_until', '>=', $now)
                ->count(),
            'scheduled' => Promotion::where('is_active', true)
                ->where('valid_from', '>', $now)
                ->count(),
            'expired' => Promotion::where('valid_until', '<', $now)->count(),
        ];

        // The form can restrict a code to one service; empty means every one.
        $services = Service::orderBy('name')->get(['service_id', 'name']);

        return view('admin.promotions.index', compact('promotions', 'stats', 'services'));
    }

    /**
     * Create a discount offer.
     */
    public function store(Request $request): RedirectResponse
    {
        Promotion::create($this->validated($request));

        return redirect()
            ->route('admin.promotions.index')
            ->with('success', 'Discount offer created successfully.');
    }

    /**
     * Update a discount offer.
     */
    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $promotion->update($this->validated($request, $promotion));

        return redirect()
            ->route('admin.promotions.index')
            ->with('success', 'Discount offer updated successfully.');
    }

    /**
     * Activate / deactivate a discount offer without deleting it.
     */
    public function toggle(Promotion $promotion): RedirectResponse
    {
        $promotion->update(['is_active' => !$promotion->is_active]);

        return redirect()
            ->route('admin.promotions.index')
            ->with(
                'success',
                $promotion->is_active
                    ? "Promo code {$promotion->promo_code} is now active."
                    : "Promo code {$promotion->promo_code} has been deactivated."
            );
    }

    /**
     * Shared create/update rules. Kept in sync with
     * Api\V1\PromotionController::validated(): codes are stored uppercase
     * because the promo engine matches them case-insensitively, and exactly one
     * discount channel (percent or fixed amount) must be provided.
     */
    private function validated(Request $request, ?Promotion $promotion = null): array
    {
        if ($request->has('promo_code')) {
            $request->merge(['promo_code' => strtoupper(trim((string) $request->input('promo_code')))]);
        }

        $data = $request->validate([
            'promo_code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('promotions', 'promo_code')->ignore($promotion?->promotion_id, 'promotion_id'),
            ],
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'discount_percent' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'gt:0'],
            'service_id' => ['nullable', 'integer', 'exists:services,service_id'],
            'min_order_value' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:0'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after:valid_from'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $hasPercent = $request->filled('discount_percent');
        $hasAmount = $request->filled('discount_amount');

        if ($hasPercent === $hasAmount) {
            throw ValidationException::withMessages([
                'discount_percent' => 'Provide exactly one of discount percent or discount amount.',
                'discount_amount' => 'Provide exactly one of discount percent or discount amount.',
            ]);
        }

        $data['promo_type'] = $hasPercent ? 'percentage' : 'fixed_amount';
        $data['discount_percent'] = $hasPercent ? $data['discount_percent'] : null;
        $data['discount_amount'] = $hasAmount ? $data['discount_amount'] : null;
        $data['is_active'] = $request->boolean('is_active');
        // Empty selection means "applies to every service".
        $data['service_id'] = $request->input('service_id') ? (int) $request->input('service_id') : null;

        return $data;
    }
}
