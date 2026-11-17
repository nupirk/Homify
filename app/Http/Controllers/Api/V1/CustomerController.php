<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function update(Request $request): UserResource
    {
        $request->user()->update($request->only(['first_name', 'last_name', 'phone', 'avatar_url']));
        return new UserResource($request->user()->fresh());
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
        ]);

        $user = $request->user();

        // Delete old avatar if exists (legacy private path or current public path)
        if ($user->avatar_url) {
            $legacyPath = str_replace('/api/v1/customer/avatar/', '', $user->avatar_url);
            $publicPath = str_replace('/storage/avatars/', '', $user->avatar_url);
            if (Storage::disk('local')->exists('avatars/' . $legacyPath)) {
                Storage::disk('local')->delete('avatars/' . $legacyPath);
            }
            if (Storage::disk('public')->exists('avatars/' . $publicPath)) {
                Storage::disk('public')->delete('avatars/' . $publicPath);
            }
        }

        // Store new avatar in public storage so <Image> can load it without auth
        $file = $request->file('avatar');
        $timestamp = now()->format('Y_m_d_H_i_s');
        $randomString = Str::random(16); // Increased randomness
        $extension = $file->getClientOriginalExtension();
        $filename = "avatar_{$user->user_id}_{$timestamp}_{$randomString}.{$extension}";

        // Store in public storage (served via /storage/avatars/...)
        $file->storeAs('avatars', $filename, 'public');

        // Public URL that works with plain <Image> components
        $avatarUrl = '/storage/avatars/' . $filename;

        // Update user
        $user->update(['avatar_url' => $avatarUrl]);

        return response()->json([
            'success' => true,
            'message' => 'Avatar uploaded successfully',
            'data' => [
                'avatar_url' => $avatarUrl,
                'user' => new UserResource($user->fresh())
            ]
        ]);
    }

    public function removeAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_url) {
            // Delete file (legacy private path or current public path)
            $legacyPath = str_replace('/api/v1/customer/avatar/', '', $user->avatar_url);
            $publicPath = str_replace('/storage/avatars/', '', $user->avatar_url);
            if (Storage::disk('local')->exists('avatars/' . $legacyPath)) {
                Storage::disk('local')->delete('avatars/' . $legacyPath);
            }
            if (Storage::disk('public')->exists('avatars/' . $publicPath)) {
                Storage::disk('public')->delete('avatars/' . $publicPath);
            }

            // Update user
            $user->update(['avatar_url' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Avatar removed successfully',
            'data' => [
                'user' => new UserResource($user->fresh())
            ]
        ]);
    }

    public function getAvatar(Request $request, $filename)
    {
        $user = $request->user();

        // Security check: Only allow users to access their own avatar
        if (!$user || !str_contains($filename, "avatar_{$user->user_id}_")) {
            abort(403, 'Unauthorized access to avatar');
        }

        $path = 'avatars/' . $filename;

        if (!Storage::disk('local')->exists($path)) {
            abort(404, 'Avatar not found');
        }

        $file = Storage::disk('local')->get($path);

        // Get mime type from file extension
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $mimeType = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            default => 'image/jpeg'
        };

        return response($file, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, max-age=3600', // Cache for 1 hour
            'Content-Disposition' => 'inline', // Display in browser, not download
        ]);
    }
}
