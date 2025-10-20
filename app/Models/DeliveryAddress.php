<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryAddress extends Model
{
    //
    protected $table = 'delivery_addresses';


    protected $fillable = [
        'user_id',
        'product_id',
        'cart_id',
        'full_name',
        'phone_number',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'country',
        'postal_code',
        'status',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
