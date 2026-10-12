<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'discount_price',
        'category_id',
        'stock',
        'sku',
        'is_new_arrival',
        'is_sale',
        'is_sold_out',
        'specifications',
    ];

    protected $casts = [
        'is_new_arrival' => 'boolean',
        'is_sale' => 'boolean',
        'is_sold_out' => 'boolean',
        'specifications' => 'array',
        'price' => 'float',
        'discount_price' => 'float',
        'stock' => 'integer',
    ];

    protected $appends = [
        'category',
        'images',
        'images_data',
        'colors',
        'sizes',
        'discountPrice',
        'isSale',
        'isNewArrival',
        'isSoldOut',
        'effective_price',
    ];

    protected $hidden = ['categoryModel', 'imagesModel', 'colorsModel', 'sizesModel'];

    public function categoryModel()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function imagesModel()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function colorsModel()
    {
        return $this->hasMany(ProductColor::class);
    }

    public function sizesModel()
    {
        return $this->hasMany(ProductSize::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    // Accessors for frontend compatibility
    public function getCategoryAttribute()
    {
        return $this->categoryModel ? $this->categoryModel->name : null;
    }

    public function getImagesAttribute()
    {
        return $this->imagesModel->map(function ($img) {
            // Already a full URL (legacy data) — return as-is
            if (str_starts_with($img->image_url, 'http')) {
                return $img->image_url;
            }
            // Relative path — generate full URL via Storage disk config + APP_URL
            return \Illuminate\Support\Facades\Storage::disk('public')->url($img->image_url);
        })->toArray();
    }

    // Returns image objects with IDs for admin edit form
    public function getImagesDataAttribute()
    {
        return $this->imagesModel->map(function ($img) {
            $url = $img->image_url;
            if (!str_starts_with($url, 'http')) {
                $url = \Illuminate\Support\Facades\Storage::disk('public')->url($url);
            }
            return [
                'id' => $img->id,
                'image_url' => $url,
            ];
        })->toArray();
    }

    public function getColorsAttribute()
    {
        return $this->colorsModel->pluck('name')->toArray();
    }

    public function getSizesAttribute()
    {
        return $this->sizesModel->pluck('name')->toArray();
    }

    /**
     * Authoritative unit price to charge for this product.
     * Uses discount_price ONLY if is_sale is true, discount_price > 0, and discount_price < regular price.
     */
    public function getEffectivePriceAttribute(): float
    {
        $rawDiscount = $this->attributes['discount_price'] ?? null;
        if (
            (bool) ($this->attributes['is_sale'] ?? false) &&
            $rawDiscount !== null &&
            (float) $rawDiscount > 0 &&
            (float) $rawDiscount < (float) $this->price
        ) {
            return (float) $rawDiscount;
        }

        return (float) $this->price;
    }

    /**
     * Sanitized discount_price attribute (null when not on sale or invalid).
     */
    public function getDiscountPriceAttribute($value): ?float
    {
        if (
            !(bool) ($this->attributes['is_sale'] ?? false) ||
            $value === null ||
            (float) $value <= 0 ||
            (float) $value >= (float) $this->price
        ) {
            return null;
        }

        return (float) $value;
    }

    /**
     * CamelCase alias for discountPrice used by storefront components.
     */
    public function getDiscountPriceCamelAttribute(): ?float
    {
        return $this->discount_price;
    }

    public function getIsSaleAttribute($value): bool
    {
        return (bool) $value;
    }

    public function getIsNewArrivalAttribute($value): bool
    {
        return (bool) $value;
    }

    public function getIsSoldOutAttribute($value): bool
    {
        return (bool) $value;
    }
}
