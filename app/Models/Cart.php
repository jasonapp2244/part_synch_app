<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $table = "cart";
    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'price',
        'status',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);  // Assuming Cart has a product_id foreign key
    }

    // You can also define the relationship for ProductImages if needed
    public function productImages()
    {
        return $this->hasManyThrough(ProductImage::class, Product::class);  // Assuming Cart -> Product -> ProductImage
    }
    public function deliveryAddress()
    {
        return $this->hasOne(DeliveryAddress::class, 'cart_id', 'id');
    }
    public function user()
{
    return $this->belongsTo(User::class);
}
}
