<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_image',
        'product_sku',
        'quantity',
        'selected_color',
        'selected_size',
        'price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'float',
    ];

    protected $appends = [
        'subtotal',
        'display_name',
        'display_image',
        'display_sku',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getSubtotalAttribute(): float
    {
        return round($this->price * $this->quantity, 2);
    }

    public function getDisplayNameAttribute(): string
    {
        if (!empty($this->product_name)) {
            return $this->product_name;
        }
        if ($this->relationLoaded('product') && $this->product) {
            return $this->product->name;
        }
        if ($this->product) {
            return $this->product->name;
        }
        return 'Product Unavailable (Archived)';
    }

    public function getDisplayImageAttribute(): ?string
    {
        if (!empty($this->product_image)) {
            return $this->product_image;
        }
        if ($this->relationLoaded('product') && $this->product && !empty($this->product->images)) {
            return $this->product->images[0] ?? null;
        }
        if ($this->product && !empty($this->product->images)) {
            return $this->product->images[0] ?? null;
        }
        return null;
    }

    public function getDisplaySkuAttribute(): ?string
    {
        if (!empty($this->product_sku)) {
            return $this->product_sku;
        }
        if ($this->relationLoaded('product') && $this->product) {
            return $this->product->sku;
        }
        return $this->product?->sku ?? null;
    }
}
