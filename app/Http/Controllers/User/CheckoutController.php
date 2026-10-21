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
use Illuminate\Support\Facades\Log;


class CheckoutController extends Controller
{
    public function addDeliveryAddress(Request $request)
    {
        $request->validate([
            'cart_id' => 'required',
            'full_name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
        ]);

        $userId = auth()->id();

        $deliveryRecords = DeliveryAddress::where('user_id', $userId)->where('cart_id', $request->cart_id)
            ->where('status', 'inactive')
            ->get();
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

            // Activate cart when delivery address is added
            $cart = Cart::find($record->cart_id);
            if ($cart) {
                $cart->update(['status' => 'active']);
            }
        }

        return response()->json([
            'message' => 'All inactive delivery addresses updated successfully.',
            'data' => $deliveryRecords
        ]);
    }


    public function checkOutProducts(Request $request)
    {
        $request->validate([
            'cart_ids' => 'nullable|array',
            'cart_ids.*' => 'exists:cart,id'
        ]);

        $userId = auth()->id();
        $user = auth()->user();

        // Get cart items - if cart_ids provided, use those, otherwise get all active
        $cartQuery = Cart::with(['product', 'product.company', 'product.productImages', 'deliveryAddress'])
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereHas('product');

        // If cart_ids provided, filter by them
        if ($request->has('cart_ids') && !empty($request->cart_ids)) {
            $cartQuery->whereIn('id', $request->cart_ids);
        }

        $cartItems = $cartQuery->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'status' => false, 
                'message' => 'No items found in cart.'
            ], 400);
        }

        // Validate that all cart items have active delivery addresses
        $cartsWithoutAddress = [];
        foreach ($cartItems as $cartItem) {
            if (!$cartItem->deliveryAddress || $cartItem->deliveryAddress->status !== 'active') {
                $cartsWithoutAddress[] = $cartItem->id;
            }
        }

        if (!empty($cartsWithoutAddress)) {
            return response()->json([
                'status' => false,
                'message' => 'Some cart items are missing delivery addresses. Please add delivery address for cart IDs: ' . implode(', ', $cartsWithoutAddress),
                'carts_without_address' => $cartsWithoutAddress
            ], 400);
        }


        // Stock validation and vendor identification
        foreach ($cartItems as $cart) {
            // dd($cart);
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

                // Send out of stock emails - wrapped in try-catch to prevent blocking
                try {
                    Mail::to($user->email)->send(new ProductOutOfStockMail($product, $user, 'customer'));
                } catch (\Exception $e) {
                    Log::error('Failed to send out of stock email to customer: ' . $user->email, [
                        'error' => $e->getMessage(),
                        'product_id' => $product->id,
                        'trace' => $e->getTraceAsString()
                    ]);
                }

                if ($vendor) {
                    try {
                        Mail::to($vendor->email)->send(new ProductOutOfStockMail($product, $user, 'vendor'));
                    } catch (\Exception $e) {
                        Log::error('Failed to send out of stock email to vendor: ' . $vendor->email, [
                            'error' => $e->getMessage(),
                            'product_id' => $product->id,
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }

                return response()->json([
                    'status' => false,
                    'message' => "Product '{$product->name}' is out of stock. Notification sent."
                ], status: 400);
            }
        }

        DB::beginTransaction();
        try {
            // Group cart items by vendor (product owner)
            $groupedItems = $cartItems->groupBy(function ($item) {
                return $item->product->user_id;
            });

            // Group by delivery address as well (in case different carts have different addresses)
            // For now, we'll use the first cart's address per vendor group
            // You can modify this if you need to handle multiple addresses per vendor




            foreach ($groupedItems as $vendorId => $items) {
                $vendorRecord = User::find($vendorId);
                
                if (!$vendorRecord) {
                    Log::warning('Vendor not found for checkout', ['vendor_id' => $vendorId]);
                    continue;
                }

                // Get delivery address from first cart item in this vendor group
                $firstCartItem = $items->first();
                $deliveryAddress = $firstCartItem->deliveryAddress;
                
                if (!$deliveryAddress || $deliveryAddress->status !== 'active') {
                    Log::warning('Delivery address missing for cart item', [
                        'cart_id' => $firstCartItem->id,
                        'vendor_id' => $vendorId
                    ]);
                    continue;
                }

                // Format complete address
                $completeAddress = trim(
                    "{$deliveryAddress->address_line1} " .
                    ($deliveryAddress->address_line2 ? "{$deliveryAddress->address_line2}, " : "") .
                    "{$deliveryAddress->city}, " .
                    "{$deliveryAddress->state}, " .
                    "{$deliveryAddress->country}, " .
                    "{$deliveryAddress->postal_code}",
                    ', '
                );

                $orderNumber = '#ORD-' . now()->format('Ymd') . '-' . rand(100000, 999999);

                $order = Order::create([
                    'user_id' => $userId,
                    'vendor_id' => $vendorRecord->id,
                    'vendor_type_id' => $vendorRecord->vendor_type_id ?? null,
                    'order_number' => $orderNumber,
                    'delivery_address_id' => $deliveryAddress->id,
                    'shipping_charges' => 0,
                    'discount' => 0,
                    'tax' => 0,
                    'order_status' => 'pending'
                ]);

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

                    // Load product relationship for email
                    $orderItem->load('product.productImages', 'product.company');
                    $orderItems[] = $orderItem;

                    $cartItem->update(['status' => 'inactive']);

                    if ($cartItem->product) {
                        $cartItem->product->decrement('stock_quantity', $cartItem->quantity);
                    }

                    $stock = Stock::where('product_id', $cartItem->product_id)->first();
                    if ($stock) {
                        $stock->decrement('current_stock', $cartItem->quantity);
                        $stock->increment('order_placed', $cartItem->quantity);
                    }
                }

                // One notification per order, not per line item, and one for
                // the vendor too: this is the path the app uses, and it never
                // told the vendor in-app that an order had arrived (live run
                // 2026-10-21, L22).
                Notification::create([
                    'user_id' => $userId,
                    'order_id' => $order->id,
                    'email_subject' => 'Order Placed',
                    'email_body' => "Your order {$orderNumber} has been placed.",
                    'status' => 'sent',
                ]);

                Notification::create([
                    'vendor_id' => $vendorRecord->id,
                    'order_id' => $order->id,
                    'email_subject' => 'New Order Received',
                    'email_body' => "You have received a new order {$orderNumber}.",
                    'status' => 'sent',
                ]);

                // Calculate order totals
                $subtotal = collect($orderItems)->sum('total_price');
                $grandTotal = $subtotal + ($order->shipping_charges ?? 0) - ($order->discount ?? 0) + ($order->tax ?? 0);

                // Send Emails - wrapped in try-catch to prevent order failure if email fails
                try {
                    Mail::to($user->email)->queue(new OrderPlacedMail($order, $orderItems, $items, $user, $vendorRecord, $completeAddress, 'user', $subtotal, $grandTotal));
                } catch (\Exception $e) {
                    Log::error('Failed to send order email to user: ' . $user->email, [
                        'error' => $e->getMessage(),
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'trace' => $e->getTraceAsString()
                    ]);
                }

                if ($vendorRecord) {
                    try {
                        Mail::to($vendorRecord->email)->queue(new OrderPlacedMail($order, $orderItems, $items, $user, $vendorRecord, $completeAddress, 'vendor', $subtotal, $grandTotal));
                    } catch (\Exception $e) {
                        Log::error('Failed to send order email to vendor: ' . $vendorRecord->email, [
                            'error' => $e->getMessage(),
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status' => true, 
                'message' => 'Orders placed successfully. Emails sent to user and vendors.',
                'orders_created' => count($groupedItems),
                'cart_ids_processed' => $cartItems->pluck('id')->toArray()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while placing the order.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all cart products with complete details for checkout
     */
    public function getAllCartProducts(Request $request)
    {
        $userId = auth()->id();
        $user = auth()->user();

        // Get all active cart items with relationships
        // Get all active cart items (active = ready for checkout, inactive = already ordered)
        $cartItems = Cart::with([
            'product.productImages', 
            'product.company',
            'product.user',
            'deliveryAddress'
        ])
        ->where('user_id', $userId)
        ->where('status', 'active') // Only show active carts (ready for checkout)
        ->whereHas('product')
        ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No items found in cart.',
                'data' => [],
                'summary' => [
                    'total_items' => 0,
                    'total_price' => 0,
                    'items_with_address' => 0,
                    'items_without_address' => 0
                ]
            ], 200);
        }

        // Format cart data with complete details
        $cartData = [];
        $totalPrice = 0;
        $itemsWithAddress = 0;
        $itemsWithoutAddress = 0;

        foreach ($cartItems as $cart) {
            $product = $cart->product;
            if (!$product) continue;

            // Get product image
            $firstImage = optional($product->productImages->first())->image_url ?? asset('images/default-product.png');
            
            // Get stock information
            $latestStock = Stock::where('product_id', $product->id)->orderByDesc('id')->first();
            $currentStock = $latestStock?->current_stock ?? 0;
            $isInStock = $currentStock > 0 && $cart->quantity <= $currentStock;

            // Get delivery address status
            $deliveryAddress = $cart->deliveryAddress;
            $hasAddress = $deliveryAddress && $deliveryAddress->status === 'active';
            
            if ($hasAddress) {
                $itemsWithAddress++;
            } else {
                $itemsWithoutAddress++;
            }

            // `cart.price` already is the line total (BCR-14).
            $itemTotal = $cart->lineTotal();
            $totalPrice += $itemTotal;

            // Get vendor information
            $vendor = $product->user;
            
            $cartData[] = [
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku ?? 'N/A',
                'product_description' => $product->description,
                'product_image' => $firstImage,
                'product_images' => $product->productImages->map(function($img) {
                    return $img->image_url;
                })->toArray(),
                'quantity' => $cart->quantity,
                'unit_price' => $cart->unitPrice(),
                'total_price' => $itemTotal,
                'line_total' => $itemTotal,
                'company_name' => optional($product->company)->company_name,
                'company_id' => optional($product->company)->id,
                'vendor_id' => $vendor->id ?? null,
                'vendor_name' => $vendor ? $vendor->sellerName() : 'N/A',
                'stock' => [
                    'current_stock' => $currentStock,
                    'is_in_stock' => $isInStock,
                    'available_quantity' => $currentStock
                ],
                'delivery_address' => $hasAddress ? [
                    'address_id' => $deliveryAddress->id,
                    'full_name' => $deliveryAddress->full_name,
                    'phone_number' => $deliveryAddress->phone_number,
                    'address_line1' => $deliveryAddress->address_line1,
                    'address_line2' => $deliveryAddress->address_line2,
                    'city' => $deliveryAddress->city,
                    'state' => $deliveryAddress->state,
                    'country' => $deliveryAddress->country,
                    'postal_code' => $deliveryAddress->postal_code,
                    'status' => $deliveryAddress->status,
                    'complete_address' => trim(
                        "{$deliveryAddress->address_line1} " .
                        ($deliveryAddress->address_line2 ? "{$deliveryAddress->address_line2}, " : "") .
                        "{$deliveryAddress->city}, " .
                        "{$deliveryAddress->state}, " .
                        "{$deliveryAddress->country}, " .
                        "{$deliveryAddress->postal_code}",
                        ', '
                    )
                ] : null,
                'has_delivery_address' => $hasAddress,
                'can_checkout' => $hasAddress && $isInStock,
                'created_at' => $cart->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $cart->updated_at->format('Y-m-d H:i:s')
            ];
        }

        // Group by vendor for summary
        $vendorGroups = collect($cartData)->groupBy('vendor_id')->map(function($items, $vendorId) {
            return [
                'vendor_id' => $vendorId,
                'vendor_name' => $items->first()['vendor_name'],
                'items_count' => $items->count(),
                'total_price' => $items->sum('total_price')
            ];
        })->values();

        return response()->json([
            'status' => true,
            'message' => 'Cart products retrieved successfully.',
            'data' => $cartData,
            'summary' => [
                'total_items' => count($cartData),
                'total_price' => round($totalPrice, 2),
                'items_with_address' => $itemsWithAddress,
                'items_without_address' => $itemsWithoutAddress,
                'items_in_stock' => collect($cartData)->where('stock.is_in_stock', true)->count(),
                'items_out_of_stock' => collect($cartData)->where('stock.is_in_stock', false)->count(),
                'items_ready_for_checkout' => collect($cartData)->where('can_checkout', true)->count(),
                'vendor_groups' => $vendorGroups
            ]
        ], 200);
    }


    ///
    public function buyNowProductDetails(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
        ]);

        $userId = auth()->id();

        // Step 1: Check if the product is already in the user's active cart
        $cart_record = Cart::where('product_id', $request->product_id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->get();

        // Step 2: If the product is already in the cart, return a response
        if ($cart_record->isNotEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'This product is already in the cart for buy now'
            ]);
        }

        $product = Product::findOrFail($request->product_id);

        // Step 3: Validate stock
        $latestStock = Stock::where('product_id', $product->id)->orderByDesc('id')->first();
        $currentStock = $latestStock?->current_stock ?? $product->stock_quantity ?? 0;
        $quantity = $request->quantity ?? 1;

        if ($currentStock <= 0 || $quantity > $currentStock) {
            return response()->json([
                'status' => false,
                'message' => "Product '{$product->name}' is out of stock."
            ], 400);
        }

        // Step 4: Create cart item for buy-now
        $cartItem = Cart::create([
            'user_id' => $userId,
            'product_id' => $product->id,
            'quantity' => $quantity,
            // Line total, like every other cart row (BCR-14/BCR-15).
            'price' => round($quantity * (float) $product->price, 2),
            'status' => 'active',
        ]);

        // Step 5: Create inactive delivery address placeholder
        DeliveryAddress::create([
            'user_id' => $userId,
            'cart_id' => $cartItem->id,
            'status' => 'inactive',
        ]);

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
