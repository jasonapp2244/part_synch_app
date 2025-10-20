<?php

namespace App\Http\Controllers\Vendor;

use App\Models\Stock;
use App\Models\Product;
use Illuminate\Support\Str;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
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
            $products = Product::with('productImages')
                ->where('user_id',  $id)
                ->orderBy('id', 'desc')
                ->paginate();
            return response()->json([
                'status' => false,
                'message' => 'You are not authorized to access this resource.',
            ], 400);
        }
        $products = Product::with('productImages')
            ->where('user_id',  $user->id)
            ->orderBy('id', 'desc')
            ->paginate();

        // Fetch products using user ID or fallback to provided ID


        // Check if any products found
        if ($products->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No products found.',
            ], 400);
        }

        return response()->json([
            'status' => true,
            'message' => 'Products fetched successfully.',
            'data' => $products,
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
            // $request->validate([
            //     'name'                          => 'required|string|max:255|unique:products,name',
            //     'vendor_id'                     => 'nullable|exists:vendor_type,id',
            //     'category_id'                   => 'nullable|exists:categories,id',
            //     'sub_category_id'               => 'nullable|exists:sub_categories,id',
            //     'company_id'                    => 'nullable|exists:companies,id',
            //     'company_product_categories_id' => 'nullable|exists:company_product_categories,id',
            // 'sku'                           => 'nullable|string|max:100',
            //     'barcode'                       => 'nullable|string|max:255',
            //     'warranty'                      => 'nullable|string|max:255',
            //     'description'                   => 'nullable|string',
            //     'price'                         => 'nullable|numeric|min:0',
            //     'modal_number'                  => 'nullable|string|max:100',
            //     'expire_date'                   => 'nullable|date',
            //     'new_stock'                     => 'nullable|numeric|min:0',
            //     'status'                        => 'nullable|in:active,inactive',
            //     'product_pic'                   => 'nullable|array',
            //     'product_pic.*'                 => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            // ]);


            $modalNumber = strtolower(trim($request->modal_number));
            $name = strtolower(trim($request->name));
            // $sku = strtoupper(trim($request->sku));
            // return $sku;
            $cleanName = preg_replace('/[^a-z0-9]/', '', strtolower($name));
            $cleanName = substr($cleanName, 0, 10); // Limit to 10 chars for brevi
            $uniqueSuffix = strtoupper(dechex(time() % 100000)) . rand(10000, 99999); // e.g. "3E8A7"
            $sku =  strtoupper($cleanName) . '-' . $uniqueSuffix;
            // return $sku;
            $existingSku = Product::whereRaw('LOWER(sku) = ?', [$uniqueSuffix])->first();
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
            $product->modal_number                  = $modalNumber;
            $product->expire_date                   = $request->expire_date;
            $product->status                        = $request->status ?? 'active';
            $product->stock_quantity                = 0;
            $product->save();

            CompanyProductCategoryLinks::create([
                'company_id'     => $request->company_id,
                'com_pro_cat_id' => $request->company_product_categories_id,
                'product_id'     => $product->id,
                'modal_number'   => $modalNumber,
                'product_number' => $sku,
                // 'custom_price'   => $request->custom_price,
                'original_price' => $product->price,
                'stock_quantity' => $request->new_stock ?? 0,
                'warranty'       => $product->warranty,
                'created_by'     => $request->user_id,
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

                    $image->storeAs('product_images', $imageName);

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

            return response()->json([
                'message'        => 'Product added successfully!',
                'status'         => true,
                'service_status' => Product::SERVICE_STATUS,
                'service_types'  => Product::SERVICE_TYPES,
                'data'           => $product,
                'product_images'         => $uploadedImages,
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

            $product->user_id                       = $request->user_id ?? $product->user_id;
            $product->vendor_id                     = $request->vendor_id ?? $product->vendor_id;
            $product->category_id                   = $request->category_id ?? $product->category_id;
            $product->sub_category_id               = $request->sub_category_id ?? $product->sub_category_id;
            $product->company_product_categories_id = $request->company_product_categories_id ?? $product->company_product_categories_id;
            $product->service_status                = $request->service_status ?? $product->service_status;
            $product->service_type                  = $request->service_type ?? $product->service_type;
            $product->name                          = $request->name ?? $product->name;
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

                $product->stock_quantity += $newStock;
                $product->save();
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
                $link->created_by     = $request->user_id;
            }

            // Set all pivot fields only if they exist in request, otherwise retain old value
            $link->modal_number          = $request->modal_number         ?? $link->modal_number;
            // $link->product_number        = $request->product_number       ?? $link->product_number;
            $link->custom_price          = $request->custom_price         ?? $link->custom_price;
            $link->original_price        = $request->price                ?? $link->original_price;
            $link->stock_quantity        = $request->new_stock            ?? $link->stock_quantity;
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


            $uploadedImages = [];
            $existingImages = ProductImage::where('product_id', $id)->get();

            if ($request->hasFile('product_pic')) {
                foreach ($request->file('product_pic') as $image) {
                    $imageHash = sha1_file($image->getRealPath());
                    $imageName = $imageHash . '_' . time() . "." . $image->getClientOriginalExtension();
                    $imagePath = 'product_images/' . $imageName;

                    $existingImage = $existingImages->firstWhere(function ($existingImage) use ($imageHash) {
                        return $existingImage->hash === $imageHash;
                    });

                    if ($existingImage) {
                        $existingImage->name = $imageName;
                        $existingImage->image_url = URL::to('storage/' . $imagePath);
                        $existingImage->save();
                        $uploadedImages[] = asset(Storage::url($imagePath));
                    } else {
                        $image->storeAs('product_images', $imageName);
                        $productImage = new ProductImage();
                        $productImage->user_id = $request->user_id;
                        $productImage->product_id = $product->id;
                        $productImage->name = $imageName;
                        $productImage->hash = $imageHash;
                        $productImage->image_url =  URL::to('storage/' . $imagePath);
                        // $productImage->image_url = Storage::url($imagePath);
                        $productImage->save();
                        $uploadedImages[] = asset(Storage::url($imagePath));
                    }
                }

                foreach ($existingImages as $existingImage) {
                    $shouldDelete = true;
                    foreach ($request->file('product_pic') as $image) {
                        $imageHash = sha1_file($image->getRealPath());
                        if ($existingImage->hash === $imageHash) {
                            $shouldDelete = false;
                            break;
                        }
                    }

                    if ($shouldDelete) {
                        Storage::delete('product_images/' . basename($existingImage->image_url));
                        $existingImage->delete();
                    }
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
