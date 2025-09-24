<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WholesaleApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_name',
        'business_type',
        'full_name',
        'email',
        'phone_number',
        'country_region',
        'city',
        'state_province',
        'product_interests',
        'additional_information',
        'shipping_address',
        'status',
        'admin_notes',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'product_interests' => 'array',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who reviewed this application
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope for filtering by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for pending applications
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for approved applications
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Get formatted product interests as string
     */
    public function getProductInterestsStringAttribute(): string
    {
        if (!$this->product_interests) {
            return '';
        }
        
        $interests = $this->product_interests;
        return implode(', ', array_map('ucfirst', $interests));
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            'under_review' => 'blue',
            default => 'gray'
        };
    }

    /**
     * Check if application can be reviewed
     */
    public function canBeReviewed(): bool
    {
        return in_array($this->status, ['pending', 'under_review']);
    }
}
