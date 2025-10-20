<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'vendor_id',
        'order_id',
        'payment_id',
        'vendor_payment_id',
        'discount_id',
        'refunds_id',
        'email_subject',
        'email_body',
        'status',
    ];
}
