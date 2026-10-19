<?php

namespace App\Http\Controllers\User;

use App\Models\Cart;
use App\Models\Stock;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use App\Models\DeliveryAddress;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{

    public function addOrUpdateCart(Request $request)
    {
        try {
            $request->validate([
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|integer',
            ]);

            $userId = auth()->id();
            $product = Product::findOrFail($request->product_id);

            if (!$product->is_active || $product->status !== 'active') {
                return response()->json([
                    'status' => false,
                    'message' => 'This product is currently unavailable.',
                ], 400);
            }

            if ($product->stock_quantity <= 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'This product is out of stock.',
                ], 400);
            }

            if ($request->quantity > $product->stock_quantity) {
                return response()->json([
                    'status' => false,
                    'message' => 'Only ' . $product->stock_quantity . ' items available in stock.',
                ], 400);
            }

            return DB::transaction(function () use ($userId, $request, $product) {
                // Active rows only: checkout leaves its rows behind as
                // 'inactive', and picking one up again added the new quantity
                // to the last order's (bought 2, added 1, sent an order for 3).
                $cartItem = Cart::where('user_id', $userId)
                    ->where('product_id', $request->product_id)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                if ($cartItem) {
                    $newQty = $cartItem->quantity + $request->quantity;

                    if ($newQty <= 0) {
                        DeliveryAddress::where('cart_id', $cartItem->id)->delete();
                        $cartItem->delete();
                        return response()->json(['message' => 'Item removed from cart (quantity zero)'], 200);
                    }

                    if ($newQty > $product->stock_quantity) {
                        return response()->json([
                            'status' => false,
                            'message' => 'Cannot add more. You already have ' . $cartItem->quantity . ' in cart. Only ' . $product->stock_quantity . ' available in stock.',
                        ], 400);
                    }

                    $newTotalPrice = $newQty * $product->price;

                    $cartItem->update([
                        'quantity' => $newQty,
                        'price' => $newTotalPrice,
                        'status' => 'active',
                    ]);

                    return response()->json([
                        'message' => 'Cart updated',
                        'data' => $cartItem
                    ], 200);
                }

                if ($request->quantity <= 0) {
                    return response()->json([
                        'message' => 'Quantity must be greater than 0 for new items'
                    ], 400);
                }

                $totalPrice = $request->quantity * $product->price;

                $cartItem = Cart::create([
                    'user_id' => $userId,
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity,
                    'price' => $totalPrice,
                    'status' => 'active',
                ]);

                DeliveryAddress::create([
                    'user_id' => $userId,
                    'product_id' => $request->product_id,
                    'cart_id' => $cartItem->id,
                    'status' => 'inactive'
                ]);

                return response()->json([
                    'message' => 'Item added to cart',
                    'data' => $cartItem
                ], 200);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function viewCart()
    {
        $cartItems = DB::table('cart')
            ->join('products', 'cart.product_id', '=', 'products.id')
            ->leftJoin('product_images', 'products.id', '=', 'product_images.product_id')
            ->join('companies', 'products.company_id', '=', 'companies.id')
            ->where('cart.user_id', auth()->id())
            ->where('cart.status', 'active')
            ->whereNotNull('cart.product_id')
            ->select(
                'cart.id',
                'cart.user_id',
                'cart.product_id',
                'cart.quantity',
                'cart.price',
                'cart.status',
                'cart.created_at',
                'cart.updated_at',
                'products.name as product_name',
                'products.price as product_price',
                'products.description as product_description',
                DB::raw('MAX(product_images.image_url) as product_image'),
                'companies.company_name as company_name'
            )
            ->groupBy(
                'cart.id',
                'cart.user_id',
                'cart.product_id',
                'cart.quantity',
                'cart.price',
                'cart.status',
                'cart.created_at',
                'cart.updated_at',
                'products.id',
                'products.name',
                'products.price',
                'products.description',
                'companies.id',
                'companies.company_name'
            )
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No cart items found.'
            ], 400);
        }

        $totalPrice = $cartItems->sum('price');

        return response()->json([
            'status' => true,
            'message' => 'Cart items retrieved successfully.',
            'data' => $cartItems,
            'total_price' => $totalPrice
        ], 200);
    }


    public function cartProductDetails(Request $request)
    {
        try {
            $userId = auth()->id();

            // Step 1: Get active cart items with relationships
            $cartItems = Cart::with(['product.productImages', 'product.company'])
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->get();
            // Step 2: Filter out any cart items with missing products
            $cartData = $cartItems->filter(fn($cart) => $cart->product)
                ->map(function ($cart) {
                    $product = $cart->product;
                    $firstImage = optional($product->productImages->first())->image_url ?? asset('images/default-product.png');

                    return [
                        'cart_id' => $cart->id,
                        'user_id' => $cart->user_id,
                        'product_id' => $cart->product_id,
                        'quantity' => $cart->quantity,
                        'unit_price' => $product->price,
                        'total_price' => $cart->quantity * $product->price,
                        'product_name' => $product->name,
                        'product_price' => $product->price,
                        'product_description' => $product->description,
                        'product_image' => $firstImage,
                        'company_name' => optional($product->company)->company_name,
                    ];
                });

            if ($cartData->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No cart products found.'
                ], 400);
            }

            // Step 3: Calculate totals
            $subTotal = $cartData->sum('total_price');
            $totalPrice = $subTotal;

            // Step 4: Return formatted response
            return response()->json([
                'status' => true,
                'message' => 'Checkout products fetched successfully',
                'data' => $cartData,
                'subtotal' => round($subTotal, 2),
                'total_price' => round($totalPrice, 2),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function removeCartItem(Request $request)
    {
        $request->validate([
            'cart_id' => 'required|exists:cart,id',
        ]);

        // A checked-out row belongs to an order now; deleting it took the
        // order's delivery address with it.
        $cartItem = Cart::where('id', $request->cart_id)
            ->where('user_id', auth()->id())
            ->where('status', 'active')
            ->first();

        if (!$cartItem) {
            return response()->json([
                'status' => false,
                'message' => 'Cart item not found.',
            ], 404);
        }

        DeliveryAddress::where('cart_id', $cartItem->id)->delete();
        $cartItem->delete();

        return response()->json([
            'status' => true,
            'message' => 'Item removed from cart successfully.',
        ]);
    }
}
