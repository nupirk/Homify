<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request): View
    {
        $query = Category::withCount('services');

        // Filter by status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search by name
        if ($request->filled('search')) {
            $query->where('name', 'ILIKE', '%' . $request->search . '%');
        }

        // Sort options
        $sortBy = $request->get('sort_by', 'sort_order');
        $sortOrder = $request->get('sort_order', 'asc');

        if ($sortBy === 'sort_order') {
            $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $categories = $query->paginate($request->get('per_page', 15));

        return view('admin.categories.index', compact('categories'));
    }

    /**
     * Show the form for creating a new category.
     */
    public function create(): View
    {
        $parentCategories = Category::whereNull('parent_id')->orderBy('name')->get();
        return view('admin.categories.create', compact('parentCategories'));
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100|unique:service_categories,name',
                'description' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
                'icon' => 'nullable|string|max:255',
                'parent_id' => 'nullable|exists:service_categories,category_id',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'boolean',
            ]);

            // Handle image upload with timestamp naming
            $imagePath = null;
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $timestamp = now()->format('Y_m_d_H_i_s');
                $extension = $image->getClientOriginalExtension();
                $filename = "category_{$timestamp}.{$extension}";
                $imagePath = $image->storeAs('categories', $filename, 'public');
            }

            // Remove image from validated data and add image_url
            unset($validated['image']);
            $validated['image_url'] = $imagePath;
            $validated['icon_url'] = $validated['icon'] ?? null;
            unset($validated['icon']);

            // Auto-set sort_order if not provided
            if (!isset($validated['sort_order'])) {
                $validated['sort_order'] = Category::max('sort_order') + 1;
            }

            $category = Category::create($validated);

            return redirect()->route('admin.categories.index')
                ->with('success', 'Category "' . $category->name . '" created successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to create category. Please try again.')
                ->withInput();
        }
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category): View
    {
        $category->load(['services' => function ($query) {
            $query->where('is_active', true);
        }]);

        return view('admin.categories.show', compact('category'));
    }

    /**
     * Show the form for editing the specified category.
     */
    public function edit(Category $category): View
    {
        $parentCategories = Category::whereNull('parent_id')
            ->where('category_id', '!=', $category->category_id)
            ->orderBy('name')
            ->get();
        return view('admin.categories.edit', compact('category', 'parentCategories'));
    }

    /**
     * Update the specified category in storage.
     */
    public function update(Request $request, Category $category): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:100', Rule::unique('service_categories', 'name')->ignore($category->category_id, 'category_id')],
                'description' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
                'icon' => 'nullable|string|max:255',
                'parent_id' => 'nullable|exists:service_categories,category_id',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'boolean',
            ]);

            // Handle image upload with timestamp naming
            if ($request->hasFile('image')) {
                // Delete old image if it exists
                if ($category->image_url && Storage::disk('public')->exists($category->image_url)) {
                    Storage::disk('public')->delete($category->image_url);
                }

                $image = $request->file('image');
                $timestamp = now()->format('Y_m_d_H_i_s');
                $extension = $image->getClientOriginalExtension();
                $filename = "category_{$timestamp}.{$extension}";
                $imagePath = $image->storeAs('categories', $filename, 'public');
                $validated['image_url'] = $imagePath;
            }

            // Remove image from validated data and handle icon
            unset($validated['image']);
            if (isset($validated['icon'])) {
                $validated['icon_url'] = $validated['icon'];
                unset($validated['icon']);
            }

            $category->update($validated);

            return redirect()->route('admin.categories.index')
                ->with('success', 'Category "' . $category->name . '" updated successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to update category. Please try again.')
                ->withInput();
        }
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Category $category): RedirectResponse
    {
        try {
            // Check if category has services
            if ($category->services()->exists()) {
                return redirect()->route('admin.categories.index')
                    ->with('error', 'Cannot delete category "' . $category->name . '" because it has associated services.');
            }

            $categoryName = $category->name;
            $category->delete();

            return redirect()->route('admin.categories.index')
                ->with('success', 'Category "' . $categoryName . '" deleted successfully!');
        } catch (\Exception $e) {
            return redirect()->route('admin.categories.index')
                ->with('error', 'Failed to delete category. Please try again.');
        }
    }

    /**
     * Toggle category active status.
     */
    public function toggleStatus(Category $category): JsonResponse
    {
        $category->update(['is_active' => !$category->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Category status updated successfully',
            'data' => new CategoryResource($category)
        ]);
    }

    /**
     * Reorder categories.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:categories,id',
            'categories.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['categories'] as $categoryData) {
            Category::where('id', $categoryData['id'])
                ->update(['sort_order' => $categoryData['sort_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Categories reordered successfully'
        ]);
    }

    /**
     * Get category statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_categories' => Category::count(),
            'active_categories' => Category::where('is_active', true)->count(),
            'inactive_categories' => Category::where('is_active', false)->count(),
            'categories_with_services' => Category::has('services')->count(),
            'empty_categories' => Category::doesntHave('services')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
