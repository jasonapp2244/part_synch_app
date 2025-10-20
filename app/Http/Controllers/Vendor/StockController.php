<?php

namespace App\Http\Controllers\Vendor;

use App\Models\User;
use App\Models\Stock;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Mail\ProductBackInStockMail;
use Illuminate\Support\Facades\Mail;

class StockController extends Controller
{

    public function updateStock(Request $request)
    {
        $vendorId = auth()->id(); // Authenticated vendor

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'new_stock' => 'required|integer|min:0',
        ]);

        $productId = $request->product_id;
        $newStock = $request->new_stock;

        // Check product ownership
        $product = Product::where('id', $productId)
            ->where('user_id', $vendorId)
            ->first();

        if (!$product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found or not owned by vendor.',
            ], 400);
        }

        // Update or create stock
        $stock = Stock::where('product_id', $productId)->first();
        if ($stock) {
            $stock->new_stock = $newStock;
            $stock->current_stock += $newStock;
            $stock->save();
        } else {
            $stock = new Stock();
            $stock->product_id = $productId;
            $stock->new_stock = $newStock;
            $stock->current_stock = $newStock;
            $stock->order_placed = 0;
            $stock->save();
        }

        // Update product stock_quantity
        $product->stock_quantity += $newStock;
        $product->save();

        // Notify wishlist users
        $wishlistUsers = Wishlist::where('product_id', $productId)
            ->where('status', 'notified')
            ->get();

        if ($wishlistUsers->isEmpty()) {
            return response()->json([
                'status' => true,
                'message' => 'Stock updated successfully. No users to notify.',
            ]);
        }

        foreach ($wishlistUsers as $wishlist) {
            $user = User::find($wishlist->user_id);

            if ($user) {
                // Queue the email
                Mail::to($user->email)->send(new ProductBackInStockMail($product, $user));

                // Mail::to($user->email)->queue(new ProductBackInStockMail($product, $user));
            }

            // Mark wishlist status as active
            $wishlist->status = 'active';
            $wishlist->save();
        }

        return response()->json([
            'status' => true,
            'message' => 'Stock updated successfully and all interested customers notified.',
        ]);
    }
}
