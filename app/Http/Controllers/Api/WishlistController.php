<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WishlistController extends Controller
{
    /**
     * Display user's wishlist
     */
    public function index(Request $request): JsonResponse
    {
        $wishlist = Wishlist::with('product')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($wishlist);
    }

    /**
     * Add product to wishlist
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        $userId = $request->user()->id;
        $productId = $validated['product_id'];

        // Check if already in wishlist
        $existing = Wishlist::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Product already in wishlist'
            ], 409);
        }

        $wishlistItem = Wishlist::create([
            'user_id' => $userId,
            'product_id' => $productId
        ]);

        return response()->json([
            'message' => 'Product added to wishlist',
            'wishlist_item' => $wishlistItem->load('product')
        ], 201);
    }

    /**
     * Remove product from wishlist
     */
    public function destroy(Request $request, string $productId): JsonResponse
    {
        $deleted = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Product not found in wishlist'
            ], 404);
        }

        return response()->json([
            'message' => 'Product removed from wishlist'
        ]);
    }

    /**
     * Check if product is in user's wishlist
     */
    public function check(Request $request, string $productId): JsonResponse
    {
        $exists = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->exists();

        return response()->json([
            'in_wishlist' => $exists
        ]);
    }

    /**
     * Clear entire wishlist
     */
    public function clear(Request $request): JsonResponse
    {
        $deleted = Wishlist::where('user_id', $request->user()->id)->delete();

        return response()->json([
            'message' => "Removed {$deleted} items from wishlist"
        ]);
    }
}
