<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentMethodController extends Controller
{
    /**
     * Get all payment methods for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $paymentMethods = PaymentMethod::where('user_id', $user->user_id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($method) {
                return [
                    'id' => $method->payment_method_id,
                    'type' => $method->method_type,
                    'name' => $method->method_display_name,
                    'details' => $method->decrypted_details, // Auto-decrypted
                    'is_default' => $method->is_default,
                    'created_at' => $method->created_at->toISOString(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $paymentMethods
        ]);
    }

    /**
     * Store a new payment method
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:bank_transfer,esewa,khalti,connectips',
            'name' => 'required|string|max:100',
            'details' => 'required|array',
            'is_default' => 'boolean'
        ]);

        // Validate payment method specific details
        $type = $request->input('type');
        $details = $request->input('details', []);

        switch ($type) {
            case 'bank_transfer':
                $detailsValidator = Validator::make($details, [
                    'account_number' => 'required|string|max:50',
                    'account_name' => 'required|string|max:100',
                    'bank_name' => 'required|string|max:100',
                    // Optional in the partner app's form, so it cannot be
                    // required here - that mismatch rejected a valid bank
                    // account with "The branch field is required."
                    'branch' => 'nullable|string|max:100',
                    'ifsc_code' => 'nullable|string|max:20'
                ]);
                break;

            case 'esewa':
                $detailsValidator = Validator::make($details, [
                    'esewa_number' => 'required|string|max:20',
                    'esewa_name' => 'required|string|max:100'
                ]);
                break;

            case 'khalti':
                $detailsValidator = Validator::make($details, [
                    'khalti_number' => 'required|string|max:20',
                    'khalti_name' => 'required|string|max:100'
                ]);
                break;

            case 'connectips':
                $detailsValidator = Validator::make($details, [
                    'account_number' => 'required|string|max:50',
                    'account_name' => 'required|string|max:100',
                    'bank_code' => 'required|string|max:10',
                    'bank_name' => 'required|string|max:100'
                ]);
                break;

            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid payment method type'
                ], 422);
        }

        if ($validator->fails() || $detailsValidator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => array_merge(
                    $validator->errors()->toArray(),
                    $detailsValidator->errors()->toArray()
                )
            ], 422);
        }

        $user = $request->user();

        try {
            DB::beginTransaction();

            // If this is set as default, unset other defaults
            if ($request->boolean('is_default', false)) {
                PaymentMethod::where('user_id', $user->user_id)
                    ->update(['is_default' => false]);
            }

            // Check if this is the first payment method (auto-default)
            $isFirstMethod = !PaymentMethod::where('user_id', $user->user_id)->exists();

            // Create payment method with encrypted details
            $paymentMethod = PaymentMethod::create([
                'user_id' => $user->user_id,
                'provider' => $type === 'bank_transfer' ? 'bank' : $type,
                'method_type' => $type,
                'method_display_name' => $request->input('name'),
                'encrypted_details' => $details, // Auto-encrypted by cast
                'is_default' => $request->boolean('is_default', $isFirstMethod)
            ]);

            DB::commit();

            Log::info('Payment method created successfully', [
                'payment_method_id' => $paymentMethod->payment_method_id,
                'user_id' => $user->user_id,
                'type' => $type
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment method added successfully',
                'data' => [
                    'id' => $paymentMethod->payment_method_id,
                    'type' => $paymentMethod->method_type,
                    'name' => $paymentMethod->method_display_name,
                    'details' => $paymentMethod->decrypted_details,
                    'is_default' => $paymentMethod->is_default,
                    'created_at' => $paymentMethod->created_at->toISOString(),
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create payment method', [
                'user_id' => $user->user_id,
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add payment method. Please try again.'
            ], 500);
        }
    }

    /**
     * Update an existing payment method
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $paymentMethod = PaymentMethod::where('payment_method_id', $id)
            ->where('user_id', $user->user_id)
            ->first();

        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'details' => 'sometimes|required|array',
            'is_default' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // If setting as default, unset other defaults
            if ($request->boolean('is_default', false)) {
                PaymentMethod::where('user_id', $user->user_id)
                    ->where('payment_method_id', '!=', $id)
                    ->update(['is_default' => false]);
            }

            // Update payment method
            $updateData = [];
            if ($request->has('name')) {
                $updateData['method_display_name'] = $request->input('name');
            }
            if ($request->has('details')) {
                $updateData['encrypted_details'] = $request->input('details');
            }
            if ($request->has('is_default')) {
                $updateData['is_default'] = $request->boolean('is_default');
            }

            $paymentMethod->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment method updated successfully',
                'data' => [
                    'id' => $paymentMethod->payment_method_id,
                    'type' => $paymentMethod->method_type,
                    'name' => $paymentMethod->method_display_name,
                    'details' => $paymentMethod->decrypted_details,
                    'is_default' => $paymentMethod->is_default,
                    'created_at' => $paymentMethod->created_at->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update payment method', [
                'payment_method_id' => $id,
                'user_id' => $user->user_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment method. Please try again.'
            ], 500);
        }
    }

    /**
     * Set a payment method as default
     */
    public function setDefault(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $paymentMethod = PaymentMethod::where('payment_method_id', $id)
            ->where('user_id', $user->user_id)
            ->first();

        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Unset all other defaults
            PaymentMethod::where('user_id', $user->user_id)
                ->update(['is_default' => false]);

            // Set this as default
            $paymentMethod->update(['is_default' => true]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Default payment method updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to set default payment method', [
                'payment_method_id' => $id,
                'user_id' => $user->user_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update default payment method. Please try again.'
            ], 500);
        }
    }

    /**
     * Delete a payment method
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $paymentMethod = PaymentMethod::where('payment_method_id', $id)
            ->where('user_id', $user->user_id)
            ->first();

        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $wasDefault = $paymentMethod->is_default;
            $paymentMethod->delete();

            // If deleted method was default, set another as default
            if ($wasDefault) {
                $nextMethod = PaymentMethod::where('user_id', $user->user_id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($nextMethod) {
                    $nextMethod->update(['is_default' => true]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment method deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete payment method', [
                'payment_method_id' => $id,
                'user_id' => $user->user_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payment method. Please try again.'
            ], 500);
        }
    }
}
