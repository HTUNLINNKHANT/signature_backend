<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'discount_type',
        'discount_value',
        'minimum_amount',
        'maximum_discount',
        'usage_limit',
        'used_count',
        'is_active',
        'starts_at',
        'expires_at',
        'description',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'maximum_discount' => 'decimal:2',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'used_count' => 'integer',
        'usage_limit' => 'integer',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValid($query)
    {
        $now = Carbon::now();
        return $query->active()
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            });
    }

    public function scopeAvailable($query)
    {
        return $query->valid()
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                  ->orWhereRaw('used_count < usage_limit');
            });
    }

    // Helper methods
    public function isValid()
    {
        if (!$this->is_active) {
            return false;
        }

        $now = Carbon::now();

        if ($this->starts_at && $this->starts_at->gt($now)) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->lt($now)) {
            return false;
        }

        return true;
    }

    public function isAvailable()
    {
        if (!$this->isValid()) {
            return false;
        }

        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function canBeUsedForAmount($amount)
    {
        if (!$this->isAvailable()) {
            return false;
        }

        if ($this->minimum_amount && $amount < $this->minimum_amount) {
            return false;
        }

        return true;
    }

    public function calculateDiscount($amount)
    {
        if (!$this->canBeUsedForAmount($amount)) {
            return 0;
        }

        if ($this->discount_type === 'percentage') {
            $discount = ($amount * $this->discount_value) / 100;
            
            if ($this->maximum_discount) {
                $discount = min($discount, $this->maximum_discount);
            }
            
            return $discount;
        }

        if ($this->discount_type === 'fixed_amount') {
            return min($this->discount_value, $amount);
        }

        return 0;
    }

    public function incrementUsage()
    {
        $this->increment('used_count');
    }

    public function getComputedStatusAttribute()
    {
        if (!$this->is_active) {
            return 'inactive';
        }

        $now = Carbon::now();

        if ($this->starts_at && $this->starts_at->gt($now)) {
            return 'scheduled';
        }

        if ($this->expires_at && $this->expires_at->lt($now)) {
            return 'expired';
        }

        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return 'used_up';
        }

        return 'active';
    }

    public function getComputedDiscountDisplayAttribute()
    {
        if ($this->discount_type === 'percentage') {
            // For percentage, show the value as is, with a '%' sign.
            return rtrim(rtrim(number_format((float) $this->discount_value, 2), '0'), '.') . '%';
        }

        // For fixed amount, format as an integer with 'Ks' currency symbol.
        return number_format((float) $this->discount_value, 0) . ' Ks';
    }
}
