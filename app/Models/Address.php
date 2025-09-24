<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'company',
        'address_line_1',
        'address_line_2',
        'township',
        'state_region',
        'postal_code',
        'country',
        'is_default'
    ];

    protected $casts = [
        'is_default' => 'boolean'
    ];

    /**
     * Get the user that owns the address.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    /**
     * Get the formatted address.
     */
    public function getFormattedAddressAttribute(): string
    {
        $address = $this->address_line_1;
        if ($this->address_line_2) {
            $address .= ', ' . $this->address_line_2;
        }
        if ($this->township) {
            $address .= ', ' . $this->township;
        }
        if ($this->state_region) {
            $address .= ', ' . $this->state_region;
        }
        if ($this->postal_code) {
            $address .= ' ' . $this->postal_code;
        }
        $address .= ', ' . $this->country;
        
        return $address;
    }

    /**
     * Get the delivery fee for this address.
     */
    public function getDeliveryFee()
    {
        if (!$this->township || !$this->state_region) {
            return null;
        }

        return TownshipDeliveryPrice::active()
            ->where('township_name', $this->township)
            ->where('state_region', $this->state_region)
            ->first();
    }
}
