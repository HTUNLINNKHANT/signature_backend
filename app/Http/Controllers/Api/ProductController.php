<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\ProductVariant;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource for admin.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'brand', 'variants', 'activePromotions']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('tags', 'like', "%{$search}%")
                  ->orWhereHas('category', function ($categoryQuery) use ($search) {
                      $categoryQuery->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('brand', function ($brandQuery) use ($search) {
                      $brandQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by category
        if ($request->filled('category_id') && $request->category_id !== 'all') {
            $query->where('category_id', $request->category_id);
        }

        // Filter by brand
        if ($request->filled('brand_id') && $request->brand_id !== 'all') {
            $query->where('brand_id', $request->brand_id);
        }


        // Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($request->status === 'featured') {
                $query->where('is_featured', true);
            } elseif ($request->status === 'in_stock') {
                $query->where('in_stock', true);
            } elseif ($request->status === 'out_of_stock') {
                $query->where('in_stock', false);
            } elseif ($request->status === 'with_promotions') {
                $query->whereHas('activePromotions');
            }
        }

        // Filter by price range
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        $allowedSorts = ['name', 'price', 'created_at', 'updated_at', 'rating'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $perPage = min($perPage, 100); // Max 100 items per page
        
        $products = $query->paginate($perPage);

        // Add computed fields
        $products->getCollection()->transform(function ($product) {
            // Use saved promotional price if available, otherwise calculate from active promotions
            $product->discounted_price = $product->promotional_price ?? $product->getDiscountedPrice();
            $product->discount_percentage = $product->promotional_price ? 
                round((($product->price - $product->promotional_price) / $product->price) * 100, 2) : 
                $product->getDiscountPercentage();
            $product->has_promotion = $product->hasPromotionalPrice() || $product->hasActivePromotion();
            $product->variants_count = $product->variants->count();
            $product->active_variants_count = $product->activeVariants->count();
            return $product;
        });

        return response()->json($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_code' => 'nullable|string|max:50|unique:products,product_code',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'required|exists:brands,id',
            'hover_image' => 'nullable|string',
            'product_images' => 'nullable|array',
            'product_images.*' => 'string',
            'features' => 'nullable|array',
            'tags' => 'nullable|array',
            'in_stock' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'variants' => 'nullable|array',
            'variants.*.color' => 'required_with:variants|string|max:50',
            'variants.*.color_code' => 'nullable|string|max:7',
            'variants.*.sizes' => 'required_with:variants|array|min:1',
            'variants.*.sizes.*.name' => 'required|string|max:20',
            'variants.*.sizes.*.stock_quantity' => 'required|integer|min:0',
            'variants.*.sizes.*.reserved_quantity' => 'nullable|integer|min:0',
            'variants.*.sizes.*.price' => 'nullable|numeric|min:0',
            'variants.*.sizes.*.compare_at_price' => 'nullable|numeric|min:0',
            'variants.*.sizes.*.weight' => 'nullable|numeric|min:0',
            'variants.*.sizes.*.barcode' => 'nullable|string|max:50',
            'variants.*.sizes.*.is_active' => 'nullable|boolean',
            'variants.*.sizes.*.track_inventory' => 'nullable|boolean',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.is_active' => 'nullable|boolean',
            'promotion_id' => 'nullable|exists:promotions,id'
        ]);

        DB::beginTransaction();
        try {
            // Generate product code if not provided
            if (empty($validated['product_code'])) {
                $validated['product_code'] = $this->generateUniqueProductCode();
            }
            
            // Generate slug
            $validated['slug'] = $this->generateUniqueSlug($validated['name']);
            
            // Set defaults
            $validated['is_active'] = $validated['is_active'] ?? true;
            $validated['is_featured'] = $validated['is_featured'] ?? false;
            $validated['in_stock'] = 0; // Will be calculated from variants

            // Create product
            $product = Product::create($validated);

            // Create variants if provided
            if (isset($validated['variants']) && is_array($validated['variants'])) {
                foreach ($validated['variants'] as $variantData) {
                    // Prepare variant data with new multi-size structure
                    $variant = [
                        'product_id' => $product->id,
                        'sku' => $this->generateVariantSku($product, $variantData),
                        'color' => $variantData['color'],
                        'color_code' => $variantData['color_code'] ?? null,
                        'sizes' => $variantData['sizes'], // JSON array of sizes
                        'price' => $variantData['price'] ?? null,
                        'compare_at_price' => $variantData['compare_at_price'] ?? null,
                        'is_active' => $variantData['is_active'] ?? true,
                    ];
                    
                    ProductVariant::create($variant);
                }
                // Update product stock from variants
                $product->updateStockFromVariants();
            }

            // Apply promotion if provided
            if (isset($validated['promotion_id']) && !empty($validated['promotion_id'])) {
                $product->applyPromotion($validated['promotion_id']);
            }

            DB::commit();

            // Load relationships for response
            $product->load(['category', 'brand', 'variants', 'activePromotions']);
            
            return response()->json([
                'message' => 'Product created successfully',
                'product' => $product
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Failed to create product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $product = Product::with([
            'category', 
            'brand', 
            'variants' => function ($query) {
                // Sizes are stored in JSON; remove ordering by non-existent 'size' column
                $query->orderBy('color');
            },
            'activePromotions'
        ])->findOrFail($id);

        // Add computed fields
        $product->discounted_price = $product->getDiscountedPrice();
        $product->discount_percentage = $product->getDiscountPercentage();
        $product->has_promotion = $product->hasActivePromotion();
        $product->variants_count = $product->variants->count();
        $product->active_variants_count = $product->activeVariants->count();

        return response()->json($product);
    }

    /**
     * Display the specified resource by slug.
     */
    public function showBySlug(string $slug): JsonResponse
    {
        $product = Product::with([
            'category', 
            'brand', 
            'variants' => function ($query) {
                // Sizes are stored in JSON; remove ordering by non-existent 'size' column
                $query->orderBy('color');
            },
            'activePromotions'
        ])->where('slug', $slug)->firstOrFail();

        // Add computed fields
        $product->discounted_price = $product->getDiscountedPrice();
        $product->discount_percentage = $product->getDiscountPercentage();
        $product->has_promotion = $product->hasActivePromotion();
        $product->variants_count = $product->variants->count();
        $product->active_variants_count = $product->activeVariants->count();

        return response()->json($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        // Custom validation for promotion_id to handle empty strings
        $rules = [
            'product_code' => 'sometimes|string|max:50|unique:products,product_code,' . $id,
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'category_id' => 'sometimes|exists:categories,id',
            'brand_id' => 'sometimes|exists:brands,id',
            'hover_image' => 'nullable|string',
            'product_images' => 'sometimes|array',
            'product_images.*' => 'string',
            'features' => 'sometimes|array',
            'tags' => 'sometimes|array',
            'in_stock' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'variants' => 'sometimes|array',
            'variants.*.id' => 'sometimes|exists:product_variants,id',
            'variants.*.color' => 'required_with:variants|string|max:50',
            'variants.*.color_code' => 'nullable|string|max:7',
            'variants.*.sizes' => 'required_with:variants|array|min:1',
            'variants.*.sizes.*.name' => 'required|string|max:20',
            'variants.*.sizes.*.stock_quantity' => 'required|integer|min:0',
            'variants.*.sizes.*.reserved_quantity' => 'nullable|integer|min:0',
            'variants.*.sizes.*.price' => 'nullable|numeric|min:0',
            'variants.*.sizes.*.compare_at_price' => 'nullable|numeric|min:0',
            'variants.*.sizes.*.weight' => 'nullable|numeric|min:0',
            'variants.*.sizes.*.barcode' => 'nullable|string|max:50',
            'variants.*.sizes.*.is_active' => 'nullable|boolean',
            'variants.*.sizes.*.track_inventory' => 'nullable|boolean',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.is_active' => 'sometimes|boolean',
            'promotion_id' => 'nullable|string'
        ];

        $validated = $request->validate($rules);

        // Validate promotion_id exists if not empty
        if (isset($validated['promotion_id']) && !empty($validated['promotion_id'])) {
            $promotion = Promotion::find($validated['promotion_id']);
            if (!$promotion) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => ['promotion_id' => ['The selected promotion does not exist.']]
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            // Update slug if name changed
            if (isset($validated['name']) && $validated['name'] !== $product->name) {
                $validated['slug'] = $this->generateUniqueSlug($validated['name'], $product->id);
            }

            // Update product
            $product->update($validated);

            // Update variants if provided
            if (isset($validated['variants']) && is_array($validated['variants'])) {
                $existingVariantIds = [];
                
                foreach ($validated['variants'] as $variantData) {
                    if (isset($variantData['id'])) {
                        // Update existing variant with new multi-size structure
                        $variant = ProductVariant::findOrFail($variantData['id']);
                        $updateData = [
                            'color' => $variantData['color'],
                            'color_code' => $variantData['color_code'] ?? null,
                            'sizes' => $variantData['sizes'], // JSON array of sizes
                            'price' => $variantData['price'] ?? null,
                            'compare_at_price' => $variantData['compare_at_price'] ?? null,
                            'is_active' => $variantData['is_active'] ?? true,
                            ];
                        $variant->update($updateData);
                        $existingVariantIds[] = $variantData['id'];
                    } else {
                        // Create new variant with multi-size structure
                        $newVariantData = [
                            'product_id' => $product->id,
                            'sku' => $this->generateVariantSku($product, $variantData),
                            'color' => $variantData['color'],
                            'color_code' => $variantData['color_code'] ?? null,
                            'sizes' => $variantData['sizes'], // JSON array of sizes
                            'price' => $variantData['price'] ?? null,
                            'compare_at_price' => $variantData['compare_at_price'] ?? null,
                            'is_active' => $variantData['is_active'] ?? true,
                            ];
                        $newVariant = ProductVariant::create($newVariantData);
                        $existingVariantIds[] = $newVariant->id;
                    }
                }
                
                // Delete variants not in the update list
                $product->variants()->whereNotIn('id', $existingVariantIds)->delete();
                
                // Update product stock from variants
                $product->updateStockFromVariants();
            }

            // Update promotion if provided
            if (array_key_exists('promotion_id', $validated)) {
                if (!empty($validated['promotion_id'])) {
                    // Validate promotion exists if not empty
                    $promotion = Promotion::find($validated['promotion_id']);
                    if (!$promotion) {
                        throw new \Exception('Invalid promotion ID');
                    }
                    $product->applyPromotion($validated['promotion_id']);
                } else {
                    $product->removePromotion();
                }
            }

            DB::commit();

            // Load relationships for response
            $product->load(['category', 'brand', 'variants', 'activePromotions']);
            
            return response()->json([
                'message' => 'Product updated successfully',
                'product' => $product
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Failed to update product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        
        // Check if product has orders (prevent deletion if it has order history)
        // This would require an OrderItem model relationship - for now we'll allow deletion
        
        DB::beginTransaction();
        try {
            // Delete related variants
            $product->variants()->delete();
            
            // Detach promotions
            $product->promotions()->detach();
            
            // Delete the product
            $product->delete();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Product deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Failed to delete product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get featured products
     */
    public function featured(): JsonResponse
    {
        $products = Product::where('in_stock', true)
            ->orderBy('rating', 'desc')
            ->limit(6)
            ->get();

        return response()->json($products);
    }

    /**
     * Get best seller products
     */
    public function bestSellers(): JsonResponse
    {
        $products = Product::with(['category', 'brand', 'variants', 'activePromotions'])
            ->where('is_active', true)
            ->where('in_stock', '>', 0)
            ->orderBy('is_featured', 'desc') // Featured products first
            ->orderBy('created_at', 'desc')  // Then newest first
            ->limit(8)
            ->get();

        // Add computed fields for each product
        $products->transform(function ($product) {
            $product->discounted_price = $product->promotional_price ?? $product->getDiscountedPrice();
            $product->discount_percentage = $product->promotional_price ? 
                round((($product->price - $product->promotional_price) / $product->price) * 100, 2) : 
                $product->getDiscountPercentage();
            $product->has_promotion = $product->hasPromotionalPrice() || $product->hasActivePromotion();
            
            // Get first variant's first size for stock info
            $firstVariant = $product->variants->first();
            if ($firstVariant && !empty($firstVariant->sizes)) {
                // Handle both string (JSON) and array formats
                if (is_string($firstVariant->sizes)) {
                    $sizes = json_decode($firstVariant->sizes, true);
                } else {
                    $sizes = $firstVariant->sizes;
                }
                
                $firstSize = is_array($sizes) && !empty($sizes) ? $sizes[0] : null;
                $product->stock_quantity = is_array($firstSize) ? ($firstSize['stock_quantity'] ?? 0) : 0;
            } else {
                $product->stock_quantity = $product->in_stock;
            }
            
            return $product;
        });

        return response()->json($products);
    }

    /**
     * Get trending products
     */
    public function trending(): JsonResponse
    {
        $products = Product::with(['category', 'brand', 'variants', 'activePromotions'])
            ->where('is_active', true)
            ->where('in_stock', '>', 0)
            ->orderBy('created_at', 'desc')  // Recent products first
            ->orderBy('is_featured', 'desc') // Then featured products
            ->limit(8)
            ->get();

        // Add computed fields for each product
        $products->transform(function ($product) {
            $product->discounted_price = $product->promotional_price ?? $product->getDiscountedPrice();
            $product->discount_percentage = $product->promotional_price ? 
                round((($product->price - $product->promotional_price) / $product->price) * 100, 2) : 
                $product->getDiscountPercentage();
            $product->has_promotion = $product->hasPromotionalPrice() || $product->hasActivePromotion();
            
            // Get first variant's first size for stock info
            $firstVariant = $product->variants->first();
            if ($firstVariant && !empty($firstVariant->sizes)) {
                // Handle both string (JSON) and array formats
                if (is_string($firstVariant->sizes)) {
                    $sizes = json_decode($firstVariant->sizes, true);
                } else {
                    $sizes = $firstVariant->sizes;
                }
                
                $firstSize = is_array($sizes) && !empty($sizes) ? $sizes[0] : null;
                $product->stock_quantity = is_array($firstSize) ? ($firstSize['stock_quantity'] ?? 0) : 0;
            } else {
                $product->stock_quantity = $product->in_stock;
            }
            
            return $product;
        });

        return response()->json($products);
    }

    /**
     * Get product categories
     */
    public function categories(): JsonResponse
    {
        $categories = Product::select('category')
            ->distinct()
            ->pluck('category');

        return response()->json($categories);
    }

    /**
     * Toggle product status (active/inactive)
     */
    public function toggleStatus(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->is_active = !$product->is_active;
        $product->save();

        return response()->json([
            'message' => 'Product status updated successfully',
            'product' => $product
        ]);
    }

    /**
     * Toggle featured status
     */
    public function toggleFeatured(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->is_featured = !$product->is_featured;
        $product->save();

        return response()->json([
            'message' => 'Product featured status updated successfully',
            'product' => $product
        ]);
    }

    /**
     * Get product statistics for admin dashboard
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_products' => Product::count(),
            'active_products' => Product::where('is_active', true)->count(),
            'inactive_products' => Product::where('is_active', false)->count(),
            'featured_products' => Product::where('is_featured', true)->count(),
            'in_stock_products' => Product::where('in_stock', true)->count(),
            'out_of_stock_products' => Product::where('in_stock', false)->count(),
            'products_with_promotions' => Product::whereHas('activePromotions')->count(),
            'products_with_variants' => Product::whereHas('variants')->count(),
            'new_products_this_month' => Product::whereMonth('created_at', now()->month)
                                              ->whereYear('created_at', now()->year)
                                              ->count(),
            'avg_product_price' => Product::avg('price'),
            'total_product_value' => Product::sum('price'),
            'categories_count' => Category::count(),
            'brands_count' => Brand::count(),
            'active_promotions_count' => Promotion::active()->count()
        ];

        return response()->json($stats);
    }

    /**
     * Export products to CSV or Excel
     */
    public function export(Request $request): JsonResponse
    {
        $format = $request->get('format', 'csv'); // Default to CSV
        
        // Apply same filters as index method
        $query = Product::with(['category', 'brand', 'variants']);

        // Apply filters (same as index method)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id') && $request->category_id !== 'all') {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('brand_id') && $request->brand_id !== 'all') {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $products = $query->get();

        // Prepare data for export
        $exportData = [];
        $exportData[] = [
            'ID', 'Product Code', 'Name', 'Description', 'Price (Ks)', 'Category', 'Brand',
            'Status', 'Featured', 'Stock Quantity', 'Variants Count', 'Created At', 'Updated At'
        ];

        foreach ($products as $product) {
            $exportData[] = [
                $product->id,
                $product->product_code,
                $product->name,
                strip_tags($product->description), // Remove HTML tags for clean export
                number_format((float)$product->price, 2),
                $product->category ? $product->category->name : '',
                $product->brand ? $product->brand->name : '',
                $product->is_active ? 'Active' : 'Inactive',
                $product->is_featured ? 'Yes' : 'No',
                $product->in_stock,
                $product->variants->count(),
                $product->created_at->format('Y-m-d H:i:s'),
                $product->updated_at->format('Y-m-d H:i:s')
            ];
        }

        $timestamp = now()->format('Y_m_d_H_i_s');
        $fileExtension = $format === 'excel' ? 'xlsx' : 'csv';
        $filename = "products_export_{$timestamp}.{$fileExtension}";

        return response()->json([
            'filename' => $filename,
            'format' => $format,
            'data' => $exportData,
            'count' => count($exportData) - 1 // Subtract header row
        ]);
    }

    /**
     * Get dropdown options for product form
     */
    public function formOptions(): JsonResponse
    {
        return response()->json([
            'categories' => Category::where('is_active', true)
                                  ->orderBy('name')
                                  ->get(['id', 'name']),
            'brands' => Brand::where('is_active', true)
                            ->orderBy('name')
                            ->get(['id', 'name']),
            'promotions' => Promotion::active()
                                   ->orderBy('name')
                                   ->get(['id', 'name', 'type', 'value'])
        ]);
    }

    /**
     * Bulk update products
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'action' => 'required|in:activate,deactivate,feature,unfeature,delete,update_category,update_brand',
            'category_id' => 'required_if:action,update_category|exists:categories,id',
            'brand_id' => 'required_if:action,update_brand|exists:brands,id'
        ]);

        $productIds = $validated['product_ids'];
        $action = $validated['action'];
        $updatedCount = 0;

        DB::beginTransaction();
        try {
            switch ($action) {
                case 'activate':
                    $updatedCount = Product::whereIn('id', $productIds)
                                          ->update(['is_active' => true]);
                    break;
                    
                case 'deactivate':
                    $updatedCount = Product::whereIn('id', $productIds)
                                          ->update(['is_active' => false]);
                    break;
                    
                case 'feature':
                    $updatedCount = Product::whereIn('id', $productIds)
                                          ->update(['is_featured' => true]);
                    break;
                    
                case 'unfeature':
                    $updatedCount = Product::whereIn('id', $productIds)
                                          ->update(['is_featured' => false]);
                    break;
                    
                case 'update_category':
                    $updatedCount = Product::whereIn('id', $productIds)
                                          ->update(['category_id' => $validated['category_id']]);
                    break;
                    
                case 'update_brand':
                    $updatedCount = Product::whereIn('id', $productIds)
                                          ->update(['brand_id' => $validated['brand_id']]);
                    break;
                    
                case 'delete':
                    // Delete variants first
                    ProductVariant::whereIn('product_id', $productIds)->delete();
                    
                    // Detach promotions
                    DB::table('promotion_products')
                      ->whereIn('product_id', $productIds)
                      ->delete();
                    
                    // Delete products
                    $updatedCount = Product::whereIn('id', $productIds)->delete();
                    break;
            }

            DB::commit();

            return response()->json([
                'message' => "Successfully {$action}d {$updatedCount} products",
                'updated_count' => $updatedCount
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Bulk update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate unique slug for product
     */
    private function generateUniqueSlug(string $name, int $excludeId = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (true) {
            $query = Product::where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            
            if (!$query->exists()) {
                break;
            }
            
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Generate SKU for product variant (now for color variants with multiple sizes)
     */
    private function generateVariantSku(Product $product, array $variantData): string
    {
        $productSlug = Str::upper(Str::substr($product->slug, 0, 3));
        $color = Str::upper(Str::substr($variantData['color'], 0, 3));
        
        // Generate base SKU for color variant (without size since we now have multiple sizes)
        $baseSku = "{$productSlug}-{$color}";
        $sku = $baseSku;
        $counter = 1;

        while (ProductVariant::where('sku', $sku)->exists()) {
            $sku = $baseSku . '-' . $counter;
            $counter++;
        }

        return $sku;
    }

    /**
     * Upload single product image
     */
    public function uploadImage(Request $request): JsonResponse
    {
        try {
            // Check file upload errors first
            if (!$request->hasFile('image')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No image file provided',
                    'debug' => [
                        'post_max_size' => ini_get('post_max_size'),
                        'upload_max_filesize' => ini_get('upload_max_filesize'),
                        'max_file_uploads' => ini_get('max_file_uploads'),
                        'memory_limit' => ini_get('memory_limit')
                    ]
                ], 400);
            }

            $file = $request->file('image');
            
            // Check for upload errors
            if ($file->getError() !== UPLOAD_ERR_OK) {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
                ];
                
                return response()->json([
                    'success' => false,
                    'message' => 'Upload error: ' . ($errorMessages[$file->getError()] ?? 'Unknown error'),
                    'error_code' => $file->getError(),
                    'file_size' => $file->getSize(),
                    'debug' => [
                        'post_max_size' => ini_get('post_max_size'),
                        'upload_max_filesize' => ini_get('upload_max_filesize'),
                        'max_file_uploads' => ini_get('max_file_uploads'),
                        'memory_limit' => ini_get('memory_limit')
                    ]
                ], 400);
            }

            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:20480', // 20MB max
                'type' => 'in:primary,hover,additional'
            ]);

            $image = $request->file('image');
            $type = $request->input('type', 'primary');
            
            // Generate unique filename
            $filename = time() . '_' . $type . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            
            // Store in public/storage/products directory
            $path = $image->storeAs('products', $filename, 'public');
            
            // Generate full URL
            $url = asset('storage/' . $path);
            
            return response()->json([
                'success' => true,
                'url' => $url,
                'path' => $path,
                'filename' => $filename,
                'type' => $type,
                'file_size' => $image->getSize(),
                'original_name' => $image->getClientOriginalName()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Image upload failed',
                'error' => $e->getMessage(),
                'debug' => [
                    'post_max_size' => ini_get('post_max_size'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'max_file_uploads' => ini_get('max_file_uploads'),
                    'memory_limit' => ini_get('memory_limit')
                ]
            ], 500);
        }
    }

    /**
     * Upload multiple product images
     */
    public function uploadMultipleImages(Request $request): JsonResponse
    {
        $request->validate([
            'images' => 'required|array|min:1|max:10', // Max 10 images
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:20480', // 20MB max per image
        ]);

        try {
            $uploadedImages = [];
            $images = $request->file('images');
            
            foreach ($images as $index => $image) {
                // Generate unique filename
                $filename = time() . '_img' . ($index + 1) . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                
                // Store in public/storage/products directory
                $path = $image->storeAs('products', $filename, 'public');
                
                // Generate full URL
                $url = asset('storage/' . $path);
                
                $uploadedImages[] = [
                    'url' => $url,
                    'path' => $path,
                    'filename' => $filename,
                    'order' => $index + 1,
                    'is_primary' => $index === 0 // First image is primary
                ];
            }
            
            return response()->json([
                'success' => true,
                'images' => $uploadedImages,
                'count' => count($uploadedImages),
                'primary_image' => $uploadedImages[0]['url'] ?? null,
                'hover_image' => $uploadedImages[1]['url'] ?? null,
                'additional_images' => array_slice(array_column($uploadedImages, 'url'), 2) // Images after first 2
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Multiple image upload failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate unique product code
     */
    private function generateUniqueProductCode(): string
    {
        $prefix = 'SIG'; // Signature prefix
        $year = date('Y');
        $counter = 1;
        
        // Get the highest existing product code for this year
        $lastProduct = Product::where('product_code', 'like', "{$prefix}{$year}%")
                             ->orderBy('product_code', 'desc')
                             ->first();
        
        if ($lastProduct) {
            // Extract the counter from the last product code
            $lastCode = $lastProduct->product_code;
            $lastCounter = (int) substr($lastCode, strlen($prefix . $year));
            $counter = $lastCounter + 1;
        }
        
        // Generate code with zero-padding (e.g., SIG2025001, SIG2025002, etc.)
        $productCode = $prefix . $year . str_pad($counter, 3, '0', STR_PAD_LEFT);
        
        // Ensure uniqueness (just in case)
        while (Product::where('product_code', $productCode)->exists()) {
            $counter++;
            $productCode = $prefix . $year . str_pad($counter, 3, '0', STR_PAD_LEFT);
        }
        
        return $productCode;
    }
}
