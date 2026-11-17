<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ]
        ]);
    }

    public function unreadCount(Request $request)
    {
        $count = $request->user()
            ->notifications()
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => ['unread_count' => $count]
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $updated = $request->user()
            ->notifications()
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'data' => ['marked_count' => $updated]
        ]);
    }

    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->update([
            'is_read' => true,
            'read_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }

    public function registerDevice(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'device_token' => 'required|string',
                'platform' => 'required|in:ios,android,web',
                'device_model' => 'nullable|string|max:100',
                'os_version' => 'nullable|string|max:50',
                'app_version' => 'nullable|string|max:20',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            // If this token is already registered for a DIFFERENT user,
            // the device has switched accounts (same phone, different login).
            // Deactivate those stale rows so pushes stop going to the old account.
            UserDevice::where('device_token', $request->device_token)
                ->where('user_id', '!=', $user->user_id)
                ->update(['is_active' => false]);

            // Check if device already exists for this user and token
            $existingDevice = UserDevice::where('user_id', $user->user_id)
                ->where('device_token', $request->device_token)
                ->first();

            if ($existingDevice) {
                // Update existing device
                $existingDevice->update([
                    'platform' => $request->platform,
                    'device_model' => $request->device_model,
                    'os_version' => $request->os_version,
                    'app_version' => $request->app_version,
                    'is_active' => $request->get('is_active', true),
                    'last_used_at' => now()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Device updated successfully',
                    'data' => $existingDevice
                ]);
            }

            // Create new device
            $device = UserDevice::create([
                'user_id' => $user->user_id,
                'device_token' => $request->device_token,
                'platform' => $request->platform,
                'device_model' => $request->device_model,
                'os_version' => $request->os_version,
                'app_version' => $request->app_version,
                'is_active' => $request->get('is_active', true),
                'last_used_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Device registered successfully',
                'data' => $device
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to register device',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
