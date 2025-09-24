<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class UserOrderController extends Controller
{
    /**
     * Get user's orders with pagination
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Order::with([
            'orderItems.product.category',
            'orderItems.product.brand',
            'orderItems.productVariant',
            'shippingAddress',
            'townshipDelivery'
        ])
        ->where('user_id', $user->id)
        ->orderByDesc('created_at');

        // Filter by status if provided
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // Filter by payment status if provided
        if ($request->has('payment_status') && $request->payment_status !== '') {
            $query->where('payment_status', $request->payment_status);
        }

        $orders = $query->paginate($request->get('per_page', 10));

        return response()->json($orders);
    }

    /**
     * Get user's recent orders (last 5)
     */
    public function recent(Request $request)
    {
        $user = $request->user();
        
        $orders = Order::with([
            'orderItems.product.category',
            'orderItems.product.brand',
            'orderItems.productVariant',
            'shippingAddress',
            'townshipDelivery'
        ])
        ->where('user_id', $user->id)
        ->orderByDesc('created_at')
        ->limit(5)
        ->get();

        return response()->json($orders);
    }

    /**
     * Get specific order details
     */
    public function show($id)
    {
        $user = request()->user();
        
        $order = Order::with([
            'orderItems.product.category',
            'orderItems.product.brand',
            'orderItems.productVariant',
            'shippingAddress',
            'townshipDelivery'
        ])
        ->where('user_id', $user->id)
        ->where('id', $id)
        ->first();

        return response()->json($order);
    }

    /**
     * Get order status information
     */
    public function getOrderStatus($orderNumber)
    {
        $user = request()->user();
        
        $order = Order::with([
            'orderItems.product.category',
            'orderItems.product.brand',
            'orderItems.productVariant',
            'shippingAddress',
            'townshipDelivery'
        ])
        ->where('user_id', $user->id)
        ->where('order_number', $orderNumber)
        ->first();

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        // Return order with status information
        return response()->json([
            'order' => $order,
            'status_info' => [
                'current_status' => $order->status,
                'payment_status' => $order->payment_status,
                'status_display' => $this->getStatusDisplay($order->status),
                'payment_status_display' => $this->getPaymentStatusDisplay($order->payment_status),
                'can_cancel' => $order->canBeCancelled(),
                'estimated_delivery' => $order->getEstimatedDeliveryDate(),
                'order_timeline' => $this->getOrderTimeline($order)
            ]
        ]);
    }

    /**
     * Get user-friendly status display
     */
    private function getStatusDisplay($status)
    {
        $statusMap = [
            'pending' => 'Order Received',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled'
        ];

        return $statusMap[$status] ?? ucfirst($status);
    }

    /**
     * Get user-friendly payment status display
     */
    private function getPaymentStatusDisplay($paymentStatus)
    {
        $statusMap = [
            'pending_approval' => 'Payment Under Review',
            'approved' => 'Payment Approved',
            'rejected' => 'Payment Rejected',
            'paid' => 'Payment Confirmed'
        ];

        return $statusMap[$paymentStatus] ?? ucfirst(str_replace('_', ' ', $paymentStatus));
    }

    /**
     * Get order timeline for tracking
     */
    private function getOrderTimeline($order)
    {
        $timeline = [
            [
                'status' => 'Order Placed',
                'date' => $order->created_at,
                'completed' => true,
                'description' => 'Your order has been received and is being reviewed.'
            ]
        ];

        if ($order->payment_status === 'approved' || $order->payment_status === 'paid') {
            $timeline[] = [
                'status' => 'Payment Confirmed',
                'date' => $order->updated_at,
                'completed' => true,
                'description' => 'Your payment has been verified and approved.'
            ];
        } elseif ($order->payment_status === 'rejected') {
            $timeline[] = [
                'status' => 'Payment Issue',
                'date' => $order->updated_at,
                'completed' => true,
                'description' => 'There was an issue with your payment. Please contact support.'
            ];
        } else {
            $timeline[] = [
                'status' => 'Payment Review',
                'date' => null,
                'completed' => false,
                'description' => 'Your payment is being reviewed by our team.'
            ];
        }

        if ($order->status === 'processing') {
            $timeline[] = [
                'status' => 'Processing',
                'date' => $order->updated_at,
                'completed' => true,
                'description' => 'Your order is being prepared for shipment.'
            ];
        } elseif (in_array($order->status, ['shipped', 'delivered'])) {
            $timeline[] = [
                'status' => 'Processing',
                'date' => $order->updated_at,
                'completed' => true,
                'description' => 'Your order has been prepared for shipment.'
            ];
        } else {
            $timeline[] = [
                'status' => 'Processing',
                'date' => null,
                'completed' => false,
                'description' => 'Your order will be prepared once payment is confirmed.'
            ];
        }

        if ($order->status === 'shipped') {
            $timeline[] = [
                'status' => 'Shipped',
                'date' => $order->shipped_at ?? $order->updated_at,
                'completed' => true,
                'description' => 'Your order has been shipped and is on its way.'
            ];
        } elseif ($order->status === 'delivered') {
            $timeline[] = [
                'status' => 'Shipped',
                'date' => $order->shipped_at ?? $order->updated_at,
                'completed' => true,
                'description' => 'Your order was shipped.'
            ];
        } else {
            $timeline[] = [
                'status' => 'Shipped',
                'date' => null,
                'completed' => false,
                'description' => 'Your order will be shipped once processing is complete.'
            ];
        }

        if ($order->status === 'delivered') {
            $timeline[] = [
                'status' => 'Delivered',
                'date' => $order->delivered_at ?? $order->updated_at,
                'completed' => true,
                'description' => 'Your order has been successfully delivered.'
            ];
        } else {
            $timeline[] = [
                'status' => 'Delivered',
                'date' => null,
                'completed' => false,
                'description' => 'Your order will be delivered soon.'
            ];
        }

        return $timeline;
    }
}
