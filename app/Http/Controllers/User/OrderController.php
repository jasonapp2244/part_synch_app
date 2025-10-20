<?php

namespace App\Http\Controllers\User;

use App\Models\Cart;
use App\Models\User;
use App\Models\Order;
use App\Models\Stock;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Mail\OrderPlacedMail;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    public function orderCreate(Request $request)
    {
        $userId = auth()->id();

        $cartItems = Cart::with(['product', 'product.company', 'deliveryAddress'])
            ->where('user_id', $userId)
            ->where('status', 'inactive')
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'No cart items found'], 404);
        }

        // Group cart items by vendor
        $groupedItems = $cartItems->groupBy(function ($item) {
            return $item->product->user_id;
        });

        // dd($groupedItems->toArray());

        // Get delivery address
        $deliveryAddress = $cartItems->first()->deliveryAddress;
        $completeAddress = '';
        if ($deliveryAddress) {
            $completeAddress = $deliveryAddress->address_line1;
            if ($deliveryAddress->address_line2) {
                $completeAddress .= " " . $deliveryAddress->address_line2;
            }
            $completeAddress .= ", " . $deliveryAddress->city;
            $completeAddress .= ", " . $deliveryAddress->state;
            $completeAddress .= ", " . $deliveryAddress->country;
            $completeAddress .= ", " . $deliveryAddress->postal_code;
        }

        foreach ($groupedItems as $vendorId => $items) {
            $firstItem = $items->first();
            $vendorRecord = User::find($vendorId);
            // dd($vendorRecord->toArray());
            // Generate unique order number for each vendor
            $orderNumber = '#ORD-' . now()->format('Ymd') . '-' . rand(100000, 999999);

            // Create order
            $order = new Order();
            $order->user_id = $userId;
            $order->vendor_type_id = $vendorRecord->vendor_type_id ?? null;
            $order->vendor_id = $vendorRecord->id ?? null;
            $order->order_number = $orderNumber;
            $order->product_id = $firstItem->product_id;
            $order->delivery_address_id = $deliveryAddress->id ?? null;
            $order->shipping_charges = 0;
            $order->discount = 0;
            $order->tax = 0;
            $order->order_status = 'pending';
            $order->save();

            $orderItems = [];

            foreach ($items as $cartItem) {
                // Create order item
                $orderItem = new OrderItem();
                $orderItem->order_id = $order->id;
                // $orderItem->vendor_id = $vendorId;
                $orderItem->product_id = $cartItem->product_id;
                $orderItem->quantity = $cartItem->quantity;
                $orderItem->price = $cartItem->price;
                $orderItem->total_price = $cartItem->quantity * $cartItem->price;
                $orderItem->discount = 0;
                $orderItem->tax = 0;
                $orderItem->order_status = 'pending';
                $orderItem->status = 'active';
                $orderItem->save();

                $orderItems[] = $orderItem;

                // Create notificationx
                Notification::create([
                    'user_id' => $userId,
                    'order_id' => $order->id,
                    'email_subject' => 'Order Placed',
                    'email_body' => "Your order {$orderNumber} has been placed.",
                    'status' => 'sent',
                ]);

                // Mark cart item as inactive
                $cartItem->status = 'active';
                $cartItem->save();

                // Update stock in products table
                $product = $cartItem->product;
                if ($product) {
                    $product->stock_quantity -= $cartItem->quantity;
                    $product->save();
                }

                // Update stock record
                $stock = Stock::where('product_id', $cartItem->product_id)->first();
                if ($stock) {
                    $stock->current_stock -= $cartItem->quantity;
                    $stock->order_placed += $cartItem->quantity;
                    $stock->save();
                }
            }

            // Fetch customer record for email
            // dd($orderItems->toArray());
            $userRecord = User::find($userId);
            // dd($userRecord->toArray());
            // Send emails
            Mail::to($userRecord->email)->send(new OrderPlacedMail($order, $orderItems, $items, $userRecord, $vendorRecord, $completeAddress, 'user'));
            Mail::to($vendorRecord->email ?? 'tauseefchoohan0401@gmail.com')->send(new OrderPlacedMail($order, $orderItems, $items, $userRecord, $vendorRecord, $completeAddress, 'vendor'));
        }

        return response()->json(['message' => 'Orders placed and emails sent successfully.']);
    }
    // public function orderStatus(Request $request)
    // {
    //     $userId = auth()->id();

    //     $orders = DB::table('orders as o')
    //         ->join('order_items as oi', 'o.id', '=', 'oi.order_id')
    //         ->join('products as p', 'oi.product_id', '=', 'p.id')
    //         ->leftJoin(DB::raw('(SELECT product_id, MIN(image_url) as image_url FROM product_images GROUP BY product_id) as pi'), 'p.id', '=', 'pi.product_id')
    //         ->join('delivery_addresses as da', 'o.delivery_address_id', '=', 'da.id')
    //         ->where('o.user_id', $userId)
    //         ->where('o.order_status', '!=', 'complete')
    //         ->select(
    //             'o.user_id as user_id',
    //             'o.vendor_id as vendor_id',
    //             'o.id as order_id',
    //             'o.order_number',
    //             'oi.quantity',
    //             'oi.total_price',
    //             'p.name as product_name',
    //             'pi.image_url',
    //             'da.address_line1',
    //             'da.address_line2',
    //             'da.city',
    //             'o.order_status',
    //             'o.delivery_date'
    //         )
    //         ->orderBy('o.created_at', 'desc')
    //         ->get();

    //     if ($orders->isEmpty()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Record not found'
    //         ], 404);
    //     }

    //     return response()->json([
    //         'status' => true,
    //         'orders' => $orders
    //     ], 200);
    // }




    public function orderStatus(Request $request)
    {
        $userId = auth()->id();
        $status = $request->input('status');

        $ordersQuery = DB::table('orders as o')
            ->join('order_items as oi', 'o.id', '=', 'oi.order_id')
            ->join('products as p', 'oi.product_id', '=', 'p.id')
            ->leftJoin(DB::raw('(SELECT product_id, MIN(image_url) as image_url FROM product_images GROUP BY product_id) as pi'), 'p.id', '=', 'pi.product_id')
            ->join('delivery_addresses as da', 'o.delivery_address_id', '=', 'da.id')
            ->where('o.user_id', $userId);

        // Apply status condition
        if ($status && $status !== 'all') {
            $ordersQuery->where('o.order_status', $status);
        }

        $orders = $ordersQuery
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
            ], 404);
        }

        return response()->json([
            'status' => true,
            'orders' => $orders
        ], 200);
    }
}
