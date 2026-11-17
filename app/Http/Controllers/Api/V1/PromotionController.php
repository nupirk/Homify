<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Services\PromoException;
use App\Services\PromoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PromotionController extends Controller
{
    /**
     * Preview/validate a promo code against the gross order subtotal.
     * Lets checkout show the discount before the booking is created.
     */
    public function validatePromo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:20',
            'subtotal' => 'required|numeric|min:0',
        ]);

        try {
            $result = PromoService::validate(
                $request->user(),
                $data['code'],
                (float) $data['subtotal']
            );
        } catch (PromoException $e) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $promotion = $result['promotion'];

        return response()->json([
            'success' => true,
            'valid' => true,
            'discount_amount' => $result['discount'],
            'promotion' => [
                'promotion_id' => $promotion->promotion_id,
                'promo_code' => $promotion->promo_code,
                'title' => $promotion->title,
                'description' => $promotion->description,
                'discount_percent' => $promotion->discount_percent !== null ? (float) $promotion->discount_percent : null,
                'discount_amount' => $promotion->discount_amount !== null ? (float) $promotion->discount_amount : null,
                'max_discount' => $promotion->max_discount !== null ? (float) $promotion->max_discount : null,
            ],
        ]);
    }

    /**
     * Public: promo codes customers can currently claim - active, inside
     * their window and not yet exhausted.
     */
    public function active(Request $request): JsonResponse
    {
        $now = now();

        // Personalise the public feed when a token is present: a code this
        // customer already redeemed must not be advertised as available, or
        // every tap on it ends in "you have already used this promo code".
        // Guests (no token) still get the full list - the route stays public.
        $user = $request->user() ?? auth('sanctum')->user();
        $usedPromotionIds = $user
            ? DB::table('user_promotions')
                ->where('user_id', $user->user_id)
                ->where('used', true)
                ->pluck('promotion_id')
                ->all()
            : [];

        $promotions = Promotion::query()
            ->when($usedPromotionIds !== [], fn ($query) => $query->whereNotIn('promotion_id', $usedPromotionIds))
            ->where('is_active', true)
            ->where('valid_from', '<=', $now)
            ->where(function ($query) use ($now) {
                $query->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
            })
            // 0 means unlimited (see PromoService::validate); only a positive
            // cap can be exhausted.
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhere('usage_limit', 0)
                    ->orWhereColumn('usage_count', '<', 'usage_limit');
            })
            ->orderBy('promotion_id')
            ->get()
            ->map(fn (Promotion $promotion) => $this->presentPublic($promotion))
            ->values();

        return response()->json([
            'success' => true,
            'data' => ['promotions' => $promotions],
        ]);
    }

    /**
     * Admin: every promotion, including usage counters.
     */
    public function adminIndex(): JsonResponse
    {
        $promotions = Promotion::query()
            ->orderBy('promotion_id')
            ->get()
            ->map(fn (Promotion $promotion) => $this->presentAdmin($promotion))
            ->values();

        return response()->json([
            'success' => true,
            'data' => ['promotions' => $promotions],
        ]);
    }

    /**
     * Admin: create a promo code (exactly one discount channel).
     */
    public function store(Request $request): JsonResponse
    {
        $promotion = Promotion::create($this->validated($request));

        return response()->json([
            'success' => true,
            'message' => 'Promotion created successfully',
            'data' => ['promotion' => $this->presentAdmin($promotion)],
        ], 201);
    }

    /**
     * Admin: update a promo code with the same rules as creation.
     */
    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        $promotion->update($this->validated($request, $promotion));

        return response()->json([
            'success' => true,
            'message' => 'Promotion updated successfully',
            'data' => ['promotion' => $this->presentAdmin($promotion->fresh())],
        ]);
    }

    /**
     * Shared create/update rules. Codes are stored uppercase because the
     * promo engine matches them case-insensitively.
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
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
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
                'discount_percent' => 'Provide exactly one of discount_percent or discount_amount.',
                'discount_amount' => 'Provide exactly one of discount_percent or discount_amount.',
            ]);
        }

        // promo_type follows the provided channel; the other one stays empty
        // so a row never carries two competing discounts.
        $data['promo_type'] = $hasPercent ? 'percentage' : 'fixed_amount';
        $data['discount_percent'] = $hasPercent ? $data['discount_percent'] : null;
        $data['discount_amount'] = $hasAmount ? $data['discount_amount'] : null;

        return $data;
    }

    /**
     * Public projection of a promo code (same shape as validatePromo).
     */
    private function presentPublic(Promotion $promotion): array
    {
        return [
            'promotion_id' => $promotion->promotion_id,
            'promo_code' => $promotion->promo_code,
            'title' => $promotion->title,
            'description' => $promotion->description,
            'discount_percent' => $promotion->discount_percent !== null ? (float) $promotion->discount_percent : null,
            'discount_amount' => $promotion->discount_amount !== null ? (float) $promotion->discount_amount : null,
            'max_discount' => $promotion->max_discount !== null ? (float) $promotion->max_discount : null,
            'min_order_value' => $promotion->min_order_value !== null ? (float) $promotion->min_order_value : null,
            'promo_type' => $promotion->promo_type,
            'valid_until' => $promotion->valid_until,
        ];
    }

    /**
     * Admin projection: everything the dashboard manages, usage included.
     */
    private function presentAdmin(Promotion $promotion): array
    {
        return [
            'promotion_id' => $promotion->promotion_id,
            'promo_code' => $promotion->promo_code,
            'title' => $promotion->title,
            'description' => $promotion->description,
            'discount_percent' => $promotion->discount_percent !== null ? (float) $promotion->discount_percent : null,
            'discount_amount' => $promotion->discount_amount !== null ? (float) $promotion->discount_amount : null,
            'min_order_value' => $promotion->min_order_value !== null ? (float) $promotion->min_order_value : null,
            'max_discount' => $promotion->max_discount !== null ? (float) $promotion->max_discount : null,
            'usage_limit' => $promotion->usage_limit !== null ? (int) $promotion->usage_limit : null,
            'usage_count' => (int) $promotion->usage_count,
            'promo_type' => $promotion->promo_type,
            'valid_from' => $promotion->valid_from,
            'valid_until' => $promotion->valid_until,
            'is_active' => (bool) $promotion->is_active,
        ];
    }
}
