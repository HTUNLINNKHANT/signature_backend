<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'date_of_purchase',
        'email_address',
        'phone_number',
        'product_to_exchange_name',
        'size',
        'item_color',
        'reason_for_exchange',
        'replacement_request_item',
        'replacement_request_name',
        'additional_comments',
        'status',
        'admin_notes',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'date_of_purchase' => 'date',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the admin user who processed this request
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Scope for filtering by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope for searching by order number or email
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('order_number', 'like', "%{$search}%")
              ->orWhere('email_address', 'like', "%{$search}%")
              ->orWhere('product_to_exchange_name', 'like', "%{$search}%")
              ->orWhere('replacement_request_name', 'like', "%{$search}%");
        });
    }

    /**
     * Get status badge color for UI
     */
    public function getStatusBadgeColorAttribute()
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            'processing' => 'blue',
            'completed' => 'emerald',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get formatted status for display
     */
    public function getFormattedStatusAttribute()
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }
}
