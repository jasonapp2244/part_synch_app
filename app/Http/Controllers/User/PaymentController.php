<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\{StripeService, PaypalService};
use App\Models\{Cart, User, Order, OrderItem, Payment, Stock, Notification};
use App\Mail\OrderPlacedMail;

class PaymentController extends Controller
{
    // 1. Initiate
    public function initiatePayment(Request $r)
    {
        $r->validate([
            'cart_id' => 'required|array',
            'cart_id.*' => 'exists:cart,id',
            'payment_method' => 'required|in:stripe,paypal',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string'
        ]);

        if ($r->payment_method == 'stripe') {
            $paymentData = (new StripeService())->createPaymentIntent($r->amount, $r->currency);
            return response()->json([
                'client_secret' => $paymentData['client_secret'],
                'payment_intent_id' => $paymentData['payment_intent_id']
            ]);
        }

        $url = (new PaypalService())->createPaypalPayment($r->amount, $r->currency);
        return response()->json(['approval_url' => $url]);
    }

    // 2. Confirm (Stripe) or PayPal callbacks
    public function confirmPayment(Request $r)
    {
        $r->validate([
            'payment_method' => 'required|in:stripe,paypal',
            'payment_token' => 'required|string',
            'cart_id' => 'required|array',
            'amount' => 'required|numeric',
            'currency' => 'required|string',
        ]);
        return $this->processSuccess($r);
    }

    public function paypalSuccess(Request $r)
    {
        return $this->processSuccess($r);
    }

    public function paypalCancel()
    {
        return response()->json(['status' => 'cancelled'], 400);
    }

    public function stripeSuccess(Request $r)
    {
        return $this->processSuccess($r);
    }

    public function stripeCancel()
    {
        return response()->json(['status' => 'cancelled'], 400);
    }

    private function processSuccess(Request $r)
    {
        $userId = auth()->id();
        $user = auth()->user();

        DB::beginTransaction();
        try {
            // 1. Verify payment with gateway
            if ($r->payment_method == 'stripe') {
                (new StripeService())->verifyPaymentIntent($r->payment_token);
            } else {
                (new PaypalService())->verifyPaypalPayment(
                    $r->payment_token,
                    $r->PayerID ?? $r->payerID
                );
            }

            // 2. Get cart items
            $cartItems = Cart::with(['product', 'product.company', 'product.productImages', 'deliveryAddress'])
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->whereIn('id', $r->cart_id)
                ->get();

            if ($cartItems->isEmpty()) {
                throw new \Exception('No active cart items found for the given IDs.');
            }

            // 3. Record Payment
            $payment = Payment::create([
                'user_id' => $userId,
                'cart_id' => implode(',', $r->cart_id),
                'transaction_id' => $r->payment_token,
                'payment_method' => $r->payment_method,
                'payment_date' => now(),
                'amount' => $r->amount,
                'currency' => $r->currency,
                'notes' => 'Success',
                'payment_status' => 'completed'
            ]);

            // 4. Create orders grouped by vendor
            $groupedItems = $cartItems->groupBy(function ($item) {
                return $item->product->user_id;
            });

            $createdOrderIds = [];

            foreach ($groupedItems as $vendorId => $items) {
                $vendorRecord = User::find($vendorId);
                if (!$vendorRecord) continue;

                $firstItem = $items->first();
                $deliveryAddress = $firstItem->deliveryAddress;

                $orderNumber = '#ORD-' . now()->format('Ymd') . '-' . rand(100000, 999999);

                $order = Order::create([
                    'user_id' => $userId,
                    'vendor_id' => $vendorRecord->id,
                    'vendor_type_id' => $vendorRecord->vendor_type_id ?? null,
                    'order_number' => $orderNumber,
                    'payment_id' => $payment->id,
                    'payment_method' => $r->payment_method,
                    'delivery_address_id' => $deliveryAddress->id ?? null,
                    'shipping_charges' => 0,
                    'discount' => 0,
                    'tax' => 0,
                    'order_status' => 'pending'
                ]);

                $createdOrderIds[] = $order->id;
                $orderItems = [];

                foreach ($items as $cartItem) {
                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $cartItem->product_id,
                        'quantity' => $cartItem->quantity,
                        ...$cartItem->orderItemMoney(),
                        'discount' => 0,
                        'tax' => 0,
                        'order_status' => 'pending',
                        'status' => 'active'
                    ]);

                    $orderItem->load('product.productImages', 'product.company');
                    $orderItems[] = $orderItem;

                    // Create notification
                    Notification::create([
                        'user_id' => $userId,
                        'order_id' => $order->id,
                        'email_subject' => 'Order Placed',
                        'email_body' => "Your order {$orderNumber} has been placed.",
                        'status' => 'sent'
                    ]);

                    // Mark cart as inactive
                    $cartItem->update(['status' => 'inactive']);

                    // Deduct stock from products table
                    if ($cartItem->product) {
                        $cartItem->product->decrement('stock_quantity', $cartItem->quantity);
                    }

                    // Deduct stock from stock records
                    $stock = Stock::where('product_id', $cartItem->product_id)->first();
                    if ($stock) {
                        $stock->decrement('current_stock', $cartItem->quantity);
                        $stock->increment('order_placed', $cartItem->quantity);
                    }
                }

                // Update payment with first order id
                if (count($createdOrderIds) === 1) {
                    $payment->update(['order_id' => $order->id, 'vendor_id' => $vendorRecord->id]);
                }

                // Format address for email
                $completeAddress = '';
                if ($deliveryAddress) {
                    $completeAddress = trim(
                        "{$deliveryAddress->address_line1} " .
                        ($deliveryAddress->address_line2 ? "{$deliveryAddress->address_line2}, " : "") .
                        "{$deliveryAddress->city}, {$deliveryAddress->state}, " .
                        "{$deliveryAddress->country}, {$deliveryAddress->postal_code}",
                        ', '
                    );
                }

                // Send emails
                $subtotal = collect($orderItems)->sum('total_price');
                $grandTotal = $subtotal;

                try {
                    Mail::to($user->email)->queue(new OrderPlacedMail($order, $orderItems, $items, $user, $vendorRecord, $completeAddress, 'user', $subtotal, $grandTotal));
                } catch (\Exception $e) {
                    Log::error('Payment: Failed to send order email to user', ['error' => $e->getMessage(), 'order_id' => $order->id]);
                }

                try {
                    Mail::to($vendorRecord->email)->queue(new OrderPlacedMail($order, $orderItems, $items, $user, $vendorRecord, $completeAddress, 'vendor', $subtotal, $grandTotal));
                } catch (\Exception $e) {
                    Log::error('Payment: Failed to send order email to vendor', ['error' => $e->getMessage(), 'order_id' => $order->id]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Payment confirmed and orders placed successfully.',
                'order_ids' => $createdOrderIds,
                'payment_id' => $payment->id
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment processSuccess failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'failed', 'error' => $e->getMessage()], 500);
        }
    }
}
