<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'customer_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'address',
        'city',
        'notes',
        'total',
        'status',
    ];

    protected $casts = [
        'total' => 'float',
    ];

    protected $appends = [
        'subtotal',
        'shipping_fee',
        'discount',
        'payment_method',
        'payment_status',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getSubtotalAttribute(): float
    {
        return round((float) $this->items->sum(function ($item) {
            return $item->price * $item->quantity;
        }), 2);
    }

    public function getShippingFeeAttribute(): float
    {
        $diff = round($this->total - $this->subtotal, 2);
        return $diff > 0 ? $diff : 0.0;
    }

    public function getDiscountAttribute(): float
    {
        $diff = round($this->subtotal - $this->total, 2);
        return $diff > 0 ? $diff : 0.0;
    }

    public function getPaymentMethodAttribute(): string
    {
        return 'Cash on Delivery';
    }

    public function getPaymentStatusAttribute(): string
    {
        return match ($this->status) {
            'Delivered' => 'Paid',
            'Cancelled' => 'Cancelled',
            'Refunded' => 'Refunded',
            default => 'Pending Payment',
        };
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('id', $value)
            ->orWhere('order_number', $value)
            ->first() ?? abort(404, 'Order not found');
    }
}
