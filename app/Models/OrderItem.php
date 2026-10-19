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

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Lines that count as money earned: everything except cancelled orders.
     * Pass a vendor id to narrow it to that vendor's orders.
     */
    public function scopeEarned($query, ?int $vendorId = null)
    {
        return $query->whereHas('order', function ($q) use ($vendorId) {
            $q->where('order_status', '!=', 'cancel')
                ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId));
        });
    }
}
