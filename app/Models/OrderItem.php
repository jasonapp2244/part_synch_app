<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'vendor_id',
        'product_id',
        'quantity',
        'price',
        'total_price',
        'discount',
        'tax',
        'order_status',
        'status',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }



}
