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

        $orders = DB::table('orders as o')
            ->join('order_items as oi', 'o.id', '=', 'oi.order_id')
            ->join('products as p', 'oi.product_id', '=', 'p.id')
            ->leftJoin('product_images as pi', 'p.id', '=', 'pi.product_id')
            ->join('delivery_addresses as da', 'o.delivery_address_id', '=', 'da.id')
            ->where('o.order_status', 'pending')
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
            $order = Order::where('id', $request->order_id)
                ->where('order_status', 'pending') // Adjust if needed
                ->first();
            // dd($order->toArray());
            if (!$order) {
                return response()->json(['message' => 'Order not found or not in pending status'], 400);
            }
            // Set new status
            $order->order_status = $request->order_status;

            // Save but not commit yet
            if (!$order->save()) {


                DB::rollBack();
                return response()->json(['message' => 'Status not updated'], 500);
            }

            $user = User::find($order->user_id);
            $vendor = User::find($order->vendor_id);
            // Send mails
            Mail::to($user->email)->send(new OrderManagement($order, $user, $vendor, 'user'));
            Mail::to($vendor->email)->send(new OrderManagement($order, $user, $vendor, 'vendor'));

            // If all is good, commit changes
            DB::commit();
            return response()->json(['message' => 'Status updated and notifications sent']);
        } catch (\Exception $e) {
            DB::rollBack();

            // Log the error for debugging
            Log::error('Order Status Update Failed: ' . $e->getMessage());

            return response()->json(['message' => 'Something went wrong. Email or update failed.'], 500);
        }
    }
}
