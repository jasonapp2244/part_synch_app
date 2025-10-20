<?php

namespace App\Http\Controllers\User;

use App\Models\Cart;
use App\Models\User;
use App\Models\Order;
use App\Models\Stock;
use App\Models\Product;
use App\Models\Wishlist;
use App\Models\OrderItem;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Mail\OrderPlacedMail;
use App\Models\DeliveryAddress;
use Illuminate\Support\Facades\DB;
use App\Mail\ProductOutOfStockMail;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;


class CheckoutController extends Controller
{
    public function addDeliveryAddress(Request $request)
    {
        $request->validate([
            // 'full_name' => 'required|string|max:255',
            // 'phone_number' => 'required|string|max:20',
            // 'address_line1' => 'required|string|max:255',
            // 'address_line2' => 'nullable|string|max:255',
            // 'city' => 'required|string|max:100',
            // 'state' => 'required|string|max:100',
            // 'country' => 'required|string|max:100',
            // 'postal_code' => 'required|string|max:20',
        ]);

        $userId = auth()->id();

        // Get all inactive delivery records for the user
        $deliveryRecords = DeliveryAddress::where('user_id', $userId)
            ->where('status', 'inactive')
            ->get();
        // dd($deliveryRecords->toArray());
        if ($deliveryRecords->isEmpty()) {
            return response()->json([
                'message' => 'No inactive delivery addresses found for the user.',
            ], 400);
        }

        foreach ($deliveryRecords as $record) {
            $record->update([
                'full_name' => $request->full_name,
                'phone_number' => $request->phone_number,
                'address_line1' => $request->address_line1,
                'address_line2' => $request->address_line2,
                'city' => $request->city,
                'state' => $request->state,
                'country' => $request->country,
                'postal_code' => $request->postal_code,
                'status' => 'active'
            ]);
        }

        return response()->json([
            'message' => 'All inactive delivery addresses updated successfully.',
            'data' => $deliveryRecords
        ]);
    }


    public function checkOutProducts(Request $request)
    {
        $userId = auth()->id();
        $user = auth()->user();

        $cartItems = Cart::with(['product', 'product.company', 'deliveryAddress'])
            ->where('user_id', $userId)
            ->where('status', 'inactive')
            ->whereHas('product')
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No items found in cart.']);
        }


        // Stock validation and vendor identification
        foreach ($cartItems as $cart) {
            $product = $cart->product;
            // dd($product->toArray());
            if (!$product) continue;

            $latestStock = Stock::where('product_id', $product->id)->orderByDesc('id')->first();
            $currentStock = $latestStock?->current_stock ?? 0;

            if ($currentStock <= 0 || $cart->quantity > $currentStock) {
                // dd($currentStock);
                // Notify customer

                // Find vendor from users table using role_id and product ownership
                $vendor = User::where('role_id', 2)
                    ->where('id', $product->user_id)
                    ->first();


                $existing = Wishlist::where('user_id', $userId)
                    ->where('product_id', $product->id)
                    ->first();

                if (!$existing) {
                    $wishlist = new Wishlist();
                    $wishlist->user_id = $userId;
                    $wishlist->product_id = $product->id;
                    $wishlist->type = "product";
                    $wishlist->status = 'notified';
                    $wishlist->save();
                }


                Mail::to($user->email)->send(new ProductOutOfStockMail($product, $user, 'customer'));


                if ($vendor) {
                    Mail::to($vendor->email)->send(new ProductOutOfStockMail($product, $user, 'vendor'));
                }

                return response()->json([
                    'status' => false,
                    'message' => "Product '{$product->name}' is out of stock. Notification sent."
                ], status: 400);
            }
        }





        DB::beginTransaction();
        try {
            $groupedItems = $cartItems->groupBy(function ($item) {
                return $item->product->user_id;
            });

            $deliveryAddress = $cartItems->first()->deliveryAddress;
            $completeAddress = '';
            if ($deliveryAddress) {
                $completeAddress = trim("{$deliveryAddress->address_line1} {$deliveryAddress->address_line2}, {$deliveryAddress->city}, {$deliveryAddress->state}, {$deliveryAddress->country}, {$deliveryAddress->postal_code}", ', ');
            }


        // yar ap to keh rha th public_html khali ma na us hisab sa files set kr ka bhji h
        // ab is hisab sa set kerni ho gi



            foreach ($groupedItems as $vendorId => $items) {
                $vendorRecord = User::find($vendorId);
                $orderNumber = '#ORD-' . now()->format('Ymd') . '-' . rand(100000, 999999);

                $order = Order::create([
                    'user_id' => $userId,
                    'vendor_id' => $vendorRecord->id ?? null,
                    'vendor_type_id' => $vendorRecord->vendor_type_id ?? null,
                    'order_number' => $orderNumber,
                    'delivery_address_id' => $deliveryAddress->id ?? null,
                    'shipping_charges' => 0,
                    'discount' => 0,
                    'tax' => 0,
                    'status' => 'active',
                    'process_status' => 'notified'

                ]);

                $orderItems = [];

                foreach ($items as $cartItem) {
                    $orderItems[] = OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $cartItem->product_id,
                        'quantity' => $cartItem->quantity,
                        'price' => $cartItem->price,
                        'total_price' => $cartItem->price * $cartItem->quantity,
                        'discount' => 0,
                        'tax' => 0,
                        'order_status' => 'pending',
                        'status' => 'active'
                    ]);

                    Notification::create([
                        'user_id' => $userId,
                        'order_id' => $order->id,
                        'email_subject' => 'Order Placed',
                        'email_body' => "Your order {$orderNumber} has been placed.",
                        'status' => 'sent'
                    ]);

                    $cartItem->update(['status' => 'active']);

                    if ($cartItem->product) {
                        $cartItem->product->decrement('stock_quantity', $cartItem->quantity);
                    }

                    $stock = Stock::where('product_id', $cartItem->product_id)->first();
                    if ($stock) {
                        $stock->decrement('current_stock', $cartItem->quantity);
                        $stock->increment('order_placed', $cartItem->quantity);
                    }
                }

                // Send Emails
                Mail::to($user->email)->send(new OrderPlacedMail($order, $orderItems, $items, $user, $vendorRecord, $completeAddress, 'user'));
                if ($vendorRecord) {
                    Mail::to($vendorRecord->email)->send(new OrderPlacedMail($order, $orderItems, $items, $user, $vendorRecord, $completeAddress, 'vendor'));
                }
            }

            DB::commit();

            return response()->json(['status' => true, 'message' => 'Orders placed and emails sent successfully.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while placing the order.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }





    ///
    public function buyNowProductDetails(Request $request)
    {
        $userId = auth()->id();

        // Step 1: Check if the product is already in the user's cart
        $cart_record = Cart::where('product_id', $request->product_id)
            ->where('user_id', $userId)
            ->where('status', 'inactive')
            ->get();

        // Step 2: If the product is already in the cart, return a response
        if ($cart_record->isNotEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'This product is already in the cart for buy now'
            ]);
        }

        $product = Product::findOrFail($request->product_id);
        $totalPrice = $product->price * $request->quantity;


        // Step 6: Fetch the newly created cart item along with related data
        $cartItem = Cart::with([
            'product.productImages',
            'product.company',
            'deliveryAddress'
        ])->find($cartItem->id);



        if (!$cartItem) {
            return response()->json([
                'status' => false,
                'message' => 'Record not found'
            ]);
        }

        // Step 7: Format the cart data for response
        $product = $cartItem->product;
        $firstImage = optional($product->productImages->first())->image_url ?? asset('images/default-product.png');
        $address = optional($cartItem->deliveryAddress);

        $cartData = [
            'cart_id' => $cartItem->id,
            'user_id' => $cartItem->user_id,
            'product_id' => $cartItem->product_id,
            'quantity' => $cartItem->quantity,
            'unit_price' => $product->price,
            'total_price' => $cartItem->quantity * $product->price,
            'product_name' => $product->name,
            'product_price' => $product->price,
            'product_description' => $product->description,
            'product_image' => $firstImage,
            'company_name' => optional($product->company)->company_name,
            'delivery_address' => $address ? [
                'full_name' => $address->full_name,
                'phone_number' => $address->phone_number,
                'address_line1' => $address->address_line1,
                'address_line2' => $address->address_line2,
                'city' => $address->city,
                'state' => $address->state,
                'country' => $address->country,
                'postal_code' => $address->postal_code,
                'status' => $address->status,
            ] : null
        ];

        // Step 8: Calculate totals
        $subTotal = $cartData['total_price'];
        // $shippingFee = 5;
        // $totalPrice = $subTotal + $shippingFee;
        $totalPrice = $subTotal;

        // Step 9: Return the response
        return response()->json([
            'status' => true,
            'message' => 'Checkout product fetched successfully',
            'data' => [$cartData], // Wrap in an array for consistency
            'subtotal' => round($subTotal, 2),
            // 'shipping_fee' => $shippingFee,
            'total_price' => round($totalPrice, 2),
        ]);
    }
}
