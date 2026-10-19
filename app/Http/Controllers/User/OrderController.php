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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderController extends Controller
{
    public function orderCreate(Request $request)
    {
        // dd('here');

        $userId = auth()->id();

        $cartItems = Cart::with(['product', 'product.company', 'deliveryAddress'])
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('id', $request->cart_id)
            ->get();

        // dd($cartItems->toArray());
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
            // dd($orderItems);

            foreach ($items as $cartItem) {
                // Create order item
                $orderItem = new OrderItem();
                $orderItem->order_id = $order->id;
                // $orderItem->vendor_id = $vendorId;
                $orderItem->product_id = $cartItem->product_id;
                $orderItem->quantity = $cartItem->quantity;
                ['price' => $orderItem->price, 'total_price' => $orderItem->total_price] = $cartItem->orderItemMoney();
                $orderItem->discount = 0;
                $orderItem->tax = 0;
                $orderItem->order_status = 'pending';
                $orderItem->status = 'active';
                $orderItem->save();

                $orderItems[] = $orderItem;

                // Mark cart item as inactive after order is placed
                $cartItem->status = 'inactive';
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

            // One notification per order, not per line item — this used to sit
            // inside the item loop, so a three-item order produced three
            // identical "Order Placed" notifications.
            Notification::create([
                'user_id' => $userId,
                'order_id' => $order->id,
                'email_subject' => 'Order Placed',
                'email_body' => "Your order {$orderNumber} has been placed.",
                'status' => 'sent',
            ]);

            // The vendor was never notified in-app, only by email, so nothing
            // showed up in their notifications list when an order arrived.
            Notification::create([
                'vendor_id' => $order->vendor_id,
                'order_id' => $order->id,
                'email_subject' => 'New Order Received',
                'email_body' => "You have received a new order {$orderNumber}.",
                'status' => 'sent',
            ]);

            // Fetch customer record for email
            // dd($orderItems->toArray());
            $userRecord = User::find($userId);
            // dd($userRecord->toArray());
            // $admin_email = env('Admin_Email');
            // Send emails
            // The order is already saved; a failed email must not turn that
            // into an error the customer retries (and pays for) twice.
            foreach (['user' => $userRecord, 'vendor' => $vendorRecord] as $role => $recipient) {
                try {
                    Mail::to($recipient->email)->send(new OrderPlacedMail($order, $orderItems, $items, $userRecord, $vendorRecord, $completeAddress, $role));
                } catch (\Exception $e) {
                    Log::error("Order placed email to {$role} failed", ['order_id' => $order->id, 'error' => $e->getMessage()]);
                }
            }
        }

        return response()->json(['message' => 'Orders placed successfully.']);
    }

    public function orderHistory(Request $request)
    {
        $userId = auth()->id();

        $orders = Order::with(['orderItems.product.productImages', 'deliveryAddress'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate(15);

        $data = $orders->getCollection()->map(function ($order) {
            return [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->order_status,
                'total' => $order->orderItems->sum('total_price'),
                'items_count' => $order->orderItems->count(),
                'delivery_date' => $order->delivery_date,
                'created_at' => $order->created_at?->format('d M Y H:i'),
                'items' => $order->orderItems->map(function ($item) {
                    return [
                        'product_name' => $item->product->name ?? 'Deleted Product',
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'total_price' => $item->total_price,
                        'line_total' => $item->total_price,
                        'image' => $item->product?->productImages?->first()?->image_url,
                    ];
                }),
                'delivery_address' => $order->deliveryAddress ? [
                    'full_name' => $order->deliveryAddress->full_name,
                    'address' => $order->deliveryAddress->address_line1,
                    'city' => $order->deliveryAddress->city,
                    'state' => $order->deliveryAddress->state,
                    'country' => $order->deliveryAddress->country,
                    'postal_code' => $order->deliveryAddress->postal_code,
                    'phone_number' => $order->deliveryAddress->phone_number,
                ] : null,
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Order history fetched.',
            'data' => $data,
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

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
