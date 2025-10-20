<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'vendor_type_id',
        'product_id',
        'payment_id',
        'payment_method',
        'delivery_address_id',
        'shipping_charges',
        'discount',
        'tax',
        'order_status',
        'cancellation_reason',
        'cancellation_status',
        'delivery_date'
    ];


    public function deliveryAddress()
    {
        return $this->belongsTo(DeliveryAddress::class);
    }

    public function order()
    {
        return $this->hasMany(OrderItem::class);
    }


    public function orderItems()
{
    return $this->hasMany(OrderItem::class);
}
}
