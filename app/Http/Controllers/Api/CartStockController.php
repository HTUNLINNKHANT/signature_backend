<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartStockController extends Controller
{
    /**
     * Reserve stock when adding items to cart
     */
    public function reserveStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.variant_id' => 'required|integer|exists:product_variants,id',
            'items.*.size_name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $results = [];
            
            foreach ($validated['items'] as $item) {
                $variant = ProductVariant::findOrFail($item['variant_id']);
                $sizeName = $item['size_name'];
                $quantity = $item['quantity'];
                
                // Get current size data
                $sizes = $variant->sizes ?? [];
                $sizeIndex = null;
                
                foreach ($sizes as $index => $size) {
                    if (($size['name'] ?? '') === $sizeName) {
                        $sizeIndex = $index;
                        break;
                    }
                }
                
                if ($sizeIndex === null) {
                    throw ValidationException::withMessages([
                        'items.' . array_search($item, $validated['items']) . '.size_name' => 'Size not found for this variant'
                    ]);
                }
                
                $currentSize = $sizes[$sizeIndex];
                $currentStock = $currentSize['stock_quantity'] ?? 0;
                $currentReserved = $currentSize['reserved_quantity'] ?? 0;
                $availableStock = $currentStock - $currentReserved;
                
                if ($availableStock < $quantity) {
                    throw ValidationException::withMessages([
                        'items.' . array_search($item, $validated['items']) . '.quantity' => "Only {$availableStock} items available in stock"
                    ]);
                }
                
                // Reserve the stock
                $sizes[$sizeIndex]['reserved_quantity'] = $currentReserved + $quantity;
                $variant->sizes = $sizes;
                $variant->save();
                
                $results[] = [
                    'variant_id' => $item['variant_id'],
                    'size_name' => $sizeName,
                    'quantity_reserved' => $quantity,
                    'available_stock' => $availableStock - $quantity,
                ];
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Stock reserved successfully',
                'data' => $results
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($e instanceof ValidationException) {
                throw $e;
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to reserve stock: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Release reserved stock when removing items from cart
     */
    public function releaseStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.variant_id' => 'required|integer|exists:product_variants,id',
            'items.*.size_name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $results = [];
            
            foreach ($validated['items'] as $item) {
                $variant = ProductVariant::findOrFail($item['variant_id']);
                $sizeName = $item['size_name'];
                $quantity = $item['quantity'];
                
                // Get current size data
                $sizes = $variant->sizes ?? [];
                $sizeIndex = null;
                
                foreach ($sizes as $index => $size) {
                    if (($size['name'] ?? '') === $sizeName) {
                        $sizeIndex = $index;
                        break;
                    }
                }
                
                if ($sizeIndex === null) {
                    continue; // Skip if size not found
                }
                
                $currentSize = $sizes[$sizeIndex];
                $currentReserved = $currentSize['reserved_quantity'] ?? 0;
                
                // Release the stock (ensure it doesn't go below 0)
                $newReserved = max(0, $currentReserved - $quantity);
                $sizes[$sizeIndex]['reserved_quantity'] = $newReserved;
                $variant->sizes = $sizes;
                $variant->save();
                
                $results[] = [
                    'variant_id' => $item['variant_id'],
                    'size_name' => $sizeName,
                    'quantity_released' => $currentReserved - $newReserved,
                    'available_stock' => $variant->getAvailableQuantity($sizeName),
                ];
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Stock released successfully',
                'data' => $results
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to release stock: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update reserved stock quantity (for quantity changes in cart)
     */
    public function updateReservedStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'variant_id' => 'required|integer|exists:product_variants,id',
            'size_name' => 'required|string',
            'old_quantity' => 'required|integer|min:0',
            'new_quantity' => 'required|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            $variant = ProductVariant::findOrFail($validated['variant_id']);
            $sizeName = $validated['size_name'];
            $oldQuantity = $validated['old_quantity'];
            $newQuantity = $validated['new_quantity'];
            
            // Get current size data
            $sizes = $variant->sizes ?? [];
            $sizeIndex = null;
            
            foreach ($sizes as $index => $size) {
                if (($size['name'] ?? '') === $sizeName) {
                    $sizeIndex = $index;
                    break;
                }
            }
            
            if ($sizeIndex === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Size not found for this variant'
                ], 404);
            }
            
            $currentSize = $sizes[$sizeIndex];
            $currentStock = $currentSize['stock_quantity'] ?? 0;
            $currentReserved = $currentSize['reserved_quantity'] ?? 0;
            
            // Calculate the difference
            $quantityDiff = $newQuantity - $oldQuantity;
            $newReserved = $currentReserved + $quantityDiff;
            
            // Check if we have enough available stock for increase
            if ($quantityDiff > 0) {
                $availableStock = $currentStock - $currentReserved;
                if ($availableStock < $quantityDiff) {
                    return response()->json([
                        'success' => false,
                        'message' => "Only {$availableStock} additional items available in stock"
                    ], 400);
                }
            }
            
            // Ensure reserved quantity doesn't go below 0
            $newReserved = max(0, $newReserved);
            
            // Update the reserved quantity
            $sizes[$sizeIndex]['reserved_quantity'] = $newReserved;
            $variant->sizes = $sizes;
            $variant->save();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Reserved stock updated successfully',
                'data' => [
                    'variant_id' => $validated['variant_id'],
                    'size_name' => $sizeName,
                    'old_reserved' => $currentReserved,
                    'new_reserved' => $newReserved,
                    'available_stock' => $variant->getAvailableQuantity($sizeName),
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update reserved stock: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get current stock availability for items
     */
    public function checkStockAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.variant_id' => 'required|integer|exists:product_variants,id',
            'items.*.size_name' => 'required|string',
        ]);

        try {
            $results = [];
            
            foreach ($validated['items'] as $item) {
                $variant = ProductVariant::findOrFail($item['variant_id']);
                $sizeName = $item['size_name'];
                
                $size = $variant->getSizeByName($sizeName);
                if ($size) {
                    $results[] = [
                        'variant_id' => $item['variant_id'],
                        'size_name' => $sizeName,
                        'total_stock' => $size['stock_quantity'] ?? 0,
                        'reserved_stock' => $size['reserved_quantity'] ?? 0,
                        'available_stock' => $variant->getAvailableQuantity($sizeName),
                        'in_stock' => $variant->isInStock($sizeName),
                    ];
                } else {
                    $results[] = [
                        'variant_id' => $item['variant_id'],
                        'size_name' => $sizeName,
                        'error' => 'Size not found',
                        'available_stock' => 0,
                        'in_stock' => false,
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $results
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check stock availability: ' . $e->getMessage()
            ], 500);
        }
    }
}
