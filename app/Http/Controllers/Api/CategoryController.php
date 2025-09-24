<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories with filtering, search, and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::with('parent');

        // Search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%")
                  ->orWhere('slug', 'like', "%{$searchTerm}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $status = $request->status === 'active' ? 1 : 0;
            $query->where('is_active', $status);
        }

        // Sorting - default to name instead of sort_order
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        
        if (in_array($sortBy, ['name', 'created_at', 'is_active', 'type'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $perPage = $request->get('per_page', 10);
        $categories = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $categories->items(),
            'pagination' => [
                'current_page' => $categories->currentPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'last_page' => $categories->lastPage(),
            ]
        ]);
    }

    /**
     * Store a newly created category
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'parent_id' => 'nullable|exists:categories,id',
            'type' => 'required|in:category,subcategory',
        ]);

        // Validate parent_id requirement for subcategories
        if ($request->type === 'subcategory' && !$request->parent_id) {
            return response()->json([
                'success' => false,
                'message' => 'Parent category is required for subcategories',
                'errors' => ['parent_id' => ['Parent category is required for subcategories']]
            ], 422);
        }

        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'is_active' => $request->get('is_active', true),
            'parent_id' => $request->parent_id,
            'type' => $request->type,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category->load('parent')
        ], 201);
    }

    /**
     * Display the specified category
     */
    public function show(string $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        // Get products count for this category
        $productsCount = $category->products()->count();

        $categoryData = $category->toArray();
        $categoryData['products_count'] = $productsCount;

        return response()->json([
            'success' => true,
            'data' => $categoryData
        ]);
    }

    /**
     * Update the specified category
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->ignore($category->id)
            ],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'parent_id' => 'nullable|exists:categories,id',
            'type' => 'required|in:category,subcategory',
        ]);

        // Validate parent_id requirement for subcategories
        if ($request->type === 'subcategory' && !$request->parent_id) {
            return response()->json([
                'success' => false,
                'message' => 'Parent category is required for subcategories',
                'errors' => ['parent_id' => ['Parent category is required for subcategories']]
            ], 422);
        }

        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'is_active' => $request->get('is_active', $category->is_active),
            'parent_id' => $request->parent_id,
            'type' => $request->type,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category->fresh()->load('parent')
        ]);
    }

    /**
     * Get parent categories (categories without parent_id)
     */
    public function getParents(): JsonResponse
    {
        $categories = Category::whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Get hierarchical categories for product management
     */
    public function getHierarchical(): JsonResponse
    {
        $categories = Category::with(['children' => function ($query) {
            $query->where('is_active', true)->orderBy('name');
        }])
        ->whereNull('parent_id')
        ->where('is_active', true)
        ->orderBy('name')
        ->get();

        // Transform categories to include full path and hierarchy info
        $hierarchicalCategories = [];
        
        foreach ($categories as $parent) {
            // Add parent category
            $hierarchicalCategories[] = [
                'id' => $parent->id,
                'name' => $parent->name,
                'full_path' => $parent->name,
                'level' => 0,
                'is_parent' => $parent->children->count() > 0,
                'parent_id' => null
            ];
            
            // Add children
            foreach ($parent->children as $child) {
                $hierarchicalCategories[] = [
                    'id' => $child->id,
                    'name' => $child->name,
                    'full_path' => $parent->name . ' > ' . $child->name,
                    'level' => 1,
                    'is_parent' => false,
                    'parent_id' => $parent->id
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $hierarchicalCategories
        ]);
    }

    /**
     * Get public categories for navigation (no authentication required)
     */
    public function getPublicCategories(): JsonResponse
    {
        $categories = Category::with('parent')
                             ->where('is_active', true)
                             ->orderBy('name')
                             ->get(['id', 'name', 'slug', 'type', 'parent_id']);

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Remove the specified category
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $category = Category::with(['products', 'children'])->findOrFail($id);

            // Comprehensive validation with detailed feedback
            $blockers = [];
            $suggestions = [];

            // Check for products
            $productsCount = $category->products()->count();
            if ($productsCount > 0) {
                $blockers[] = "{$productsCount} product" . ($productsCount > 1 ? 's' : '');
                $suggestions[] = "Move products to another category or delete them first";
            }

            // Check for subcategories
            $childrenCount = $category->children()->count();
            if ($childrenCount > 0) {
                $childNames = $category->children()->pluck('name')->take(3)->toArray();
                $childrenList = implode(', ', $childNames);
                if ($childrenCount > 3) {
                    $childrenList .= " and " . ($childrenCount - 3) . " more";
                }
                
                $blockers[] = "{$childrenCount} subcategor" . ($childrenCount > 1 ? 'ies' : 'y') . " ({$childrenList})";
                $suggestions[] = "Delete or reassign subcategories to another parent category first";
            }

            // If there are blockers, return detailed error with helpful suggestions
            if (!empty($blockers)) {
                $message = "Cannot delete '{$category->name}' category. It contains:\n• " . implode("\n• ", $blockers);
                
                if (!empty($suggestions)) {
                    $message .= "\n\nTo delete this category:\n• " . implode("\n• ", $suggestions);
                }

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'details' => [
                        'category_name' => $category->name,
                        'products_count' => $productsCount,
                        'children_count' => $childrenCount,
                        'children_names' => $category->children()->pluck('name')->toArray(),
                        'blockers' => $blockers,
                        'suggestions' => $suggestions
                    ]
                ], 422);
            }

            // Safe to delete - no products or subcategories
            $categoryName = $category->name;
            $category->delete();

            return response()->json([
                'success' => true,
                'message' => "Category '{$categoryName}' deleted successfully"
            ]);

        } catch (\Exception $e) {
            Log::error('Category deletion failed', [
                'category_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category. Please try again or contact support if the problem persists.'
            ], 500);
        }
    }

    /**
     * Toggle category status
     */
    public function toggleStatus(string $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        
        $category->update([
            'is_active' => !$category->is_active
        ]);

        return response()->json([
            'success' => true,
            'message' => $category->is_active ? 'Category activated successfully' : 'Category deactivated successfully',
            'data' => $category->fresh()
        ]);
    }

    /**
     * Get category statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_categories' => Category::count(),
            'active_categories' => Category::where('is_active', true)->count(),
            'inactive_categories' => Category::where('is_active', false)->count(),
            'new_categories_this_month' => Category::whereMonth('created_at', now()->month)
                                                 ->whereYear('created_at', now()->year)
                                                 ->count(),
            'categories_with_products' => Category::has('products')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Export categories to CSV
     */
    public function export(Request $request)
    {
        $query = Category::query();

        // Apply same filters as index
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%")
                  ->orWhere('slug', 'like', "%{$searchTerm}%");
            });
        }

        if ($request->filled('status')) {
            $status = $request->status === 'active' ? 1 : 0;
            $query->where('is_active', $status);
        }

        $categories = $query->with('products')->get();

        $filename = 'categories_export_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($categories) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'ID',
                'Name',
                'Slug',
                'Description',
                'Status',
                'Products Count',
                'Created At'
            ]);

            // CSV Data
            foreach ($categories as $category) {
                fputcsv($file, [
                    $category->id,
                    $category->name,
                    $category->slug,
                    $category->description ?: 'N/A',
                    $category->is_active ? 'Active' : 'Inactive',
                    $category->products->count(),
                    $category->created_at ?: 'N/A',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
