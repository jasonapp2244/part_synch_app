<?php

namespace App\Http\Controllers\Vendor;

use App\Models\Stock;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Str;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\CompanyProductCategoryLinks;

class ProductController extends Controller
{
    public function getAllProduct(Request $request, $id = null)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not authenticated.',
            ], 400);
        }

        if ($user->role_id != 2) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to access this resource.',
            ], 403);
        }
        $products = Product::with('productImages')
            ->where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->paginate();

        // A vendor with no products is a normal, successful result — it is what
        // every new vendor sees. This used to answer 400 / status:false, which
        // the app maps onto its network-failure screen: the first thing a vendor
        // saw after signing up was "We're unable to process your request. Please
        // try again later." and a Retry button that could never succeed.
        // Returning the empty paginator lets the client render an empty state.
        return response()->json([
            'status'  => true,
            'message' => $products->isEmpty()
                ? 'No products found.'
                : 'Products fetched successfully.',
            'data'    => $products,
        ]);
    }


    public function showProduct(Request $request, $id = null)
    {
        if (! $id) {
            return response()->json(['error' => 'Product ID is required'], 400);
        }
        $product = Product::where('id', $id)
            ->with('productImages')
            ->get();
        if (! $product) {
            return response()->json(
                [
                    'error' => 'Product not found'
                ],
                400
            );
        }
        return response()->json(['message' => 'Product found', 'product' => $product], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function addProduct(Request $request)
    {
        try {
            $request->validate([
                'name'                          => 'required|string|max:255',
                'price'                         => 'required|numeric|min:0',
                'modal_number'                  => 'required|string|max:100',
                'category_id'                   => 'nullable|exists:categories,id',
                'sub_category_id'               => 'nullable|exists:sub_categories,id',
                'company_id'                    => 'nullable|exists:companies,id',
                'company_product_categories_id' => 'nullable',
                'barcode'                       => 'nullable|string|max:255',
                'warranty'                      => 'nullable|string|max:255',
                'description'                   => 'nullable|string',
                'expire_date'                   => 'nullable|date',
                'new_stock'                     => 'nullable|numeric|min:0',
                'status'                        => 'nullable|in:active,inactive',
                'product_pic'                   => 'nullable|array',
                'product_pic.*'                 => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);


            // Lowercased for the duplicate check only; the product keeps the
            // model number as the vendor typed it ("C18-PK-2207", not
            // "c18-pk-2207": live run 2026-10-21, L20).
            $typedModalNumber = trim((string) $request->modal_number);
            $modalNumber = strtolower($typedModalNumber);
            $name = strtolower(trim($request->name));
            // $sku = strtoupper(trim($request->sku));
            // return $sku;
            $cleanName = preg_replace('/[^a-z0-9]/', '', strtolower($name));
            $cleanName = substr($cleanName, 0, 10); // Limit to 10 chars for brevi
            $uniqueSuffix = strtoupper(dechex(time() % 100000)) . rand(10000, 99999); // e.g. "3E8A7"
            $sku =  strtoupper($cleanName) . '-' . $uniqueSuffix;
            // return $sku;
            $existingSku = Product::whereRaw('LOWER(sku) = ?', [strtolower($sku)])->first();
            if ($existingSku) {
                // return "testing";
                return response()->json([
                    'status'  => false,
                    'message' => 'A product with this SKU/product number already exists. SKU must be unique.',
                ], 400);
            }



            $productWithSameNameOrModal = Product::where(function ($query) use ($name, $modalNumber) {
                $query->whereRaw('LOWER(name) = ?', [$name])
                    ->where('user_id', Auth::user()->id)
                    ->orWhereRaw('LOWER(modal_number) = ?', [$modalNumber]);
            })->first();

            // return $productWithSameNameOrModal;
            if ($productWithSameNameOrModal) {

                // Step 2.1: If the new product has same name or modal, and same SKU (already handled above)
                // Step 2.2: If same name or modal but different SKU — allow it
                // Step 2.3: But name and modal must be provided if duplication exists
                if (empty($name) || empty($modalNumber)) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Name and Modal Number are required when similar products already exist.',
                        'data' => $productWithSameNameOrModal,
                    ], 400);
                }
            }

            $product = new Product();
            $product->user_id                       = Auth::id();
            $product->vendor_id                     = $request->vendor_id;
            $product->category_id                   = $request->category_id;
            $product->sub_category_id               = $request->sub_category_id;
            $product->company_id                    = $request->company_id;
            $product->company_product_categories_id = $request->company_product_categories_id;
            $product->service_status                = $request->service_status;
            $product->service_type                  = $request->service_type;
            $product->sku                           = $sku;
            $product->name                          = $request->name;
            $product->barcode                       = $request->barcode;
            $product->warranty                      = $request->warranty;
            $product->description                   = $request->description;
            $product->price                         = $request->price;
            $product->modal_number                  = $typedModalNumber;
            $product->expire_date                   = $request->expire_date;
            $product->status                        = $request->status ?? 'active';
            $product->stock_quantity                = 0;
            $product->save();

            CompanyProductCategoryLinks::create([
                'company_id'     => $request->company_id,
                'com_pro_cat_id' => $request->company_product_categories_id,
                'product_id'     => $product->id,
                'modal_number'   => $typedModalNumber,
                'product_number' => $sku,
                // 'custom_price'   => $request->custom_price,
                'original_price' => $product->price,
                'stock_quantity' => $request->new_stock ?? 0,
                'warranty'       => $product->warranty,
                'created_by'     => Auth::id(),
            ]);

            if ($request->has('new_stock')) {
                $newStock = (int) $request->new_stock;
                if ($newStock < 0) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Stock quantity cannot be negative!',
                    ], 400);
                }

                $stock = Stock::firstOrNew(['product_id' => $product->id]);
                $stock->user_id = Auth::id();
                $stock->vendor_id = $request->vendor_id;
                $stock->new_stock += $newStock;
                $stock->current_stock += $newStock;
                $stock->save();

                $product->stock_quantity += $newStock;
                $product->save();
            }

            $uploadedImages = [];
            if ($request->hasFile('product_pic')) {
                foreach ($request->file('product_pic') as $image) {
                    $imageHash = sha1_file($image->getRealPath());
                    $imageName = $imageHash . '_' . time() . '.' . $image->getClientOriginalExtension();
                    $imagePath = 'product_images/' . $imageName;

                    if (Storage::exists($imagePath)) {
                        Storage::delete($imagePath);
                    }

                    // The disk is set not to throw, so a failed write returned
                    // false and the row below still pointed at a file that was
                    // never there (live run 2026-10-21, L14).
                    if ($image->storeAs('product_images', $imageName) === false) {
                        Log::error('Product image was not saved', ['product_id' => $product->id, 'file' => $imageName]);
                        continue;
                    }

                    $productImage = new ProductImage();
                    $productImage->user_id    = Auth::id();
                    $productImage->product_id = $product->id;
                    $productImage->name       = $imageName;
                    $productImage->hash       = $imageHash;
                    $productImage->image_url  = URL::to('storage/' . $imagePath);
                    $productImage->save();

                    $uploadedImages[] = asset('storage/' . $imagePath);
                }
            }

            // Check if boost is requested at product creation
            $boostInfo = null;
            if ($request->has('boost_package_id') && $request->boost_package_id) {
                $boostInfo = [
                    'package_id' => $request->boost_package_id,
                    'boost_position_id' => $request->boost_position_id ?? null,
                    'product_id' => $product->id,
                    'message' => 'Product created. Please complete boost payment to activate boost.',
                    'boost_endpoint' => '/api/vendor/boost/purchase',
                    'note' => 'Use this product_id in product_ids array when purchasing boost'
                ];
            }

            return response()->json([
                'message'        => 'Product added successfully!',
                'status'         => true,
                'service_status' => Product::SERVICE_STATUS,
                'service_types'  => Product::SERVICE_TYPES,
                'data'           => $product,
                'product_images' => $uploadedImages,
                'boost_info'     => $boostInfo,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
    public function updateProduct(Request $request, $id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Product not found!',
                ], 400);
            }

            if ($product->user_id !== Auth::id()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'You are not authorized to update this product.',
                ], 403);
            }

            // user_id is the owning vendor and is fixed at creation. It is never
            // taken from the request, or a vendor could hand a product — and the
            // orders and revenue hanging off it — to any other account.
            $product->vendor_id                     = $request->vendor_id ?? $product->vendor_id;
            $product->category_id                   = $request->category_id ?? $product->category_id;
            $product->sub_category_id               = $request->sub_category_id ?? $product->sub_category_id;
            $product->company_product_categories_id = $request->company_product_categories_id ?? $product->company_product_categories_id;
            $product->service_status                = $request->service_status ?? $product->service_status;
            $product->service_type                  = $request->service_type ?? $product->service_type;
            $product->name                          = $request->product_name ?? $product->name;
            $product->description                   = $request->description ?? $product->description;
            $product->price                         = $request->price ?? $product->price;
            $product->modal_number                  = $request->modal_number ?? $product->modal_number;
            // $product->sku                           = $request->product_number ?? $product->sku;
            $product->status                        = $request->status ?? $product->status;
            $product->save();

            if ($request->has('new_stock')) {
                $newStock = (int) $request->new_stock;

                if ($newStock < 0) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Stock quantity cannot be negative!',
                    ], 400);
                }

                $stock = Stock::where('product_id', $product->id)->first();
                if ($stock) {
                    $stock->new_stock  = $newStock;
                    $stock->current_stock += $newStock;
                    $stock->save();
                } else {
                    $stock = new Stock();
                    $stock->product_id = $product->id;
                    $stock->new_stock  = $newStock;
                    $stock->current_stock  = $newStock;
                    $stock->save();
                }

                $wasOut = $product->stock_quantity <= 0;
                $product->stock_quantity += $newStock;
                $product->save();

                // The app restocks through this form, never `update-stock`, so
                // customers waiting on an out-of-stock part were never told.
                if ($wasOut && $product->stock_quantity > 0) {
                    $product->notifyBackInStock();
                }
            }

            // `new_stock` can only add. `set_stock` is the count the vendor
            // actually has, so a stock count can be corrected downwards from
            // the app (phone run 2026-10-22); the app sends it only to lower.
            if ($request->filled('set_stock')) {
                $setStock = filter_var($request->input('set_stock'), FILTER_VALIDATE_INT);

                if ($setStock === false || $setStock < 0) {
                    return response()->json([
                        'status'  => false,
                        'message' => 'Stock quantity must be a whole number, 0 or more.',
                    ], 422);
                }

                $previous = (int) $product->stock_quantity;
                $wasOut = $previous <= 0;
                $product->stock_quantity = $setStock;
                $product->save();

                $stock = Stock::firstOrNew(['product_id' => $product->id]);
                $stock->previous_stock   = $previous;
                $stock->quantity_changed = $setStock - $previous;
                $stock->current_stock    = $setStock;
                $stock->change_type      = 'stock_adjustment';
                $stock->save();

                if ($wasOut && $setStock > 0) {
                    $product->notifyBackInStock();
                }
            }

            $link = CompanyProductCategoryLinks::where('product_id', $product->id)
                ->where('company_id', $request->company_id)
                ->where('com_pro_cat_id', $request->company_product_categories_id)
                ->first();

            if (!$link) {
                $link = new CompanyProductCategoryLinks();
                $link->company_id     = $request->company_id;
                $link->product_id     = $product->id;
                $link->com_pro_cat_id = $request->company_product_categories_id;
                $link->created_by     = Auth::id();
            }

            // Set all pivot fields only if they exist in request, otherwise retain old value
            $link->modal_number          = $request->modal_number         ?? $link->modal_number;
            // $link->product_number        = $request->product_number       ?? $link->product_number;
            $link->custom_price          = $request->custom_price         ?? $link->custom_price;
            $link->original_price        = $request->price                ?? $link->original_price;
            // The product's real count. This used to copy `new_stock` — the
            // amount *added* — so the home feed, which reads this column, showed
            // "2 in stock" after a restock of two onto ten.
            $link->stock_quantity        = (int) $product->stock_quantity;
            $link->warranty              = $request->warranty             ?? $link->warranty;
            $link->discount              = $request->discount             ?? $link->discount;
            $link->discount_expire_date  = $request->discount_expire_date ?? $link->discount_expire_date;
            $link->is_featured           = $request->is_featured          ?? $link->is_featured ?? false;
            $link->is_active             = $request->is_active            ?? $link->is_active ?? true;
            $link->notes                 = $request->notes                ?? $link->notes;
            // $link->sku                   = $request->sku                  ?? $link->sku;
            $link->is_approved           = $request->is_approved          ?? $link->is_approved ?? false;
            $link->price_type            = $request->price_type           ?? $link->price_type;
            $link->tags                  = $request->tags                 ?? $link->tags;
            $link->is_deleted            = $request->is_deleted           ?? $link->is_deleted ?? 0;

            $link->price_updated_at      = now();
            $link->stock_updated_at      = now();
            $link->updated_at            = now();

            $link->save();


            // New photos are added to the listing; a photo goes only when the
            // app names it in `remove_images[]`. The upload used to be taken
            // as the complete set, so adding one photo deleted every photo the
            // listing already had, and a photo the vendor removed in the app
            // was never removed here (device run 2026-10-20).
            $uploadedImages = [];
            $existingImages = ProductImage::where('product_id', $id)->get();

            $removeNames = collect((array) $request->input('remove_images', []))
                ->filter(fn ($url) => is_string($url) && $url !== '')
                ->map(fn ($url) => basename(parse_url($url, PHP_URL_PATH) ?? ''))
                ->filter()
                ->all();

            foreach ($existingImages as $existingImage) {
                if (in_array(basename(parse_url($existingImage->image_url, PHP_URL_PATH) ?? ''), $removeNames, true)) {
                    Storage::delete('product_images/' . basename($existingImage->image_url));
                    $existingImage->delete();
                }
            }

            if ($request->hasFile('product_pic')) {
                foreach ($request->file('product_pic') as $image) {
                    $imageHash = sha1_file($image->getRealPath());

                    // The same picture again keeps the copy already stored. This
                    // used to repoint that record at a file name that was never
                    // written, so the image broke.
                    if (ProductImage::where('product_id', $product->id)->where('hash', $imageHash)->exists()) {
                        continue;
                    }

                    $imageName = $imageHash . '_' . time() . "." . $image->getClientOriginalExtension();
                    $imagePath = 'product_images/' . $imageName;

                    if ($image->storeAs('product_images', $imageName) === false) {
                        Log::error('Product image was not saved', ['product_id' => $product->id, 'file' => $imageName]);
                        continue;
                    }
                    $productImage = new ProductImage();
                    $productImage->user_id = Auth::id();
                    $productImage->product_id = $product->id;
                    $productImage->name = $imageName;
                    $productImage->hash = $imageHash;
                    $productImage->image_url =  URL::to('storage/' . $imagePath);
                    $productImage->save();
                    $uploadedImages[] = asset(Storage::url($imagePath));
                }
            }

            return response()->json([
                'status'        => true,
                'message'       => "Product updated successfully, including pivot table!",
                'service_types' => Product::SERVICE_TYPES,
                'data'          => $product,
                'product_images'        => $uploadedImages,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */

    public function deleteProduct(string $id)
    {
        try {
            $product = Product::find($id);
            if (! $product) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Product not found!',
                ], 400);
            }

            if ($product->user_id !== Auth::id()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'You are not authorized to delete this product.',
                ], 403);
            }

            // BCR-31: order lines reference the product; deleting it would
            // empty orders a customer has already placed.
            if (OrderItem::where('product_id', $product->id)->exists()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'This product has orders and cannot be deleted. Set it inactive instead.',
                ], 409);
            }

            $existingImages = ProductImage::where('product_id', $id)->get();
            foreach ($existingImages as $existingImage) {
                $imagePath = $existingImage->name;
                if (Storage::exists('product_images/' . $imagePath)) {
                    Storage::delete('product_images/' . $imagePath);
                }
                $existingImage->delete();
            }
            CompanyProductCategoryLinks::where('product_id', $id)->delete();
            Stock::where('product_id', $id)->delete();
            $product->delete();

            return response()->json([
                'status'  => true,
                'message' => 'Product and related data deleted successfully!',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    public function destroy(string $id)
    {
        //
    }
}
