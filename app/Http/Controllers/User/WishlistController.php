<?php

namespace App\Http\Controllers\User;

use App\Models\User;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\WishlistNotificationMail;

class WishlistController extends Controller
{
    public function storeWishlist(Request $request)
    {
        $request->validate([
            'type' => 'required|in:product,vendor',
            'product_id' => 'nullable|exists:products,id',
            'vendor_id' => 'nullable|exists:users,id',
        ]);

        $userId = Auth::id();
        $user = Auth::user();

        try {
            $data = [
                'type' => $request->type,
                'by_user_name' => $user->first_name,
                'by_user_email' => $user->email,
            ];

            // Check if already in wishlist
            $exists = Wishlist::where('user_id', $userId)
                ->when($request->type === 'product', function ($query) use ($request) {
                    return $query->where('product_id', $request->product_id);
                })
                ->when($request->type === 'vendor', function ($query) use ($request) {
                    return $query->where('vendor_id', $request->vendor_id);
                })
                ->where('type', $request->type)
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => false,
                    'message' => "Product / Vendor is already in your wishlist.",
                ], 400);
            }

            // Prepare email data for product wishlist
            if ($request->type === 'product' && $request->product_id) {
                $product = Product::with(['productImages', 'user'])->find($request->product_id);
                if (!$product) {
                    return response()->json(['status' => false, 'message' => 'Product not found.'], 400);
                }

                $data['product_id'] = $product->id;
                $data['product_name'] = $product->name ?? '';
                $data['product_image'] = $product->productImages->first()->image_url ?? 'https://via.placeholder.com/200';
                $data['vendor_id'] = $product->user->id ?? null;
                $data['vendor_name'] = $product->user->first_name ?? '';
                $data['product_url'] = route('product.details', $product->id);

                $mailTo = [$user->email, $product->user->email ?? null];
            }

            // Prepare email data for vendor wishlist
            if ($request->type === 'vendor' && $request->vendor_id) {
                $vendor = User::where('id', $request->vendor_id)->where('role_id', 2)->first();
                if (!$vendor) {
                    return response()->json(['status' => false, 'message' => 'Vendor not found.'], 400);
                }
                $data['vendor_id'] = $vendor->id;
                $data['vendor_name'] = $vendor->first_name ?? '';
                $data['vendor_logo'] = $vendor->profile_image ?? 'https://via.placeholder.com/200';
                $data['vendor_url'] = route('vendor.store', $vendor->id);

                $mailTo = [$user->email, $vendor->email];
            }

            $wishlist = Wishlist::create([
                'user_id' => $userId,
                'product_id' => $request->product_id,
                'vendor_id' => $request->vendor_id,
                'type' => $request->type,
                'status' => 'save',
                'process_status' => 'inactive',
            ]);

            // Saved first, mailed second: a mail server refusing the login
            // used to fail the save with a 500.
            foreach (array_filter($mailTo ?? []) as $address) {
                try {
                    Mail::to($address)->send(new WishlistNotificationMail($data));
                } catch (\Exception $e) {
                    Log::error('Wishlist email failed', ['wishlist_id' => $wishlist->id, 'error' => $e->getMessage()]);
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Added to wishlist successfully.',
                'data' => $wishlist
            ], 201);
        } catch (\Exception $e) {
            // The raw exception text (SQL, SMTP replies) went to the app.
            Log::error('Wishlist save failed', ['user_id' => $userId, 'error' => $e->getMessage()]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to save wishlist.',
            ], 500);
        }
    }

    public function getWishlist()
    {
        $userId = Auth::id();

        $wishlists = Wishlist::where('user_id', $userId)
            ->with(['product.productImages', 'product.user'])
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $wishlists->map(function ($item) {
            if ($item->type === 'product' && $item->product) {
                return [
                    'wishlist_id' => $item->id,
                    'type' => 'product',
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? 'N/A',
                    'product_price' => $item->product->price ?? 0,
                    'product_image' => $item->product->productImages->first()->image_url ?? null,
                    // Same name the product page and the cart use (L35).
                    'vendor_name' => $item->product->user?->sellerName() ?? 'N/A',
                    'stock_quantity' => $item->product->stock_quantity ?? 0,
                    'is_active' => $item->product->is_active,
                    'created_at' => $item->created_at,
                ];
            } elseif ($item->type === 'vendor') {
                $vendor = User::find($item->vendor_id);
                return [
                    'wishlist_id' => $item->id,
                    'type' => 'vendor',
                    'vendor_id' => $item->vendor_id,
                    'vendor_name' => $vendor?->sellerName() ?? 'N/A',
                    'vendor_business' => $vendor->business_type ?? 'N/A',
                    'vendor_image' => $vendor->profile_image ?? null,
                    'created_at' => $item->created_at,
                ];
            }
            return null;
        })->filter()->values();

        return response()->json([
            'status' => true,
            'message' => 'Wishlist fetched successfully',
            'data' => $data,
            'total' => $data->count(),
        ]);
    }

    public function removeWishlist(Request $request)
    {
        $request->validate([
            'wishlist_id' => 'required|exists:wishlists,id',
        ]);

        $wishlist = Wishlist::where('id', $request->wishlist_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$wishlist) {
            return response()->json([
                'status' => false,
                'message' => 'Wishlist item not found.',
            ], 404);
        }

        $wishlist->delete();

        return response()->json([
            'status' => true,
            'message' => 'Removed from wishlist successfully.',
        ]);
    }
}
