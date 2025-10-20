<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $table = 'product_images';

    protected $fillable = [
        'user_id',
        'product_id',
        'image_url'
    ];

    // public function product()
    // {
    //     return $this->belongsTo(Product::class,'product_id');
    // }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    // public function product()
    // {
    //     return $this->belongsTo(Product::class);
    // }
}
