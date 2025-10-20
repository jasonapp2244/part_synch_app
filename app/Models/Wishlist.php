<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
     protected $fillable = [
        'user_id',
        'product_id',
        'vendor_id',
        'company_id',
        'type',
        'status',
        'process_status',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}



// domi
Lunch kul
450+100+200
@asad

