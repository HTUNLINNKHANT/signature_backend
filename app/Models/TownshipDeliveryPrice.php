<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TownshipDeliveryPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'township_name',
        'township_name_mm',
        'state_region',
        'state_region_mm',
        'delivery_price',
        'estimated_days',
        'is_active',
        'notes'
    ];

    protected $casts = [
        'delivery_price' => 'decimal:2',
        'estimated_days' => 'integer',
        'is_active' => 'boolean'
    ];

    /**
     * Scope to get only active townships
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by state/region
     */
    public function scopeByStateRegion($query, $stateRegion)
    {
        return $query->where('state_region', $stateRegion);
    }

    /**
     * Get formatted delivery price
     */
    public function getFormattedDeliveryPriceAttribute()
    {
        return number_format((float) $this->delivery_price, 0) . ' Ks';
    }

    /**
     * Get full township name (English and Myanmar)
     */
    public function getFullTownshipNameAttribute()
    {
        if ($this->township_name_mm) {
            return $this->township_name . ' (' . $this->township_name_mm . ')';
        }
        return $this->township_name;
    }

    /**
     * Get full state/region name (English and Myanmar)
     */
    public function getFullStateRegionAttribute()
    {
        if ($this->state_region_mm) {
            return $this->state_region . ' (' . $this->state_region_mm . ')';
        }
        return $this->state_region;
    }
}
