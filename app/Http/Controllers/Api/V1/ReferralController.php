<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReferralException;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    /**
     * Invite & Earn snapshot: my code, stats, active promo codes.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
        ] + ReferralService::info($request->user()));
    }

    /**
     * Redeem a friend's invite code (once, ever, per account).
     */
    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:12',
        ]);

        try {
            $info = ReferralService::applyCode($request->user(), $data['code']);
        } catch (ReferralException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Invite code applied! Your welcome promo is ready to use.',
        ] + $info);
    }
}
