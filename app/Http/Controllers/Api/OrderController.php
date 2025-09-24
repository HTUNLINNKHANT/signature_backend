<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    /**
     * Display a listing of orders (admin) or user's orders (customer)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Order::with(['user', 'orderItems.product']);

        // If user is not admin, only show their own orders
        if (!$user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                               ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // Filter by payment status
        if ($request->has('payment_status') && $request->payment_status !== '') {
            $query->where('payment_status', $request->payment_status);
        }

        // Filter by payment method
        if ($request->has('payment_method') && $request->payment_method !== '') {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter by date range
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json($orders);
    }

    /**
     * Store a newly created order
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'shipping_address' => 'required|array',
            'billing_address' => 'required|array',
            'payment_method' => 'required|in:manual,card,bank_transfer',
            'payment_screenshot' => 'nullable|image|max:5120', // 5MB max
            'notes' => 'nullable|string|max:1000'
        ]);

        DB::beginTransaction();
        try {
            $subtotal = 0;
            $orderItems = [];

            // Calculate totals and prepare order items
            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                
                if (!$product->in_stock) {
                    return response()->json([
                        'message' => "Product '{$product->name}' is out of stock"
                    ], 400);
                }

                $itemTotal = $product->price * $item['quantity'];
                $subtotal += $itemTotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                    'total' => $itemTotal,
                    'product_snapshot' => $product->toArray()
                ];
            }

            $taxAmount = $subtotal * 0.08; // 8% tax
            $shippingAmount = $subtotal > 50 ? 0 : 9.99;
            $totalAmount = $subtotal + $taxAmount + $shippingAmount;

            // Handle payment screenshot upload
            $paymentScreenshotPath = null;
            if ($request->hasFile('payment_screenshot')) {
                $paymentScreenshotPath = $request->file('payment_screenshot')
                    ->store('payment_screenshots', 'public');
            }

            // Create order
            $order = Order::create([
                'user_id' => $request->user()->id,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'shipping_amount' => $shippingAmount,
                'total_amount' => $totalAmount,
                'payment_method' => $validated['payment_method'],
                'payment_screenshot' => $paymentScreenshotPath,
                'shipping_address' => $validated['shipping_address'],
                'billing_address' => $validated['billing_address'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending',
                'payment_status' => $validated['payment_method'] === 'manual' ? 'pending' : 'pending'
            ]);

            // Create order items
            foreach ($orderItems as $orderItem) {
                $orderItem['order_id'] = $order->id;
                OrderItem::create($orderItem);
            }

            DB::commit();

            return response()->json([
                'message' => 'Order created successfully',
                'order' => $order->load('orderItems.product')
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Failed to create order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified order
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $query = Order::with(['user', 'orderItems.product']);

        // If user is not admin, only allow viewing their own orders
        if (!$user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        $order = $query->findOrFail($id);

        return response()->json($order);
    }

    /**
     * Update order status (admin only)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:pending,processing,shipped,delivered,cancelled',
            'payment_status' => 'sometimes|in:pending,paid,failed,refunded',
            'notes' => 'nullable|string|max:1000'
        ]);

        $order = Order::findOrFail($id);
        $order->update($validated);

        // Auto-update timestamps based on status
        if (isset($validated['status'])) {
            switch ($validated['status']) {
                case 'shipped':
                    $order->update(['shipped_at' => now()]);
                    break;
                case 'delivered':
                    $order->update(['delivered_at' => now()]);
                    break;
            }
        }

        return response()->json([
            'message' => 'Order updated successfully',
            'order' => $order->fresh()->load('orderItems.product')
        ]);
    }

    /**
     * Cancel order
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $order = Order::findOrFail($id);

        // Only allow cancellation by order owner or admin
        if (!$user->isAdmin() && $order->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Only allow cancellation of pending or processing orders
        if (!in_array($order->status, ['pending', 'processing'])) {
            return response()->json([
                'message' => 'Cannot cancel order with status: ' . $order->status
            ], 400);
        }

        $order->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Order cancelled successfully',
            'order' => $order->fresh()
        ]);
    }

    /**
     * Approve payment (admin only)
     */
    public function approvePayment(Request $request, string $id): JsonResponse
    {
        $order = Order::findOrFail($id);

        if ($order->payment_status !== 'pending') {
            return response()->json([
                'message' => 'Payment is not pending approval'
            ], 400);
        }

        $order->markAsPaid();

        return response()->json([
            'message' => 'Payment approved successfully',
            'order' => $order->fresh()
        ]);
    }

    /**
     * Reject payment (admin only)
     */
    public function rejectPayment(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $order = Order::findOrFail($id);

        if ($order->payment_status !== 'pending') {
            return response()->json([
                'message' => 'Payment is not pending approval'
            ], 400);
        }

        $order->update([
            'payment_status' => 'failed',
            'payment_notes' => $validated['reason']
        ]);

        return response()->json([
            'message' => 'Payment rejected',
            'order' => $order->fresh()
        ]);
    }

    /**
     * Get pending payments for admin review
     */
    public function pendingPayments(): JsonResponse
    {
        $orders = Order::with(['user', 'orderItems.product'])
            ->where('payment_method', 'manual')
            ->where('payment_status', 'pending')
            ->whereNotNull('payment_screenshot')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($orders);
    }

    /**
     * Get order statistics for admin dashboard
     */
    public function statistics(): JsonResponse
    {
        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $processingOrders = Order::where('status', 'processing')->count();
        $shippedOrders = Order::where('status', 'shipped')->count();
        $deliveredOrders = Order::where('status', 'delivered')->count();
        $cancelledOrders = Order::where('status', 'cancelled')->count();

        $pendingPayments = Order::where('payment_status', 'pending')->count();
        $paidOrders = Order::where('payment_status', 'paid')->count();

        $totalRevenue = Order::where('payment_status', 'paid')->sum('total_amount');
        
        $thisMonthOrders = Order::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        
        $thisMonthRevenue = Order::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        $thisWeekOrders = Order::whereBetween('created_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ])->count();

        return response()->json([
            'total_orders' => $totalOrders,
            'pending_orders' => $pendingOrders,
            'processing_orders' => $processingOrders,
            'shipped_orders' => $shippedOrders,
            'delivered_orders' => $deliveredOrders,
            'cancelled_orders' => $cancelledOrders,
            'pending_payments' => $pendingPayments,
            'paid_orders' => $paidOrders,
            'total_revenue' => $totalRevenue,
            'this_month_orders' => $thisMonthOrders,
            'this_month_revenue' => $thisMonthRevenue,
            'this_week_orders' => $thisWeekOrders
        ]);
    }
}
