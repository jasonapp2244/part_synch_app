<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{

    protected $fillable = [
        'user_id',
        'vendor_id',
        'cart_id',
        'order_id',
        'transaction_id',
        'payment_method',
        'payment_date',
        'amount',
        'currency',
        'notes',
        'payment_status',
    ];

}
