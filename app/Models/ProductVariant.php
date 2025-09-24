<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'color',
        'color_code',
        'sizes',
        'price',
        'compare_at_price',
        'is_active',
    ];

    protected $casts = [
        'sizes' => 'array',
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }



    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->whereRaw('JSON_EXTRACT(sizes, "$[*].stock_quantity") > 0');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->active()->whereRaw('JSON_EXTRACT(sizes, "$[*].track_inventory") = false OR JSON_EXTRACT(sizes, "$[*].stock_quantity") > 0');
    }

    public function scopeByColor(Builder $query, string $color): Builder
    {
        return $query->where('color', $color);
    }

    public function scopeBySize(Builder $query, string $size): Builder
    {
        return $query->whereRaw('JSON_SEARCH(sizes, "one", ?, null, "$[*].name") IS NOT NULL', [$size]);
    }

    public function scopeByColorAndSize(Builder $query, string $color, string $size): Builder
    {
        return $query->where('color', $color)
                    ->whereRaw('JSON_SEARCH(sizes, "one", ?, null, "$[*].name") IS NOT NULL', [$size]);
    }

    // Helper methods
    public function getAvailableQuantity(string $sizeName = null): int
    {
        if ($sizeName) {
            $size = $this->getSizeByName($sizeName);
            if ($size) {
                return max(0, ($size['stock_quantity'] ?? 0) - ($size['reserved_quantity'] ?? 0));
            }
            return 0;
        }
        
        // Return total available quantity across all sizes
        $total = 0;
        foreach ($this->sizes ?? [] as $size) {
            $total += max(0, ($size['stock_quantity'] ?? 0) - ($size['reserved_quantity'] ?? 0));
        }
        return $total;
    }

    public function isInStock(string $sizeName = null): bool
    {
        if ($sizeName) {
            $size = $this->getSizeByName($sizeName);
            if ($size) {
                return !($size['track_inventory'] ?? true) || $this->getAvailableQuantity($sizeName) > 0;
            }
            return false;
        }
        
        // Check if any size is in stock
        foreach ($this->sizes ?? [] as $size) {
            if (!($size['track_inventory'] ?? true) || (($size['stock_quantity'] ?? 0) - ($size['reserved_quantity'] ?? 0)) > 0) {
                return true;
            }
        }
        return false;
    }

    public function getEffectivePrice(string $sizeName = null): float
    {
        if ($sizeName) {
            $size = $this->getSizeByName($sizeName);
            if ($size && isset($size['price'])) {
                return (float) $size['price'];
            }
        }
        
        return $this->price ?? $this->product->price;
    }

    /**
     * Get promotional price for this variant based on active promotions
     */
    public function getPromotionalPrice(string $sizeName = null): ?float
    {
        $activePromotion = $this->product->activePromotions()->first();
        if (!$activePromotion) {
            return null;
        }

        $originalPrice = $this->getEffectivePrice($sizeName);
        return $this->calculatePromotionalPrice($originalPrice, $activePromotion);
    }

    /**
     * Calculate promotional price based on promotion type and value
     */
    private function calculatePromotionalPrice(float $originalPrice, $promotion): float
    {
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
                
            default:
                return $originalPrice;
        }
    }

    /**
     * Check if this variant has active promotions
     */
    public function hasActivePromotion(): bool
    {
        return $this->product->activePromotions()->exists();
    }

    /**
     * Get discount percentage for this variant
     */
    public function getDiscountPercentage(string $sizeName = null): ?float
    {
        $originalPrice = $this->getEffectivePrice($sizeName);
        $promotionalPrice = $this->getPromotionalPrice($sizeName);
        
        if (!$promotionalPrice || $originalPrice <= 0) {
            return null;
        }
        
        return (($originalPrice - $promotionalPrice) / $originalPrice) * 100;
    }

    public function getDisplayName(): string
    {
        $sizeNames = array_column($this->sizes ?? [], 'name');
        return "{$this->color} / " . implode(', ', $sizeNames);
    }

    public function getColorDisplay(): array
    {
        return [
            'name' => $this->color,
            'code' => $this->color_code,
        ];
    }

    public function getSizeDisplay(): array
    {
        return array_map(function($size) {
            return [
                'name' => $size['name'] ?? '',
                'stock_quantity' => $size['stock_quantity'] ?? 0,
                'reserved_quantity' => $size['reserved_quantity'] ?? 0,
                'available_quantity' => max(0, ($size['stock_quantity'] ?? 0) - ($size['reserved_quantity'] ?? 0)),
                'price' => $size['price'] ?? null,
                'compare_at_price' => $size['compare_at_price'] ?? null,
                'weight' => $size['weight'] ?? null,
                'barcode' => $size['barcode'] ?? null,
                'is_active' => $size['is_active'] ?? true,
                'track_inventory' => $size['track_inventory'] ?? true,
            ];
        }, $this->sizes ?? []);
    }

    public function getSizeByName(string $sizeName): ?array
    {
        foreach ($this->sizes ?? [] as $size) {
            if (($size['name'] ?? '') === $sizeName) {
                return $size;
            }
        }
        return null;
    }

    public function hasSizes(): bool
    {
        return !empty($this->sizes) && is_array($this->sizes);
    }

    public function getSizeNames(): array
    {
        return array_column($this->sizes ?? [], 'name');
    }

    public function isSameVariant(string $color, string $size): bool
    {
        if ($this->color !== $color) {
            return false;
        }
        
        return $this->getSizeByName($size) !== null;
    }

    public function generateSku(string $sizeName = null): string
    {
        $productSku = $this->product->sku ?? 'PROD';
        $colorCode = strtoupper(substr($this->color, 0, 3));
        
        if ($sizeName) {
            $sizeCode = strtoupper($sizeName);
            return "{$productSku}-{$colorCode}-{$sizeCode}";
        }
        
        return "{$productSku}-{$colorCode}";
    }

    public function addSize(array $sizeData): void
    {
        $sizes = $this->sizes ?? [];
        $sizes[] = array_merge([
            'name' => '',
            'stock_quantity' => 0,
            'reserved_quantity' => 0,
            'price' => null,
            'compare_at_price' => null,
            'weight' => null,
            'barcode' => null,
            'is_active' => true,
            'track_inventory' => true,
        ], $sizeData);
        
        $this->sizes = $sizes;
    }

    public function updateSize(string $sizeName, array $sizeData): bool
    {
        $sizes = $this->sizes ?? [];
        
        foreach ($sizes as $index => $size) {
            if (($size['name'] ?? '') === $sizeName) {
                $sizes[$index] = array_merge($size, $sizeData);
                $this->sizes = $sizes;
                return true;
            }
        }
        
        return false;
    }

    public function removeSize(string $sizeName): bool
    {
        $sizes = $this->sizes ?? [];
        $originalCount = count($sizes);
        
        $sizes = array_filter($sizes, function($size) use ($sizeName) {
            return ($size['name'] ?? '') !== $sizeName;
        });
        
        if (count($sizes) < $originalCount) {
            $this->sizes = array_values($sizes); // Re-index array
            return true;
        }
        
        return false;
    }
}
