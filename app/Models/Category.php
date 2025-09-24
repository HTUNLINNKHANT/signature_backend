<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'type',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    // Relationship: Category has many Products
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // Parent-Child relationships for hierarchy
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // Get all descendants (children, grandchildren, etc.)
    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    // Check if this category is a parent (has children)
    public function isParent()
    {
        return $this->children()->count() > 0;
    }

    // Check if this category is a child (has parent)
    public function isChild()
    {
        return !is_null($this->parent_id);
    }

    // Get the full category path (Parent > Child)
    public function getFullPathAttribute()
    {
        if ($this->parent) {
            return $this->parent->name . ' > ' . $this->name;
        }
        return $this->name;
    }
}
