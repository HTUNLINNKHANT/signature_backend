<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Address;
use App\Models\TownshipDeliveryPrice;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    /**
     * Calculate order totals including delivery fee
     */
    public function calculateTotals(Request $request)
    {
        $validated = $request->validate([
            'cart_items' => 'required|array',
            'cart_items.*.variant_id' => 'required|exists:product_variants,id',
            'cart_items.*.size' => 'required|string',
            'cart_items.*.quantity' => 'required|integer|min:1',
            'address_id' => 'required|exists:addresses,id'
        ]);

        $user = $request->user();
        $address = $user->addresses()->findOrFail($validated['address_id']);

        // Calculate subtotal from cart items
        $subtotal = 0;
        $orderItems = [];

        foreach ($validated['cart_items'] as $item) {
            $variant = ProductVariant::with('product')->findOrFail($item['variant_id']);
            
            // Get size data from JSON
            $sizes = $variant->sizes ?? [];
            $sizeData = collect($sizes)->firstWhere('name', $item['size']);
            
            if (!$sizeData) {
                return response()->json([
                    'error' => "Size {$item['size']} not available for this variant"
                ], 400);
            }

            // Check stock availability
            if ($sizeData['stock_quantity'] < $item['quantity']) {
                return response()->json([
                    'error' => "Insufficient stock for {$variant->product->name} - {$variant->color_name} - {$item['size']}"
                ], 400);
            }

            $price = $sizeData['price'] ?? $variant->price ?? $variant->product->price;
            $itemTotal = $price * $item['quantity'];
            $subtotal += $itemTotal;

            $orderItems[] = [
                'variant' => $variant,
                'size' => $item['size'],
                'quantity' => $item['quantity'],
                'price' => $price,
                'total' => $itemTotal
            ];
        }

        // Calculate delivery fee
        $deliveryFee = 0;
        $townshipDelivery = null;

        if ($address->township && $address->state_region) {
            $townshipDelivery = TownshipDeliveryPrice::active()
                ->where('township_name', $address->township)
                ->where('state_region', $address->state_region)
                ->first();

            if ($townshipDelivery) {
                $deliveryFee = $townshipDelivery->delivery_price;
            }
        }

        $total = $subtotal + $deliveryFee;

        return response()->json([
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'total' => $total,
            'order_items' => $orderItems,
            'township_delivery' => $townshipDelivery,
            'address' => $address
        ]);
    }

    /**
     * Place order with transaction image upload
     */
    public function placeOrder(Request $request)
    {
        // Parse cart_items JSON if it's a string
        $cartItems = $request->input('cart_items');
        if (is_string($cartItems)) {
            $cartItems = json_decode($cartItems, true);
            $request->merge(['cart_items' => $cartItems]);
        }

        // Check if payment screenshot file exists
        if (!$request->hasFile('payment_screenshot')) {
            return response()->json([
                'message' => 'Payment screenshot is required.',
                'errors' => ['payment_screenshot' => ['The payment screenshot field is required.']]
            ], 422);
        }

        $validated = $request->validate([
            'cart_items' => 'required|array',
            'cart_items.*.variant_id' => 'required|exists:product_variants,id',
            'cart_items.*.size' => 'required|string',
            'cart_items.*.quantity' => 'required|integer|min:1',
            'shipping_address_id' => 'required|exists:addresses,id',
            'payment_screenshot' => 'file|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
            'payment_notes' => 'nullable|string|max:1000'
        ]);

        $user = $request->user();
        $address = $user->addresses()->findOrFail($validated['shipping_address_id']);

        try {
            return DB::transaction(function () use ($request, $validated, $user, $address) {
            // Calculate totals
            $subtotal = 0;
            $orderItems = [];

            foreach ($validated['cart_items'] as $item) {
                $variant = ProductVariant::with('product')->findOrFail($item['variant_id']);
                
                // Get size data from JSON
                $sizes = $variant->sizes ?? [];
                $sizeData = collect($sizes)->firstWhere('name', $item['size']);
                
                if (!$sizeData) {
                    throw new \Exception("Size {$item['size']} not available for this variant");
                }

                // Check stock availability
                if ($sizeData['stock_quantity'] < $item['quantity']) {
                    throw new \Exception("Insufficient stock for {$variant->product->name} - {$variant->color_name} - {$item['size']}");
                }

                $price = $sizeData['price'] ?? $variant->price ?? $variant->product->price;
                $itemTotal = $price * $item['quantity'];
                $subtotal += $itemTotal;

                $orderItems[] = [
                    'variant' => $variant,
                    'size' => $item['size'],
                    'quantity' => $item['quantity'],
                    'price' => $price,
                    'total' => $itemTotal,
                    'size_data' => $sizeData
                ];
            }

            // Calculate delivery fee
            $deliveryFee = 0;
            $townshipDeliveryId = null;

            if ($address->township && $address->state_region) {
                $townshipDelivery = TownshipDeliveryPrice::active()
                    ->where('township_name', $address->township)
                    ->where('state_region', $address->state_region)
                    ->first();

                if ($townshipDelivery) {
                    $deliveryFee = $townshipDelivery->delivery_price;
                    $townshipDeliveryId = $townshipDelivery->id;
                }
            }

            $total = $subtotal + $deliveryFee;

            // Upload payment screenshot
            $paymentScreenshotPath = null;
            if ($request->hasFile('payment_screenshot')) {
                $file = $request->file('payment_screenshot');
                $filename = 'payment_' . Str::random(20) . '.' . $file->getClientOriginalExtension();
                $paymentScreenshotPath = $file->storeAs('payment_screenshots', $filename, 'public');
            }

            // Create order
            try {
                $order = Order::create([
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'subtotal' => $subtotal,
                    'tax_amount' => 0,
                    'shipping_amount' => 0,
                    'discount_amount' => 0,
                    'delivery_fee' => $deliveryFee,
                    'total_amount' => $total,
                    'currency' => 'MMK',
                    'payment_status' => 'pending_approval',
                    'payment_method' => 'manual',
                    'payment_screenshot' => $paymentScreenshotPath,
                    'payment_notes' => $validated['payment_notes'] ?? null,
                    'shipping_address_id' => $address->id,
                    'shipping_address' => json_encode($address->toArray()),
                    'billing_address' => null, // Set as nullable since it's the same as shipping
                    'township_delivery_id' => $townshipDeliveryId
                ]);
            } catch (\Exception $e) {
                Log::error('Order creation failed: ' . $e->getMessage());
                return response()->json([
                    'error' => 'Failed to create order',
                    'debug' => $e->getMessage()
                ], 500);
            }

            // Create order items and update stock
            foreach ($orderItems as $item) {
                $product = $item['variant']->product;
                $variant = $item['variant'];
                
                // Get primary product image
                $primaryImage = null;
                if ($product->product_images && is_array($product->product_images) && count($product->product_images) > 0) {
                    $primaryImage = $product->product_images[0];
                } elseif ($product->hover_image) {
                    $primaryImage = $product->hover_image;
                }
                
                // Create product snapshot with essential data
                $productSnapshot = [
                    'product_name' => $product->name,
                    'product_code' => $product->product_code,
                    'product_slug' => $product->slug,
                    'product_primary_image' => $primaryImage,
                    'variant_color' => $variant->color,
                    'variant_color_code' => $variant->color_code,
                    'variant_sku' => $variant->sku,
                    'size_name' => $item['size'],
                    'price_at_time' => $item['price'],
                    'created_at' => now()->toISOString()
                ];
                
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $product->name,
                    'variant_color' => $variant->color,
                    'variant_size' => $item['size'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['total'],
                    'product_snapshot' => $productSnapshot
                ]);

                // Update stock - reduce available quantity
                $variant = $item['variant'];
                $sizes = $variant->sizes ?? [];
                
                foreach ($sizes as &$size) {
                    if ($size['name'] === $item['size']) {
                        $size['stock_quantity'] -= $item['quantity'];
                        $size['reserved_quantity'] = ($size['reserved_quantity'] ?? 0) + $item['quantity'];
                        break;
                    }
                }
                
                $variant->sizes = $sizes;
                $variant->save();
            }

            // Order confirmation email disabled per user request
            // Mail::to($user->email)->send(new \App\Mail\OrderConfirmation($order));

            return response()->json([
                'message' => 'Order placed successfully. Your payment is pending approval.',
                'order' => $order->load(['orderItems', 'shippingAddress', 'townshipDelivery']),
                'order_number' => $order->order_number
            ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Order placement failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to place order'], 500);
        }
    }
}
