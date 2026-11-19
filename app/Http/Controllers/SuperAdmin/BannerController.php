<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Promotion;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Web admin CRUD for the marketing banners the customer app renders.
 *
 * The app-side admin console talks to Api\V1\BannerController; this is the same
 * contract for the Blade dashboard, so a banner saved in either place behaves
 * identically.
 */
class BannerController extends Controller
{
    private const PLACEMENTS = ['home_mid', 'explore_top', 'cart_top'];

    private const LINK_TYPES = ['none', 'service', 'category', 'url', 'promo'];

    private const DISPLAY_STYLES = ['banner', 'poster', 'popup'];

    public function index(): View
    {
        $banners = Banner::orderBy('sort_order')->orderBy('banner_id')->get();
        $activeCount = $banners->where('is_active', true)->count();

        return view('admin.banners.index', compact('banners', 'activeCount'));
    }

    public function create(): View
    {
        return view('admin.banners.form', [
            'banner' => new Banner([
                'placement' => 'home_mid',
                'display_style' => 'banner',
                'link_type' => 'none',
                'sort_order' => 0,
                'is_active' => true,
            ]),
            'placements' => self::PLACEMENTS,
            'linkTypes' => self::LINK_TYPES,
            'displayStyles' => self::DISPLAY_STYLES,
            'services' => Service::orderBy('name')->get(['service_id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            $data['image_url'] = $this->storeImage($request->file('image'));
        }

        // The app admin build refuses to save a banner with no creative, and a
        // banner without one renders as an empty slot in the app.
        if (blank($data['image_url'] ?? null)) {
            return back()->withInput()->withErrors([
                'image' => 'Add an image file or paste an image URL — the app needs a creative to show.',
            ]);
        }

        Banner::create($data);

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner created. It goes live in the app as soon as its start time passes.');
    }

    public function edit(Banner $banner): View
    {
        return view('admin.banners.form', [
            'banner' => $banner,
            'placements' => self::PLACEMENTS,
            'linkTypes' => self::LINK_TYPES,
            'displayStyles' => self::DISPLAY_STYLES,
            'services' => Service::orderBy('name')->get(['service_id', 'name']),
        ]);
    }

    public function update(Request $request, Banner $banner): RedirectResponse
    {
        $data = $this->validated($request);
        $previousImageUrl = $banner->image_url;

        if ($request->hasFile('image')) {
            $data['image_url'] = $this->storeImage($request->file('image'));
        } elseif (blank($data['image_url'] ?? null)) {
            // The form always posts image_url; empty means "leave the current
            // creative alone", the same way the app admin omits the key.
            unset($data['image_url']);
        }

        if (blank($data['image_url'] ?? $previousImageUrl)) {
            return back()->withInput()->withErrors([
                'image' => 'Add an image file or paste an image URL — the app needs a creative to show.',
            ]);
        }

        $banner->update($data);

        if ($previousImageUrl !== $banner->image_url) {
            $this->deleteImage($previousImageUrl);
        }

        return redirect()->route('admin.banners.index')->with('success', 'Banner updated.');
    }

    public function toggle(Banner $banner): RedirectResponse
    {
        $banner->update(['is_active' => ! $banner->is_active]);

        return back()->with(
            'success',
            $banner->is_active ? 'Banner switched on.' : 'Banner switched off.'
        );
    }

    public function destroy(Banner $banner): RedirectResponse
    {
        $this->deleteImage($banner->image_url);
        $banner->delete();

        return redirect()->route('admin.banners.index')->with('success', 'Banner deleted.');
    }

    /**
     * Shared store/update rules. link_value is required whenever the banner
     * links somewhere, and a promo link must point at a code customers can
     * actually redeem.
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'placement' => ['required', 'string', Rule::in(self::PLACEMENTS)],
            'display_style' => ['required', 'string', Rule::in(self::DISPLAY_STYLES)],
            'link_type' => ['required', 'string', Rule::in(self::LINK_TYPES)],
            'link_value' => ['nullable', 'string', 'max:255'],
            'service_id' => ['nullable', 'integer', 'exists:services,service_id'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after:valid_from'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($data['link_type'] !== 'none' && blank($data['link_value'] ?? null)) {
            throw ValidationException::withMessages([
                'link_value' => "This banner links to a {$data['link_type']}, so a link value is required.",
            ]);
        }

        // Same per-type rules the app admin enforces before it uploads.
        if ($data['link_type'] === 'service' || $data['link_type'] === 'category') {
            if (! preg_match('/^\d+$/', trim((string) $data['link_value']))) {
                throw ValidationException::withMessages([
                    'link_value' => "Enter a numeric {$data['link_type']} ID.",
                ]);
            }
        } elseif ($data['link_type'] === 'url') {
            if (! preg_match('#^https?://\S+$#i', trim((string) $data['link_value']))) {
                throw ValidationException::withMessages([
                    'link_value' => 'Enter a full URL starting with http:// or https://.',
                ]);
            }
        }

        if ($data['link_type'] === 'promo') {
            $this->assertPromoIsRedeemable((string) $data['link_value']);
        }

        $data['link_value'] = $data['link_type'] === 'none' ? null : $data['link_value'];
        // Empty means every screen; a value scopes it to that service's cart.
        $data['service_id'] = $request->input('service_id') ? (int) $request->input('service_id') : null;

        // datetime-local posts "2026-11-19T10:00"; store a clean timestamp.
        $data['valid_from'] = Carbon::parse($data['valid_from']);
        $data['valid_until'] = blank($data['valid_until'] ?? null)
            ? null
            : Carbon::parse($data['valid_until']);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        unset($data['image']); // transport detail, not a column

        return $data;
    }

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
        } elseif ($promotion->usage_limit !== null
            && (int) $promotion->usage_limit > 0
            && (int) $promotion->usage_count >= (int) $promotion->usage_limit) {
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
     * Only remove files we own; an admin-pasted external URL is left alone.
     */
    private function deleteImage(?string $imageUrl): void
    {
        if (! $imageUrl || ! str_contains($imageUrl, '/storage/banners/')) {
            return;
        }

        Storage::disk('public')->delete('banners/' . basename($imageUrl));
    }
}
