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

    /**
     * `cart.price` is the LINE total (quantity x unit price), whatever the
     * column name says: add-to-cart has always written it that way. Every
     * reader multiplied it by the quantity again, so a line of 2 x 47.00 was
     * ordered, emailed and counted in vendor earnings as 188.00 (BCR-14).
     */
    public function lineTotal(): float
    {
        return round((float) $this->price, 2);
    }

    public function unitPrice(): float
    {
        return $this->quantity > 0 ? round($this->lineTotal() / $this->quantity, 2) : $this->lineTotal();
    }

    /**
     * The money columns of the order_items row this cart line becomes:
     * `price` is the unit price, `total_price` the line total.
     */
    public function orderItemMoney(): array
    {
        return ['price' => $this->unitPrice(), 'total_price' => $this->lineTotal()];
    }
}
