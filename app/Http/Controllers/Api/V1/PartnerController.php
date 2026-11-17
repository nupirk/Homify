<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PartnerResource;
use App\Models\PartnerProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class PartnerController extends Controller
{
    public function topRated(Request $request): JsonResponse
    {
        // Get top-rated partners, order by average rating and limit to 10
        $partners = PartnerProfile::with('user')
            ->where('status', 'active') // Only show active partners
            ->orderByDesc('rating_avg')
            ->limit(10)
            ->get();

        $data = $partners->map(function ($partner) {
            return [
                'id' => $partner->partner_id,
                'user' => [
                    'first_name' => $partner->user->first_name,
                    'last_name' => $partner->user->last_name,
                    'avatar_url' => $partner->user->avatar_url,
                ],
                'rating_avg' => $partner->rating_avg,
                'total_ratings' => $partner->total_ratings,
                'bio' => $partner->bio,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $partnerProfile = $request->user()->partnerProfile;

        if (!$partnerProfile) {
            // Create partner profile if it doesn't exist for partner users
            if ($request->user()->user_type === 'partner') {
                $partnerProfile = PartnerProfile::create([
                    'partner_id' => $request->user()->user_id,
                    'status' => 'onboarding',
                    'rating_avg' => null,
                    'total_ratings' => 0,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Partner profile not found'
                ], 404);
            }
        }

        return response()->json([
            'success' => true,
            'data' => new PartnerResource($partnerProfile->load(['user', 'services', 'earnings']))
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($request->user()->user_id, 'user_id')],
            'business_name' => 'nullable|string|max:100',
            'business_address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'bio' => 'nullable|string|max:500',
            'experience_years' => 'nullable|integer|min:0|max:100',
            'hourly_rate' => 'nullable|numeric|min:0',
            'status' => ['nullable', Rule::in(['onboarding', 'active', 'inactive', 'suspended'])],
        ]);

        $partnerProfile = $request->user()->partnerProfile;

        if (!$partnerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Partner profile not found'
            ], 404);
        }

        // Persist user-level fields on the users row
        $userFields = $request->only(['first_name', 'last_name', 'phone']);
        $userFields = array_filter($userFields, fn ($value) => !is_null($value));
        if (!empty($userFields)) {
            $request->user()->update($userFields);
        }

        $partnerProfile->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Partner profile updated successfully',
            'data' => new PartnerResource($partnerProfile->fresh()->load(['user', 'services', 'earnings']))
        ]);
    }
}
