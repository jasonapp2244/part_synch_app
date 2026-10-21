<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    /** How a status reads in "Your order … is now <label>." */
    public const STATUS_LABELS = [
        'pending'      => 'pending',
        'accept'       => 'accepted',
        'cancel'       => 'cancelled',
        'payment'      => 'paid',
        'order_packed' => 'packed',
        'shipping'     => 'on its way',
        'completed'    => 'completed',
        'delivered'    => 'delivered',
    ];

    /**
     * How the admin pages, invoice and exports name the payment method. Orders
     * from the app carry none, because the buyer pays the seller outside the
     * app, and "N/A" read like missing data (live run 2026-10-21, L24).
     */
    public function paymentMethodLabel(): string
    {
        $method = trim((string) $this->payment_method);

        return $method === '' ? 'Paid outside the app' : ucfirst(str_replace('_', ' ', $method));
    }

    /**
     * Tell the customer in-app that their order moved on. Email alone could
     * go unread or never arrive.
     */
    public function notifyCustomerOfStatus(): void
    {
        Notification::create([
            'user_id' => $this->user_id,
            'order_id' => $this->id,
            'email_subject' => 'Order Updated',
            'email_body' => "Your order {$this->order_number} is now " . (self::STATUS_LABELS[$this->order_status] ?? $this->order_status) . '.',
            'status' => 'sent',
        ]);
    }

    protected $fillable = [
        'user_id',
        'vendor_id',
        'vendor_type_id',
        'product_id',
        'payment_id',
        'payment_method',
        'order_number',
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
