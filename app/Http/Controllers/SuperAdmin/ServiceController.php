<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Support\SchemaCompat;
use Illuminate\Http\Request;
use App\Models\Service;
use App\Models\ServicePricing;
use App\Models\Category;
use App\Http\Resources\ServiceResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;


class ServiceController extends Controller
{
    /**
     * Display a listing of the services.
     */
    public function index(Request $request): View
    {
        $query = Service::with(['category', 'pricing']);

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search by name
        if ($request->filled('search')) {
            $query->where('name', SchemaCompat::likeOperator(), '%' . $request->search . '%');
        }

        // Sort options
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $services = $query->paginate($request->get('per_page', 15));
        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return view('admin.services.index', compact('services', 'categories'));
    }

    /**
     * Show the form for creating a new service.
     */
    public function create(): View
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        return view('admin.services.create', compact('categories'));
    }

    /**
     * Store a newly created service in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'category_id' => 'required|exists:service_categories,category_id',
                'name' => 'required|string|max:100|unique:services,name',
                'description' => 'required|string',
                'features' => 'nullable|array',
                'features.*' => 'string|max:255',
                'terms_conditions' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'boolean',
                'requires_prepay' => 'boolean',
            ]);

            // Handle image upload with timestamp naming
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $timestamp = now()->format('Y_m_d_H_i_s');
                $extension = $image->getClientOriginalExtension();
                $filename = "service_{$timestamp}.{$extension}";
                $validated['image_url'] = $image->storeAs('services', $filename, 'public');
            }

            // Remove image from validated data and handle features
            unset($validated['image']);

            // Ensure features is properly handled
            if (!isset($validated['features'])) {
                $validated['features'] = null;
            }

            // Checkbox semantics: absent = unchecked = COD only (no pre-pay)
            $validated['requires_prepay'] = $request->boolean('requires_prepay');

            $service = Service::create($validated);

            return redirect()->route('admin.services.index')
                ->with('success', 'Service "' . $service->name . '" created successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to create service. Please try again.')
                ->withInput();
        }
    }

    /**
     * Display the specified service.
     */
    public function show(Service $service): View
    {
        $service->load([
            'category',
            'partners',
            'pricing',
            'bookings' => function ($query) {
                $query->with(['customer'])->latest()->limit(5);
            }
        ]);
        $service->loadCount(['orderItems as order_items_count', 'bookings as bookings_count']);
        return view('admin.services.show', compact('service'));
    }

    /**
     * Show the form for editing the specified service.
     */
    public function edit(Service $service): View
    {
        $service->load(['pricing']);
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        return view('admin.services.edit', compact('service', 'categories'));
    }

    /**
     * Update the specified service in storage.
     */
    public function update(Request $request, Service $service): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'category_id' => 'required|exists:service_categories,category_id',
                'name' => ['required', 'string', 'max:100', Rule::unique('services', 'name')->ignore($service->service_id, 'service_id')],
                'description' => 'required|string',
                'features' => 'nullable|array',
                'features.*' => 'string|max:255',
                'terms_conditions' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'boolean',
                'is_featured' => 'boolean',
                'is_popular' => 'boolean',
                'requires_prepay' => 'boolean',
                'pricing' => 'nullable|array',
                'pricing.*.tier_name' => 'required_with:pricing|string|in:basic,standard,premium',
                'pricing.*.base_price' => 'required_with:pricing|numeric|min:0',
                'pricing.*.estimated_duration_min' => 'required_with:pricing|integer|min:1',
                'pricing.*.description' => 'nullable|string',
                'pricing.*.is_active' => 'nullable|boolean',
                'pricing.*.pricing_id' => 'nullable|exists:service_pricing,pricing_id',
            ]);

            // Handle image upload with timestamp naming
            if ($request->hasFile('image')) {
                // Delete old image if it exists
                if ($service->image_url && Storage::disk('public')->exists($service->image_url)) {
                    Storage::disk('public')->delete($service->image_url);
                }

                $image = $request->file('image');
                $timestamp = now()->format('Y_m_d_H_i_s');
                $extension = $image->getClientOriginalExtension();
                $filename = "service_{$timestamp}.{$extension}";
                $validated['image_url'] = $image->storeAs('services', $filename, 'public');
            }

            // Remove image from validated data and handle features
            unset($validated['image']);

            // Ensure features is properly handled
            if (!isset($validated['features'])) {
                $validated['features'] = $service->features; // Keep existing features if not provided
            }

            // Handle pricing separately
            $pricingData = $validated['pricing'] ?? [];
            unset($validated['pricing']);

            // Checkbox semantics: absent = unchecked = COD only (no pre-pay)
            $validated['requires_prepay'] = $request->boolean('requires_prepay');

            $service->update($validated);

            // Handle pricing updates
            if (!empty($pricingData)) {
                $existingPricingIds = [];

                foreach ($pricingData as $pricing) {
                    $pricingAttributes = [
                        'service_id' => $service->service_id,
                        'tier_name' => $pricing['tier_name'],
                        'base_price' => $pricing['base_price'],
                        'estimated_duration_min' => $pricing['estimated_duration_min'],
                        'description' => $pricing['description'] ?? null,
                        'is_active' => $pricing['is_active'] ?? true,
                    ];

                    if (!empty($pricing['pricing_id'])) {
                        // Update existing pricing
                        $servicePricing = ServicePricing::find($pricing['pricing_id']);
                        if ($servicePricing && $servicePricing->service_id == $service->service_id) {
                            $servicePricing->update($pricingAttributes);
                            $existingPricingIds[] = $servicePricing->pricing_id;
                        }
                    } else {
                        // Create new pricing
                        $newPricing = ServicePricing::create($pricingAttributes);
                        $existingPricingIds[] = $newPricing->pricing_id;
                    }
                }

                // Delete pricing tiers that were removed from the form
                ServicePricing::where('service_id', $service->service_id)
                    ->whereNotIn('pricing_id', $existingPricingIds)
                    ->delete();
            }

            return redirect()->route('admin.services.index')
                ->with('success', 'Service "' . $service->name . '" updated successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to update service. Please try again.')
                ->withInput();
        }
    }

    /**
     * Remove the specified service from storage.
     */
    public function destroy(Service $service): RedirectResponse
    {
        try {
            // Check if service has active orders
            if ($service->orderItems()->whereHas('order', function ($q) {
                $q->whereIn('status', ['pending', 'confirmed', 'in_progress']);
            })->exists()) {
                return redirect()->route('admin.services.index')
                    ->with('error', 'Cannot delete service "' . $service->name . '" because it has active orders.');
            }

            $serviceName = $service->name;
            $service->delete();

            return redirect()->route('admin.services.index')
                ->with('success', 'Service "' . $serviceName . '" deleted successfully!');
        } catch (\Exception $e) {
            return redirect()->route('admin.services.index')
                ->with('error', 'Failed to delete service. Please try again.');
        }
    }
}
