<?php

namespace App\Http\Controllers\Vendor;

use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Mail\orderManagement;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    public function orderView(Request $request)
    {
        $vendorId = auth()->user()->id;

        $status = $request->query('status');

        $orders = DB::table('orders as o')
            ->join('order_items as oi', 'o.id', '=', 'oi.order_id')
            ->join('products as p', 'oi.product_id', '=', 'p.id')
            ->leftJoin('product_images as pi', 'p.id', '=', 'pi.product_id')
            ->join('delivery_addresses as da', 'o.delivery_address_id', '=', 'da.id')
            ->when($status, function ($query) use ($status) {
                return $query->where('o.order_status', $status);
            })
            ->where('o.vendor_id', $vendorId)
            ->select(
                'o.user_id as user_id',
                'o.vendor_id as vendor_id',
                'o.id as order_id',
                'o.order_number',
                'oi.quantity',
                'oi.total_price',
                'p.name as product_name',
                'pi.image_url',
                'da.address_line1',
                'da.address_line2',
                'da.city',
                'o.order_status',
                'o.delivery_date'
            )
            ->orderBy('o.created_at', 'desc')
            ->get();

        if ($orders->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Record not found'
            ], 400);
        }

        return response()->json([
            'status' => true,
            'orders' => $orders
        ], 200);
    }

    /**
     * Statuses a vendor may move an order into, keyed by the status the order
     * is currently in. Fulfilment runs forward only; 'cancel' stays available
     * until the parcel is handed over, and the three end states are terminal.
     */
    private const STATUS_FLOW = [
        'pending'      => ['accept', 'cancel'],
        'accept'       => ['payment', 'order_packed', 'cancel'],
        'payment'      => ['order_packed', 'cancel'],
        'order_packed' => ['shipping', 'cancel'],
        'shipping'     => ['completed'],
        'completed'    => [],
        'delivered'    => [],
        'cancel'       => [],
    ];

    public function orderManage(Request $request)
    {
        $validStatuses = ['accept', 'cancel', 'payment', 'order_packed', 'shipping', 'completed'];

        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'order_status' => ['required', Rule::in($validStatuses)],
        ]);

        // Start transaction
        DB::beginTransaction();

        try {
            // Scope to the signed-in vendor: an order id alone must never be
            // enough to manage somebody else's order.
            $order = Order::where('id', $request->order_id)
                ->where('vendor_id', auth()->id())
                ->first();

            if (!$order) {
                DB::rollBack();
                return response()->json(['message' => 'Order not found'], 404);
            }

            $current = $order->order_status;
            $allowed = self::STATUS_FLOW[$current] ?? [];

            if (!in_array($request->order_status, $allowed, true)) {
                DB::rollBack();

                return response()->json([
                    'message' => $allowed
                        ? "An order in '{$current}' cannot be moved to '{$request->order_status}'."
                        : "This order is '{$current}' and can no longer be changed.",
                    'current_status' => $current,
                    'allowed_statuses' => $allowed,
                ], 422);
            }

            // Set new status
            $order->order_status = $request->order_status;

            // Save but not commit yet
            if (!$order->save()) {


                DB::rollBack();
                return response()->json(['message' => 'Status not updated'], 500);
            }

            $order->syncStockForStatusChange($current, $order->order_status);
            $order->notifyCustomerOfStatus();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            // Log the error for debugging
            Log::error('Order Status Update Failed: ' . $e->getMessage());

            return response()->json(['message' => 'Something went wrong. The status was not changed.'], 500);
        }

        // Mail goes out after the commit: a mail server refusing the login
        // used to roll the status change back and fail the request.
        $user = User::find($order->user_id);
        $vendor = User::find($order->vendor_id);
        foreach (['user' => $user, 'vendor' => $vendor] as $role => $recipient) {
            if (!$recipient?->email) {
                continue;
            }
            try {
                Mail::to($recipient->email)->send(new OrderManagement($order, $user, $vendor, $role));
            } catch (\Exception $e) {
                Log::error("Order status email to {$role} failed", ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json(['message' => 'Status updated']);
    }
}
