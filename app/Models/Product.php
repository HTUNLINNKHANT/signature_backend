<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'product_code',
        'name',
        'slug',
        'description',
        'price',
        'promotional_price',
        'promotional_price_set_at',
        'applied_promotion_id',
        'category', // Keep for backward compatibility
        'category_id',
        'brand_id',
        'hover_image',
        'product_images',
        'in_stock',
        'features',
        'is_active',
        'is_featured',
        'meta_title',
        'meta_description',
        'tags'
    ];

    protected $casts = [
        'features' => 'array',
        'tags' => 'array',
        'product_images' => 'array',
        'price' => 'decimal:2',
        'in_stock' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean'
    ];

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }


    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->orderBy('color');
    }

    public function activeVariants()
    {
        return $this->variants()->where('is_active', true);
    }

    public function availableVariants()
    {
        return $this->activeVariants()->where(function ($query) {
            $query->where('track_inventory', false)
                  ->orWhere('stock_quantity', '>', 0);
        });
    }

    public function promotions()
    {
        return $this->belongsToMany(Promotion::class, 'promotion_products');
    }

    public function activePromotions()
    {
        return $this->promotions()->active();
    }

    public function appliedPromotion()
    {
        return $this->belongsTo(Promotion::class, 'applied_promotion_id');
    }

    /**
     * Calculate and save promotional price when a promotion is applied
     */
    public function applyPromotion($promotionId)
    {
        $promotion = Promotion::find($promotionId);
        if (!$promotion) {
            return false;
        }

        $promotionalPrice = $this->calculatePromotionalPrice($promotion);
        
        $this->update([
            'promotional_price' => $promotionalPrice,
            'promotional_price_set_at' => now(),
            'applied_promotion_id' => $promotionId
        ]);

        // Also sync the many-to-many relationship
        $this->promotions()->sync([$promotionId]);

        return true;
    }

    /**
     * Remove promotional price
     */
    public function removePromotion()
    {
        $this->update([
            'promotional_price' => null,
            'promotional_price_set_at' => null,
            'applied_promotion_id' => null
        ]);

        // Remove from many-to-many relationship
        $this->promotions()->detach();
    }

    /**
     * Calculate promotional price based on promotion type and value
     */
    public function calculatePromotionalPrice($promotion)
    {
        $originalPrice = $this->price;
        
        switch ($promotion->type) {
            case 'percentage':
                $discountAmount = ($originalPrice * $promotion->value) / 100;
                // Apply maximum discount limit if set
                if ($promotion->maximum_discount && $discountAmount > $promotion->maximum_discount) {
                    $discountAmount = $promotion->maximum_discount;
                }
                return $originalPrice - $discountAmount;
                
            case 'fixed_amount':
                $discountedPrice = $originalPrice - $promotion->value;
                // Ensure price doesn't go below 0
                return max(0, $discountedPrice);
                
            case 'buy_x_get_y':
                // For buy X get Y, return original price (discount applied at checkout)
                return $originalPrice;
                
            default:
                return $originalPrice;
        }
    }

    /**
     * Get the effective price (promotional if available, otherwise regular price)
     */
    public function getEffectivePrice()
    {
        return $this->promotional_price ?? $this->price;
    }

    /**
     * Check if product has a saved promotional price
     */
    public function hasPromotionalPrice()
    {
        return !is_null($this->promotional_price);
    }





    // Scopes
    public function scopeInStock($query)
    {
        return $query->where('in_stock', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByBrand($query, $brandId)
    {
        return $query->where('brand_id', $brandId);
    }


    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeWithPromotions($query)
    {
        return $query->whereHas('activePromotions');
    }

    // Helper methods
    public function getDiscountedPrice()
    {
        $activePromotions = $this->activePromotions;
        
        if ($activePromotions->isEmpty()) {
            return $this->price;
        }

        $maxDiscount = 0;
        foreach ($activePromotions as $promotion) {
            $discount = $promotion->calculateDiscount($this->price);
            $maxDiscount = max($maxDiscount, $discount);
        }

        return max(0, $this->price - $maxDiscount);
    }

    public function hasActivePromotion()
    {
        return $this->activePromotions()->exists();
    }

    public function getDiscountPercentage()
    {
        if (!$this->hasActivePromotion()) {
            return 0;
        }

        $originalPrice = $this->price;
        $discountedPrice = $this->getDiscountedPrice();
        
        if ($originalPrice <= 0) {
            return 0;
        }

        return round((($originalPrice - $discountedPrice) / $originalPrice) * 100, 2);
    }

    // Stock management methods
    public function calculateTotalStock()
    {
        return $this->variants()->sum('stock_quantity');
    }

    public function updateStockFromVariants()
    {
        $totalStock = $this->calculateTotalStock();
        $this->update(['in_stock' => $totalStock]);
        return $totalStock;
    }

    // Image helper methods
    public function getAllImages()
    {
        $images = [];
        
        // Add images from product_images array (first one is primary)
        if ($this->product_images && is_array($this->product_images)) {
            foreach ($this->product_images as $index => $imageUrl) {
                $images[] = [
                    'url' => $imageUrl,
                    'type' => $index === 0 ? 'primary' : 'additional',
                    'is_primary' => $index === 0,
                    'order' => $index + 1
                ];
            }
        }
        
        // Add hover image
        if ($this->hover_image) {
            $images[] = [
                'url' => $this->hover_image,
                'type' => 'hover',
                'is_primary' => false
            ];
        }
        
        return $images;
    }
    
    public function getPrimaryImage()
    {
        // Return first image from product_images array
        if ($this->product_images && is_array($this->product_images) && count($this->product_images) > 0) {
            return $this->product_images[0];
        }
        return null;
    }
    
    public function getHoverImage()
    {
        return $this->hover_image;
    }
    
    public function getAdditionalImages()
    {
        return $this->product_images ?? [];
    }
    
    public function addProductImage($imageUrl)
    {
        $images = $this->product_images ?? [];
        $images[] = $imageUrl;
        $this->product_images = $images;
        return $this;
    }
    
    public function removeProductImage($imageUrl)
    {
        $images = $this->product_images ?? [];
        $images = array_values(array_filter($images, function($img) use ($imageUrl) {
            return $img !== $imageUrl;
        }));
        $this->product_images = $images;
        return $this;
    }
    
    public function setProductImages($imageUrls)
    {
        $this->product_images = is_array($imageUrls) ? $imageUrls : [];
        return $this;
    }
    
    public function hasMultipleImages()
    {
        $totalImages = 0;
        if ($this->hover_image) $totalImages++;
        if ($this->product_images && is_array($this->product_images)) {
            $totalImages += count($this->product_images);
        }
        return $totalImages > 1;
    }
    
    public function getImageCount()
    {
        $count = 0;
        if ($this->hover_image) $count++;
        if ($this->product_images && is_array($this->product_images)) {
            $count += count($this->product_images);
        }
        return $count;
    }
}
