<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = Address::where('user_id', $request->user()->user_id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => AddressResource::collection($addresses)
        ]);
    }

    public function store(AddressRequest $request): JsonResponse
    {
        $userId = $request->user()->user_id;

        // If this is being set as default, unset other defaults
        if ($request->is_default) {
            Address::where('user_id', $userId)->update(['is_default' => false]);
        }

        // If this is the user's first address, make it default
        $isFirstAddress = Address::where('user_id', $userId)->count() === 0;

        $address = Address::create([
            'user_id' => $userId,
            'address_line_1' => $request->address_line_1,
            'address_line_2' => $request->address_line_2,
            'city' => $request->city,
            'state' => $request->state,
            'postal_code' => $request->postal_code,
            'country' => $request->country ?? 'Nepal',
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'address_type' => $request->address_type ?? 'home',
            'is_default' => $request->is_default || $isFirstAddress,
        ]);

        return response()->json([
            'success' => true,
            'data' => new AddressResource($address),
            'message' => 'Address created successfully'
        ], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $address = Address::where('user_id', $request->user()->user_id)
            ->where('address_id', $id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new AddressResource($address)
        ]);
    }

    public function update(AddressRequest $request, $id): JsonResponse
    {
        $userId = $request->user()->user_id;

        $address = Address::where('user_id', $userId)
            ->where('address_id', $id)
            ->firstOrFail();

        // If this is being set as default, unset other defaults
        if ($request->is_default && !$address->is_default) {
            Address::where('user_id', $userId)->update(['is_default' => false]);
        }

        $address->update([
            'address_line_1' => $request->address_line_1,
            'address_line_2' => $request->address_line_2,
            'city' => $request->city,
            'state' => $request->state,
            'postal_code' => $request->postal_code,
            'country' => $request->country ?? 'Nepal',
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'address_type' => $request->address_type ?? 'home',
            'is_default' => $request->is_default ?? $address->is_default,
        ]);

        return response()->json([
            'success' => true,
            'data' => new AddressResource($address),
            'message' => 'Address updated successfully'
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $address = Address::where('user_id', $request->user()->user_id)
            ->where('address_id', $id)
            ->firstOrFail();

        $wasDefault = $address->is_default;
        $address->delete();

        // If we deleted the default address, set another one as default
        if ($wasDefault) {
            $newDefault = Address::where('user_id', $request->user()->user_id)
                ->first();
            if ($newDefault) {
                $newDefault->update(['is_default' => true]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully'
        ]);
    }

    public function setDefault(Request $request, $id): JsonResponse
    {
        $userId = $request->user()->user_id;

        // Unset all defaults
        Address::where('user_id', $userId)->update(['is_default' => false]);

        // Set this address as default
        $address = Address::where('user_id', $userId)
            ->where('address_id', $id)
            ->firstOrFail();

        $address->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'data' => new AddressResource($address),
            'message' => 'Default address updated successfully'
        ]);
    }

    public function getDefault(Request $request): JsonResponse
    {
        $address = Address::where('user_id', $request->user()->user_id)
            ->where('is_default', true)
            ->first();

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'No default address found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new AddressResource($address)
        ]);
    }
}
