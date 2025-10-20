<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'order_id',
        'vendor_id',
        'change_type',
        'quantity_changed',
        'previous_stock',
        'new_stock',
        'reason',
        'status',
        'created_at',
        'updated_at',
    ];
}
