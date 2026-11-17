<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;
use App\Services\MembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    /**
     * Plans + the caller's current membership. The app renders the plan
     * cards and the checkout discount line from this payload.
     */
    public function index(Request $request): JsonResponse
    {
        $plans = MembershipPlan::where('is_active', true)
            ->orderBy('price')
            ->get()
            ->map(fn (MembershipPlan $plan) => [
                'plan_id' => $plan->id,
                'code' => $plan->code,
                'name' => $plan->name,
                'description' => $plan->description,
                'price' => (float) $plan->price,
                'duration_days' => $plan->duration_days,
                'perks' => $plan->perks ?? [],
            ]);

        return response()->json([
            'success' => true,
            'plans' => $plans,
            'membership' => MembershipService::statusFor($request->user()),
        ]);
    }

    /**
     * Start an eSewa purchase: creates the pending membership attempt and
     * returns the signed form payload the app posts via the WebView.
     */
    public function purchase(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => 'required|integer|exists:membership_plans,id',
        ]);

        $plan = MembershipPlan::where('is_active', true)->find($data['plan_id']);

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'That plan is not available.',
            ], 422);
        }

        if (MembershipService::activeFor($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active membership.',
            ], 422);
        }

        return response()->json(MembershipService::initiateEsewa($request->user(), $plan));
    }
}
