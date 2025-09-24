<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class BrandController extends Controller
{
    /**
     * Display a listing of brands with search, filter, sort, and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $query = Brand::query();

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('slug', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('is_active', $request->status === 'active');
        }

        // Sorting
        $sortField = $request->get('sort_field', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');
        
        $allowedSortFields = ['name', 'created_at', 'is_active'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        }

        // Add secondary sort by name for consistency
        if ($sortField !== 'name') {
            $query->orderBy('name', 'asc');
        }

        // Pagination
        $perPage = $request->get('per_page', 10);
        $brands = $query->withCount('products')->paginate($perPage);

        return response()->json($brands);
    }

    /**
     * Store a newly created brand
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:brands,name',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        // Generate slug from name
        $validated['slug'] = Str::slug($validated['name']);
        
        // Ensure slug is unique
        $originalSlug = $validated['slug'];
        $counter = 1;
        while (Brand::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        $brand = Brand::create($validated);

        return response()->json([
            'message' => 'Brand created successfully',
            'brand' => $brand->load('products:id,brand_id')
        ], 201);
    }

    /**
     * Display the specified brand
     */
    public function show(Brand $brand): JsonResponse
    {
        $brand->load(['products' => function ($query) {
            $query->select('id', 'name', 'brand_id', 'price', 'is_active', 'created_at');
        }]);

        $brand->products_count = $brand->products->count();
        $brand->active_products_count = $brand->products->where('is_active', true)->count();

        return response()->json($brand);
    }

    /**
     * Update the specified brand
     */
    public function update(Request $request, Brand $brand): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('brands', 'name')->ignore($brand->id)
            ],
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        // Update slug if name changed
        if ($validated['name'] !== $brand->name) {
            $newSlug = Str::slug($validated['name']);
            
            // Ensure slug is unique
            $originalSlug = $newSlug;
            $counter = 1;
            while (Brand::where('slug', $newSlug)->where('id', '!=', $brand->id)->exists()) {
                $newSlug = $originalSlug . '-' . $counter;
                $counter++;
            }
            
            $validated['slug'] = $newSlug;
        }

        $brand->update($validated);

        return response()->json([
            'message' => 'Brand updated successfully',
            'brand' => $brand->fresh()->load('products:id,brand_id')
        ]);
    }

    /**
     * Remove the specified brand
     */
    public function destroy(Brand $brand): JsonResponse
    {
        // Check if brand has products
        $productsCount = $brand->products()->count();
        
        if ($productsCount > 0) {
            return response()->json([
                'message' => "Cannot delete brand. It has {$productsCount} associated products.",
                'error' => 'brand_has_products'
            ], 422);
        }

        $brand->delete();

        return response()->json([
            'message' => 'Brand deleted successfully'
        ]);
    }

    /**
     * Toggle brand status (active/inactive)
     */
    public function toggleStatus(Brand $brand): JsonResponse
    {
        $brand->update([
            'is_active' => !$brand->is_active
        ]);

        $status = $brand->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'message' => "Brand {$status} successfully",
            'brand' => $brand
        ]);
    }

    /**
     * Get brand statistics for dashboard
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_brands' => Brand::count(),
            'active_brands' => Brand::where('is_active', true)->count(),
            'inactive_brands' => Brand::where('is_active', false)->count(),
            'brands_with_products' => Brand::has('products')->count(),
            'new_brands_this_month' => Brand::whereMonth('created_at', now()->month)
                                           ->whereYear('created_at', now()->year)
                                           ->count()
        ];

        return response()->json($stats);
    }

    /**
     * Get brands with their related categories for navigation
     */
    public function getBrandsWithCategories(): JsonResponse
    {
        $brands = Brand::where('is_active', true)
            ->orderBy('name')
            ->get();

        $brandsWithCategories = $brands->map(function ($brand) {
            // Get categories directly from products table with explicit join
            $categories = DB::table('products')
                ->join('categories', 'products.category_id', '=', 'categories.id')
                ->where('products.brand_id', $brand->id)
                ->where('products.is_active', true)
                ->where('categories.is_active', true)
                ->select('categories.id', 'categories.name', 'categories.slug', 'categories.type', 'categories.parent_id')
                ->distinct()
                ->orderBy('categories.name')
                ->get();

            return [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'categories' => $categories->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                        'type' => $category->type,
                        'parent_id' => $category->parent_id
                    ];
                })
            ];
        })->filter(function ($brand) {
            // Only include brands that have categories
            return $brand['categories']->isNotEmpty();
        });

        return response()->json([
            'success' => true,
            'data' => $brandsWithCategories->values()
        ]);
    }

    /**
     * Export brands to CSV
     */
    public function export(Request $request): JsonResponse
    {
        $query = Brand::query();

        // Apply same filters as index method
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('slug', 'LIKE', "%{$searchTerm}%");
            });
        }

        if ($request->has('status') && $request->status !== '') {
            $query->where('is_active', $request->status === 'active');
        }

        $brands = $query->withCount('products')->orderBy('name')->get();

        $csvData = [];
        $csvData[] = ['Name', 'Slug', 'Description', 'Status', 'Products Count', 'Created At'];

        foreach ($brands as $brand) {
            $csvData[] = [
                $brand->name,
                $brand->slug,
                $brand->description ?? '',
                $brand->is_active ? 'Active' : 'Inactive',
                $brand->products_count,
                $brand->created_at->format('Y-m-d H:i:s')
            ];
        }

        return response()->json([
            'message' => 'Brands exported successfully',
            'data' => $csvData,
            'filename' => 'brands_export_' . now()->format('Y_m_d_H_i_s') . '.csv'
        ]);
    }
}
