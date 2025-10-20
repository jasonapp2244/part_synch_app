<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\{StripeService, PaypalService};
use App\Models\{Payment, Stock};
use App\Http\Controllers\OrderController;

class PaymentController extends Controller
{
    // 1. Initiate
    public function initiatePayment(Request $r)
    {
        $r->validate([
            'cart_id' => 'required|array',
            'payment_method' => 'required|in:stripe,paypal',
            'amount' => 'required|numeric',
            'currency' => 'required'
        ]);

        if ($r->payment_method == 'stripe') {
            $cs = (new StripeService())->createPaymentIntent($r->amount, $r->currency);
            return response()->json(['client_secret' => $cs]);
        }

        $url = (new PaypalService())->createPaypalPayment($r->amount, $r->currency);
        return response()->json(['approval_url' => $url]);

        // dd($url);
    }

    // 2. Confirm (Stripe) or PayPal callbacks
    public function confirmPayment(Request $r)
    {
        $r->validate(['payment_method' => 'required', 'payment_token' => 'required']);
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
    public function stripeSuccess(Request $r): JsonResponse
    {
        return $this->processSuccess($r);
    }
    public function stripeCancel()
    {
        return response()->json(['status' => 'cancelled'], 400);
    }

    private function processSuccess(Request $r)
    {
        DB::beginTransaction();
        try {
            // 1. Verify
            if ($r->payment_method == 'stripe') {
                (new StripeService())->verifyPaymentIntent($r->payment_token);
            } else {
                // PayPal sends both paymentId & PayerID
                (new PaypalService())->verifyPaypalPayment(
                    $r->payment_token,
                    $r->PayerID ?? $r->payerID
                );
            }

            // 2. Record Payment
            $p = Payment::create([
                'user_id' => auth()->id(),
                'vendor_id' => null,
                'cart_id' => implode(',', $r->cart_id),
                'transaction_id' => $r->payment_token,
                'payment_method' => $r->payment_method,
                'payment_date' => now(),
                'amount' => $r->amount,
                'currency' => $r->currency,
                'notes' => 'Success',
                'payment_status' => 'completed'
            ]);

            // 3. Create Order
            $o = (new OrderController())->createOrder(
                $r->merge(['payment_id' => $p->id])
            );

            $p->update(['order_id' => $o->id]);

            // 4. Deduct Stock
            foreach ($o->orderItems as $itm) {
                $stk = Stock::firstOrCreate(
                    ['product_id' => $itm->product_id],
                    ['user_id' => auth()->id(), 'current_stock' => 0]
                );
                $previous = $stk->current_stock;
                $stk->current_stock -= $itm->quantity;
                $stk->quantity_changed = $itm->quantity;
                $stk->previous_stock = $previous;
                $stk->reason = 'Order Placed';
                $stk->change_type = 'minus';
                $stk->order_placed = true;
                $stk->save();
            }

            DB::commit();
            return response()->json(['status' => 'success', 'order_id' => $o->id], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
