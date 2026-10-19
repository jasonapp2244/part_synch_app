<?php

namespace App\Support;

use App\Models\Order;

/**
 * Money breakdown of one order, using the same formula as checkout
 * (User\CheckoutController): items + shipping - discount + tax.
 * Orders store no total column, so the admin page, the invoice and the
 * orders export all derive it here.
 */
class OrderTotals
{
    /**
     * @return array{subtotal: float, shipping: float, discount: float, tax: float, grand_total: float}
     */
    public static function of(Order $order): array
    {
        $subtotal = (float) $order->orderItems->sum('total_price');
        $shipping = (float) ($order->shipping_charges ?? 0);
        $discount = (float) ($order->discount ?? 0);
        $tax = (float) ($order->tax ?? 0);

        return [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'discount' => $discount,
            'tax' => $tax,
            'grand_total' => $subtotal + $shipping - $discount + $tax,
        ];
    }
}
