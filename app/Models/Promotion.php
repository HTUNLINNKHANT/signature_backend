<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Promotion extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'type', // 'percentage', 'fixed_amount', 'buy_x_get_y'
        'value', // discount percentage or fixed amount
        'minimum_amount', // minimum order amount for promotion
        'maximum_discount', // maximum discount amount for percentage promotions
        'usage_limit', // total usage limit
        'usage_limit_per_customer', // usage limit per customer
        'used_count', // current usage count
        'start_date',
        'end_date',
        'is_active',
        'applies_to', // 'all', 'specific_products', 'specific_categories', 'specific_brands'
        'conditions', // JSON field for complex conditions
        'created_by'
    ];

    protected $casts = [
        'conditions' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'value' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'maximum_discount' => 'decimal:2'
    ];

    // Relationships
    public function products()
    {
        return $this->belongsToMany(Product::class, 'promotion_products');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'promotion_categories');
    }

    public function brands()
    {
        return $this->belongsToMany(Brand::class, 'promotion_brands');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('start_date', '<=', now())
                    ->where(function ($q) {
                        $q->whereNull('end_date')
                          ->orWhere('end_date', '>=', now());
                    });
    }

    public function scopeExpired($query)
    {
        return $query->where('end_date', '<', now());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>', now());
    }

    // Helper methods
    public function isActive()
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();
        
        if ($this->start_date && $this->start_date->gt($now)) {
            return false;
        }

        if ($this->end_date && $this->end_date->lt($now)) {
            return false;
        }

        return true;
    }

    public function isExpired()
    {
        return $this->end_date && $this->end_date->lt(now());
    }

    public function isUpcoming()
    {
        return $this->start_date && $this->start_date->gt(now());
    }

    public function canBeUsed($customerId = null)
    {
        if (!$this->isActive()) {
            return false;
        }

        // Check usage limits
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        // Check per-customer usage limit if customer is provided
        if ($customerId && $this->usage_limit_per_customer) {
            $customerUsage = $this->orders()->where('user_id', $customerId)->count();
            if ($customerUsage >= $this->usage_limit_per_customer) {
                return false;
            }
        }

        return true;
    }

    public function calculateDiscount($amount, $products = [])
    {
        if (!$this->canBeUsed()) {
            return 0;
        }

        // Check minimum amount
        if ($this->minimum_amount && $amount < $this->minimum_amount) {
            return 0;
        }

        $discount = 0;

        switch ($this->type) {
            case 'percentage':
                $discount = ($amount * $this->value) / 100;
                if ($this->maximum_discount) {
                    $discount = min($discount, $this->maximum_discount);
                }
                break;

            case 'fixed_amount':
                $discount = min($this->value, $amount);
                break;

            case 'buy_x_get_y':
                // Implementation for buy X get Y promotions
                // This would need more complex logic based on products
                break;
        }

        return round($discount, 2);
    }

    public function getStatusAttribute()
    {
        if ($this->isExpired()) {
            return 'expired';
        }
        
        if ($this->isUpcoming()) {
            return 'upcoming';
        }
        
        if ($this->isActive()) {
            return 'active';
        }
        
        return 'inactive';
    }

    public function getUsagePercentageAttribute()
    {
        if (!$this->usage_limit) {
            return 0;
        }
        
        return round(($this->used_count / $this->usage_limit) * 100, 2);
    }
}
