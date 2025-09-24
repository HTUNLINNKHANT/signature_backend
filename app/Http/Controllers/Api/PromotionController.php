<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    /**
     * Display a listing of promotions
     */
    public function index(Request $request): JsonResponse
    {
        $query = Promotion::withCount('products');

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhere('slug', 'LIKE', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('is_active', $request->status === 'active');
        }

        // Filter by type
        if ($request->has('type') && $request->type !== '') {
            $query->where('type', $request->type);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if (in_array($sortBy, ['name', 'type', 'value', 'start_date', 'end_date', 'created_at', 'products_count'])) {
            if ($sortBy === 'products_count') {
                $query->orderBy('products_count', $sortOrder);
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $promotions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $promotions->items(),
            'meta' => [
                'current_page' => $promotions->currentPage(),
                'last_page' => $promotions->lastPage(),
                'per_page' => $promotions->perPage(),
                'total' => $promotions->total(),
            ]
        ]);
    }

    /**
     * Store a newly created promotion
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:percentage,fixed_amount,buy_x_get_y',
            'value' => 'required|numeric|min:0',
            'minimum_amount' => 'nullable|numeric|min:0',
            'maximum_discount' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_customer' => 'nullable|integer|min:1',
            'applies_to' => 'nullable|in:all,specific_products,specific_categories,specific_brands',
            'conditions' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        // Additional validation for percentage type
        if ($validated['type'] === 'percentage' && $validated['value'] > 100) {
            return response()->json([
                'success' => false,
                'message' => 'Percentage discount cannot exceed 100%',
                'errors' => ['value' => ['Percentage discount cannot exceed 100%']]
            ], 422);
        }

        // Ensure applies_to is set to 'all' if not provided
        if (!isset($validated['applies_to']) || empty($validated['applies_to'])) {
            $validated['applies_to'] = 'all';
        }

        // Generate slug from name
        $validated['slug'] = Str::slug($validated['name']);
        
        // Ensure unique slug
        $originalSlug = $validated['slug'];
        $counter = 1;
        while (Promotion::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        // Set created_by to current user
        $validated['created_by'] = auth('sanctum')->id();
        $validated['used_count'] = 0;

        $promotion = Promotion::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Promotion created successfully',
            'data' => $promotion->load('products')
        ], 201);
    }

    /**
     * Display the specified promotion
     */
    public function show($id): JsonResponse
    {
        $promotion = Promotion::with(['products', 'categories', 'brands'])
                              ->withCount('products')
                              ->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $promotion
        ]);
    }

    /**
     * Update the specified promotion
     */
    public function update(Request $request, $id): JsonResponse
    {
        $promotion = Promotion::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:percentage,fixed_amount,buy_x_get_y',
            'value' => 'required|numeric|min:0',
            'minimum_amount' => 'nullable|numeric|min:0',
            'maximum_discount' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_limit_per_customer' => 'nullable|integer|min:1',
            'applies_to' => 'nullable|in:all,specific_products,specific_categories,specific_brands',
            'conditions' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        // Additional validation for percentage type
        if ($validated['type'] === 'percentage' && $validated['value'] > 100) {
            return response()->json([
                'success' => false,
                'message' => 'Percentage discount cannot exceed 100%',
                'errors' => ['value' => ['Percentage discount cannot exceed 100%']]
            ], 422);
        }

        // Ensure applies_to is set to 'all' if not provided
        if (!isset($validated['applies_to']) || empty($validated['applies_to'])) {
            $validated['applies_to'] = 'all';
        }
        
        // Update slug if name changed
        if ($validated['name'] !== $promotion->name) {
            $validated['slug'] = Str::slug($validated['name']);
            
            // Ensure unique slug
            $originalSlug = $validated['slug'];
            $counter = 1;
            while (Promotion::where('slug', $validated['slug'])->where('id', '!=', $promotion->id)->exists()) {
                $validated['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        $promotion->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Promotion updated successfully',
            'data' => $promotion->fresh()->load('products')
        ]);
    }

    /**
     * Remove the specified promotion
     */
    public function destroy($id): JsonResponse
    {
        $promotion = Promotion::findOrFail($id);

        // Check if promotion has products
        if ($promotion->products()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete promotion that has products associated with it'
            ], 422);
        }

        $promotion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Promotion deleted successfully'
        ]);
    }

    /**
     * Toggle promotion status
     */
    public function toggleStatus($id): JsonResponse
    {
        $promotion = Promotion::findOrFail($id);
        $promotion->update(['is_active' => !$promotion->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Promotion status updated successfully',
            'data' => $promotion->fresh()
        ]);
    }

    /**
     * Get promotion statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total' => Promotion::count(),
            'active' => Promotion::where('is_active', true)->count(),
            'inactive' => Promotion::where('is_active', false)->count(),
            'expired' => Promotion::where('end_date', '<', now())->count(),
            'upcoming' => Promotion::where('start_date', '>', now())->count(),
            'with_products' => Promotion::has('products')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Export promotions to CSV
     */
    public function export(Request $request): JsonResponse
    {
        $query = Promotion::withCount('products');

        // Apply same filters as index
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhere('slug', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status !== '') {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->has('type') && $request->type !== '') {
            $query->where('type', $request->type);
        }

        $promotions = $query->orderBy('created_at', 'desc')->get();

        $csvData = [];
        $csvData[] = ['ID', 'Name', 'Slug', 'Type', 'Value', 'Start Date', 'End Date', 'Usage Limit', 'Min Amount', 'Max Discount', 'Products Count', 'Status', 'Created At'];

        foreach ($promotions as $promotion) {
            $csvData[] = [
                $promotion->id,
                $promotion->name,
                $promotion->slug,
                ucfirst(str_replace('_', ' ', $promotion->type)),
                $promotion->value,
                $promotion->start_date->format('Y-m-d'),
                $promotion->end_date->format('Y-m-d'),
                $promotion->usage_limit ?? 'Unlimited',
                $promotion->minimum_amount ?? 'None',
                $promotion->maximum_discount ?? 'None',
                $promotion->products_count,
                $promotion->is_active ? 'Active' : 'Inactive',
                $promotion->created_at->format('Y-m-d H:i:s'),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $csvData,
            'filename' => 'promotions_' . now()->format('Y-m-d_H-i-s') . '.csv'
        ]);
    }
}
