<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'status',
        'subtotal',
        'tax_amount',
        'shipping_amount',
        'discount_amount',
        'delivery_fee',
        'total_amount',
        'currency',
        'payment_status',
        'payment_method',
        'payment_screenshot',
        'payment_notes',
        'shipping_address',
        'billing_address',
        'township_delivery_id',
        'shipping_address_id',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'notes',
        'shipped_at',
        'delivered_at'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'approved_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = 'ORD-' . strtoupper(Str::random(8));
            }
        });
    }

    /**
     * Get the user that owns the order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the order items for the order.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the order items with product details.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->with('product');
    }

    /**
     * Get the township delivery price for the order.
     */
    public function townshipDelivery(): BelongsTo
    {
        return $this->belongsTo(TownshipDeliveryPrice::class, 'township_delivery_id');
    }

    /**
     * Get the shipping address for the order.
     */
    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    /**
     * Get the admin who approved the order.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if order is pending payment approval.
     */
    public function isPendingPaymentApproval(): bool
    {
        return $this->payment_method === 'manual' && 
               $this->payment_status === 'pending_approval' && 
               !empty($this->payment_screenshot);
    }

    /**
     * Approve the order payment.
     */
    public function approvePayment(User $admin): void
    {
        $this->update([
            'payment_status' => 'approved',
            'status' => 'processing',
            'approved_by' => $admin->id,
            'approved_at' => now()
        ]);
    }

    /**
     * Reject the order payment.
     */
    public function rejectPayment(User $admin, string $reason): void
    {
        $this->update([
            'payment_status' => 'rejected',
            'status' => 'cancelled',
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'rejection_reason' => $reason
        ]);
    }

    /**
     * Mark order as paid.
     */
    public function markAsPaid(): void
    {
        $this->update([
            'payment_status' => 'paid',
            'status' => 'processing'
        ]);
    }

    /**
     * Mark order as shipped.
     */
    public function markAsShipped(): void
    {
        $this->update([
            'status' => 'shipped',
            'shipped_at' => now()
        ]);
    }

    /**
     * Mark order as delivered.
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now()
        ]);
    }

    /**
     * Check if order can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        // Order can be cancelled if:
        // 1. Payment is pending approval
        // 2. Payment is rejected
        // 3. Status is pending and payment is not approved
        return in_array($this->payment_status, ['pending_approval', 'rejected']) ||
               ($this->status === 'pending' && $this->payment_status !== 'approved');
    }

    /**
     * Get estimated delivery date.
     */
    public function getEstimatedDeliveryDate(): ?string
    {
        if ($this->status === 'delivered') {
            return $this->delivered_at ? $this->delivered_at->format('Y-m-d') : null;
        }

        if ($this->status === 'shipped' && $this->shipped_at) {
            // Add estimated delivery days from township delivery
            $estimatedDays = $this->townshipDelivery?->estimated_delivery_days ?? 3;
            return $this->shipped_at->addDays($estimatedDays)->format('Y-m-d');
        }

        if ($this->payment_status === 'approved' || $this->payment_status === 'paid') {
            // Add processing time (2 days) + delivery time
            $estimatedDays = ($this->townshipDelivery?->estimated_delivery_days ?? 3) + 2;
            return now()->addDays($estimatedDays)->format('Y-m-d');
        }

        return null; // Cannot estimate if payment not approved
    }
}
