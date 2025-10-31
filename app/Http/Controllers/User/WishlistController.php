<?php

namespace App\Http\Controllers\User;

use App\Models\User;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Carbon\Exceptions\Exception;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\WishlistNotificationMail;

class WishlistController extends Controller
{
    //    public function storeWishlist(Request $request)
    // {
    //     $request->validate([
    //         'type' => 'required|in:company,product,vendor',
    //         'product_id' => 'nullable|exists:products,id',
    //         'vendor_id' => 'nullable',
    //         'company_id' => 'nullable',
    //     ]);

    //     $userId = Auth::id();

    //     $exists = Wishlist::where('user_id', $userId)
    //         ->where('type', $request->type)
    //         ->where('product_id', $request->product_id)
    //         ->where('vendor_id', $request->vendor_id)
    //         ->exists();

    //     if ($exists) {
    //         return response()->json([
    //             'message' => 'You have already added this to your wishlist.'
    //         ], 409);
    //     }

    //     try {
    //         $user = Auth::user();
    //         $vendor = null;

    //         if ($request->vendor_id) {
    //             $vendor = User::find($request->vendor_id);
    //         }

    //         // Mail to user
    //         Mail::to($user->email)->send(new WishlistNotificationMail($request->all()));

    //         // Mail to vendor if exists
    //         if ($vendor) {
    //             Mail::to($vendor->email)->send(new WishlistNotificationMail($request->all()));
    //         }

    //         $wishlist = Wishlist::create([
    //             'user_id' => $userId,
    //             'product_id' => $request->product_id,
    //             'vendor_id' => $request->vendor_id,
    //             'company_id' => $request->compnay_id,
    //             'type' => $request->type,
    //             'status' => 'save',
    //             'process_status' => 'inactive',
    //         ]);

    //         return response()->json([
    //             'message' => 'Added to wishlist successfully and notification emails sent.',
    //             'data' => $wishlist
    //         ], 201);

    //     } catch (Exception $e) {
    //         return response()->json([
    //             'message' => 'Failed to send notification emails. Wishlist not saved.',
    //             'error' => $e->getMessage()
    //  git        ], 500);
    //     }
    // }



    // public function storeWishlist(Request $request)
    // {
    //     $request->validate([
    //         // 'type' => 'required|in:company,product,vendor',
    //         'product_id' => 'nullable|exists:products,id',
    //         'vendor_id' => 'nullable|exists:users,id',
    //         // 'company_id' => 'nullable|exists:companies,id',
    //     ]);

    //     $userId = Auth::id();

    //     // $exists = Wishlist::where('user_id', $userId)
    //     //     ->where('type', $request->type)
    //     //     ->where('product_id', $request->product_id)
    //     //     ->where('vendor_id', $request->vendor_id)
    //     //     // ->where('company_id', $request->company_id)
    //     //     ->exists();

    //     // if ($exists) {
    //     //     return response()->json([
    //     //         'message' => 'You have already added this to your wishlist.'
    //     //     ], 409);
    //     // }

    //     try {
    //         $user = Auth::user();
    //         $data = [
    //             'type' => $request->type,
    //             'product_id' => $request->product_id,
    //             'vendor_id' => $request->vendor_id,
    //             // 'company_id' => $request->company_id,
    //             // 'user_name' => $user->name ?? '',
    //         ];

    //         $user_record = Product::with([
    //             'product.productImages',
    //             'user'
    //         ])
    //             ->where('product_id', $request->product_id)
    //             ->where('user_id', $userId)
    //             ->first();


    //         dd($user_record);

    //         Mail::to($user->email)->send(new WishlistNotificationMail($data));
    //         if ($request->vendor_id) {
    //             $vendor = User::where('id', $request->vendor_id)->where('role_id', 2)->first();
    //             if ($vendor) {
    //                 Mail::to($vendor->email)->send(new WishlistNotificationMail($data));
    //             }
    //         }

    //         $wishlist = Wishlist::create([
    //             'user_id' => $userId,
    //             'prPoduct_id' => $request->product_id,
    //             'vendor_id' => $request->vendor_id,
    //             // 'company_id' => $request->company_id,
    //             'type' => $request->type,
    //             'status' => 'save',
    //             'process_status' => 'inactive',
    //         ]);
    //         // dd($wishlist->toArray());
    //         return response()->json([
    //             'message' => 'Added to wishlist successfully. Notifications sent.',
    //             'data' => $wishlist
    //         ], 201);
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'message' => 'Failed to send emails. Wishlist not saved.',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }


    public function storeWishlist(Request $request)
    {
        $request->validate([
            'type' => 'required|in:product,vendor',
            'product_id' => 'nullable|exists:products,id',
            'vendor_id' => 'nullable|exists:users,id',
        ]);

        $userId = Auth::id();
        $user = Auth::user();
        // dd($user->toArray());
        try {
            $data = [
                'type' => $request->type,
                'by_user_name' => $user->name,
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

            //  Prepare email data
            if ($request->type === 'product' && $request->product_id) {
                $product = Product::with(['productImages', 'user'])->find($request->product_id);
                // dd($product->toArray());
                if (!$product) {
                    return response()->json(['message' => 'Product not found.'], 400);
                }

                $data['product_id'] = $product->id;
                $data['product_name'] = $product->name ?? '';
                $data['product_image'] = $product->productImages->first()->image_url ?? 'https://via.placeholder.com/200';
                $data['vendor_id'] = $product->user->id ?? null;
                $data['vendor_name'] = $product->user->name ?? '';
                $data['product_url'] = route('product.details', $product->id);

                // Send mail to user and vendor
                Mail::to($user->email)->send(new WishlistNotificationMail($data));
                if (!empty($product->user->email)) {
                    Mail::to($product->user->email)->send(new WishlistNotificationMail($data));
                }
            }
            if ($request->type === 'vendor' && $request->vendor_id) {
                $vendor = User::where('id', $request->vendor_id)->where('role_id', 2)->first();
                if (!$vendor) {
                    return response()->json(['message' => 'Vendor not found.'], 400);
                }
                $data['vendor_id'] = $vendor->id;
                $data['vendor_name'] = $vendor->name ?? '';
                $data['vendor_logo'] = $vendor->profile_image ?? 'https://via.placeholder.com/200';
                $data['vendor_url'] = route('vendor.store', $vendor->id);

                Mail::to($user->email)->send(new WishlistNotificationMail($data));
                // You can optionally notify the vendor
                Mail::to($vendor->email)->send(new WishlistNotificationMail($data));
            }
            $wishlist = Wishlist::create([
                'user_id' => $userId,
                'product_id' => $request->product_id,
                'vendor_id' => $request->vendor_id,
                'type' => $request->type,
                'status' => 'save',
                'process_status' => 'inactive',
            ]);
            return response()->json([
                'status' => true,
                'message' => 'Added to wishlist successfully. Notifications sent.',
                'data' => $wishlist
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send emails or save wishlist.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
