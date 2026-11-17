<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BannerController extends Controller
{
    /** Placement slots a banner may render in. */
    private const PLACEMENTS = ['home_mid', 'explore_top', 'cart_top'];

    /** Where tapping a banner navigates. */
    private const LINK_TYPES = ['none', 'service', 'category', 'url', 'promo'];

    /** How the creative is presented: inline slide, tall slide, or modal. */
    private const DISPLAY_STYLES = ['banner', 'poster', 'popup'];

    /**
     * Public feed: active banners inside their validity window, ordered.
     */
    public function index(): JsonResponse
    {
        $now = now();

        $banners = Banner::query()
            ->where('is_active', true)
            ->where('valid_from', '<=', $now)
            ->where(function ($query) use ($now) {
                $query->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
            })
            ->orderBy('sort_order')
            ->orderBy('banner_id')
            ->get()
            ->map(fn (Banner $banner) => [
                'banner_id' => $banner->banner_id,
                'title' => $banner->title,
                'subtitle' => $banner->subtitle,
                'image_url' => $banner->image_url,
                'placement' => $banner->placement,
                'display_style' => $banner->display_style ?? 'banner',
                'link_type' => $banner->link_type,
                'link_value' => $banner->link_value,
                'sort_order' => (int) $banner->sort_order,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => ['banners' => $banners],
        ]);
    }

    /**
     * Admin: every banner in display order.
     */
    public function adminIndex(): JsonResponse
    {
        $banners = Banner::query()
            ->orderBy('sort_order')
            ->orderBy('banner_id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['banners' => $banners],
        ]);
    }

    /**
     * Admin: create from multipart image (public disk) or JSON image_url.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            $data['image_url'] = $this->storeImage($request->file('image'));
        }

        $banner = Banner::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Banner created successfully',
            'data' => ['banner' => $banner],
        ], 201);
    }

    /**
     * Admin: full update. A new image file replaces the stored one; when no
     * image/image_url is sent the existing image is kept.
     */
    public function update(Request $request, Banner $banner): JsonResponse
    {
        $data = $this->validated($request);
        $previousImageUrl = $banner->image_url;

        if ($request->hasFile('image')) {
            $data['image_url'] = $this->storeImage($request->file('image'));
        }

        $banner->update($data);

        if ($previousImageUrl !== $banner->image_url) {
            $this->deleteImage($previousImageUrl);
        }

        return response()->json([
            'success' => true,
            'message' => 'Banner updated successfully',
            'data' => ['banner' => $banner->fresh()],
        ]);
    }

    /**
     * Admin: remove the row and the public file we own for it.
     */
    public function destroy(Banner $banner): JsonResponse
    {
        $bannerId = $banner->banner_id;
        $this->deleteImage($banner->image_url);
        $banner->delete();

        return response()->json([
            'success' => true,
            'message' => 'Banner deleted successfully',
            'data' => ['banner_id' => $bannerId],
        ]);
    }

    /**
     * Shared store/update rules. link_value must be present whenever the
     * banner links somewhere - checked here so a missing key still fails.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'placement' => ['required', 'string', Rule::in(self::PLACEMENTS)],
            'display_style' => ['nullable', 'string', Rule::in(self::DISPLAY_STYLES)],
            'link_type' => ['nullable', 'string', Rule::in(self::LINK_TYPES)],
            'link_value' => ['nullable', 'string', 'max:255'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after:valid_from'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $linkType = $data['link_type'] ?? 'none';

        if ($linkType !== 'none' && blank($data['link_value'] ?? null)) {
            throw ValidationException::withMessages([
                'link_value' => "The link_value field is required when link_type is '{$linkType}'.",
            ]);
        }

        // A promo banner is an advertisement for a code. Saving one that points
        // at a code nobody can redeem (missing, switched off, expired, sold out)
        // is how a live poster ends up copying a code checkout then rejects.
        if ($linkType === 'promo') {
            $this->assertPromoIsRedeemable((string) $data['link_value']);
        }

        $data['link_type'] = $linkType;
        $data['link_value'] = $linkType === 'none' ? null : $data['link_value'];

        // Omitted means "leave it as it is": an older admin build that does
        // not know about display_style must not reset a popup back to banner.
        if (! $request->has('display_style')) {
            unset($data['display_style']);
        }

        unset($data['image']); // transport detail, not a column

        return $data;
    }

    /**
     * Store an upload on the public disk so <Image> loads it without auth
     * (avatar pattern) and return its absolute URL.
     */
    private function assertPromoIsRedeemable(string $code): void
    {
        $promotion = Promotion::whereRaw('UPPER(promo_code) = ?', [strtoupper(trim($code))])->first();

        $reason = null;

        if (! $promotion) {
            $reason = "No promotion exists with the code '{$code}'.";
        } elseif (! $promotion->is_active) {
            $reason = "The promotion '{$code}' is inactive, so customers cannot redeem it.";
        } elseif ($promotion->valid_from && now()->lt($promotion->valid_from)) {
            $reason = "The promotion '{$code}' has not started yet.";
        } elseif ($promotion->valid_until && now()->gt($promotion->valid_until)) {
            $reason = "The promotion '{$code}' has expired.";
        } elseif ($promotion->usage_limit !== null && (int) $promotion->usage_count >= (int) $promotion->usage_limit) {
            $reason = "The promotion '{$code}' has reached its usage limit.";
        }

        if ($reason !== null) {
            throw ValidationException::withMessages([
                'link_value' => $reason . ' Activate the promotion first, or point the banner somewhere else.',
            ]);
        }
    }

    private function storeImage($file): string
    {
        $filename = sprintf(
            'banner_%s_%s.%s',
            now()->format('Y_m_d_H_i_s'),
            Str::random(16),
            $file->extension()
        );

        $file->storeAs('banners', $filename, 'public');

        return asset('storage/banners/' . $filename);
    }

    /**
     * Delete a banner file we own on the public disk; external URLs are left alone.
     */
    private function deleteImage(?string $imageUrl): void
    {
        if (! $imageUrl || ! str_contains($imageUrl, '/storage/banners/')) {
            return;
        }

        Storage::disk('public')->delete('banners/' . basename($imageUrl));
    }
}
