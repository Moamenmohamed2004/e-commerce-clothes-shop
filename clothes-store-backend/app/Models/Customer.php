<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'total_orders',
        'last_order_date',
    ];

    protected $casts = [
        'total_orders' => 'integer',
        'last_order_date' => 'datetime',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
