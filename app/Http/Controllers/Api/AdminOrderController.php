<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminOrderController extends Controller
{
    /**
     * Get orders with filtering and pagination
     */
    public function index(Request $request)
    {
        $query = Order::with(['user', 'orderItems.product', 'shippingAddress', 'townshipDelivery', 'approvedBy'])
            ->orderByDesc('created_at');

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // Filter by payment status
        if ($request->has('payment_status') && $request->payment_status !== '') {
            $query->where('payment_status', $request->payment_status);
        }

        // Search by order number or customer name/email
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                               ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by date range
        if ($request->has('date_from') && $request->date_from !== '') {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to !== '') {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->paginate($request->get('per_page', 15));

        return response()->json($orders);
    }

    /**
     * Get order statistics
     */
    public function statistics()
    {
        $stats = [
            'total_orders' => Order::count(),
            'pending_approval' => Order::where('payment_status', 'pending_approval')->count(),
            'processing' => Order::where('status', 'processing')->count(),
            'shipped' => Order::where('status', 'shipped')->count(),
            'delivered' => Order::where('status', 'delivered')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
            'total_revenue' => Order::whereIn('payment_status', ['approved', 'paid'])->sum('total_amount'),
            'pending_revenue' => Order::where('payment_status', 'pending_approval')->sum('total_amount'),
            'this_month_orders' => Order::whereMonth('created_at', now()->month)
                                       ->whereYear('created_at', now()->year)
                                       ->count(),
            'this_week_orders' => Order::whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count()
        ];

        return response()->json($stats);
    }

    /**
     * Show specific order details
     */
    public function show($id)
    {
        $order = Order::with([
            'user',
            'orderItems.product.productImages',
            'orderItems.productVariant',
            'shippingAddress',
            'townshipDelivery',
            'approvedBy'
        ])->findOrFail($id);

        return response()->json($order);
    }

    /**
     * Approve order payment
     */
    public function approvePayment(Request $request, $id)
    {
        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:1000'
        ]);

        $order = Order::findOrFail($id);

        if (!$order->isPendingPaymentApproval()) {
            return response()->json([
                'error' => 'Order is not pending payment approval'
            ], 400);
        }

        $admin = $request->user();
        $order->approvePayment($admin);

        // Update admin notes if provided
        if (isset($validated['admin_notes'])) {
            $order->update(['payment_notes' => $validated['admin_notes']]);
        }

        // Order approved email disabled per user request
        // Mail::to($order->user->email)->send(new \App\Mail\OrderApproved($order));

        return response()->json([
            'message' => 'Payment approved successfully',
            'order' => $order->fresh(['user', 'orderItems', 'approvedBy'])
        ]);
    }

    /**
     * Reject order payment
     */
    public function rejectPayment(Request $request, $id)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000'
        ]);

        $order = Order::findOrFail($id);

        if (!$order->isPendingPaymentApproval()) {
            return response()->json([
                'error' => 'Order is not pending payment approval'
            ], 400);
        }

        $admin = $request->user();
        $order->rejectPayment($admin, $validated['rejection_reason']);

        // Order rejected email disabled per user request
        // Mail::to($order->user->email)->send(new \App\Mail\OrderRejected($order));

        return response()->json([
            'message' => 'Payment rejected successfully',
            'order' => $order->fresh(['user', 'orderItems', 'approvedBy'])
        ]);
    }

    /**
     * Update order status
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled',
            'notes' => 'nullable|string|max:1000'
        ]);

        $order = Order::findOrFail($id);

        $order->update([
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? $order->notes
        ]);

        // Update shipped_at or delivered_at timestamps
        if ($validated['status'] === 'shipped' && !$order->shipped_at) {
            $order->markAsShipped();
        } elseif ($validated['status'] === 'delivered' && !$order->delivered_at) {
            $order->markAsDelivered();
        }

        return response()->json([
            'message' => 'Order status updated successfully',
            'order' => $order->fresh(['user', 'orderItems', 'approvedBy'])
        ]);
    }

    /**
     * Get payment screenshot
     */
    public function getPaymentScreenshot($id)
    {
        $order = Order::findOrFail($id);

        if (!$order->payment_screenshot) {
            return response()->json(['error' => 'No payment screenshot found'], 404);
        }

        $path = storage_path('app/public/' . $order->payment_screenshot);

        if (!file_exists($path)) {
            return response()->json(['error' => 'Payment screenshot file not found'], 404);
        }

        return response()->file($path);
    }

    /**
     * Download payment screenshot
     */
    public function downloadPaymentScreenshot($id)
    {
        $order = Order::findOrFail($id);

        if (!$order->payment_screenshot) {
            return response()->json(['error' => 'No payment screenshot found'], 404);
        }

        $path = storage_path('app/public/' . $order->payment_screenshot);

        if (!file_exists($path)) {
            return response()->json(['error' => 'Payment screenshot file not found'], 404);
        }

        $filename = 'payment_' . $order->order_number . '.' . pathinfo($order->payment_screenshot, PATHINFO_EXTENSION);

        return response()->download($path, $filename);
    }
}
