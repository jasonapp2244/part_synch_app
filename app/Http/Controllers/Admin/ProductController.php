<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ProductController extends Controller
{
    public function ProductRecords()
    {
        $products = Product::with(['user', 'productImages'])->get();
        return view('admin.view_product_records', compact('products'));
    }

    public function toggleStatus($id)
    {
        $product = Product::findOrFail($id);
        $product->status = $product->status === 'active' ? 'inactive' : 'active';
        $product->save();

        return redirect()->route('product.records')->with('success', 'Product status updated to ' . $product->status . '.');
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        // BCR-31: a sold product stays, so the orders it appears in keep their lines.
        if (OrderItem::where('product_id', $product->id)->exists()
            || Order::where('product_id', $product->id)->exists()) {
            return redirect()->route('product.records')
                ->with('error', 'This product has orders and cannot be deleted. Set it inactive instead.');
        }

        $product->delete();

        return redirect()->route('product.records')->with('success', 'Product deleted successfully.');
    }

    /**
     * Adds photos to a product from the admin panel, stored the way a
     * seller's own uploads are (same folder, content-named, URL on this host)
     * so the app shows them no differently. They stay the seller's photos:
     * `user_id` is the product's owner, not the admin.
     */
    public function uploadImages(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $request->validate([
            'photos'   => 'required|array|min:1|max:8',
            'photos.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
        ], [
            'photos.required' => 'Choose at least one photo.',
            'photos.max'      => 'Up to 8 photos at a time.',
            'photos.*.image'  => 'Each file must be a photo (JPG, PNG or WebP).',
            'photos.*.mimes'  => 'Each file must be a photo (JPG, PNG or WebP).',
            'photos.*.max'    => 'Each photo must be 2 MB or smaller.',
        ]);

        $saved = 0;
        foreach ($request->file('photos') as $photo) {
            $hash = sha1_file($photo->getRealPath());
            // Named from the content and the detected type, never from the
            // name the browser sent.
            $name = $hash . '_' . time() . '.' . $photo->extension();
            if ($photo->storeAs('product_images', $name, 'public') === false) {
                Log::error('Admin product photo was not saved', ['product_id' => $product->id, 'file' => $name]);
                continue;
            }

            $image = new ProductImage();
            $image->user_id    = $product->user_id;
            $image->product_id = $product->id;
            $image->name       = $name;
            $image->hash       = $hash;
            $image->image_url  = URL::to('storage/product_images/' . $name);
            $image->save();
            $saved++;
        }

        if ($saved === 0) {
            return redirect()->route('product.records')->with('error', 'No photo could be saved. Please try again.');
        }

        return redirect()->route('product.records')->with('success', $saved === 1
            ? "1 photo added to {$product->name}."
            : "{$saved} photos added to {$product->name}.");
    }

    /**
     * Removes one photo. A product keeps at least one, the rule the app
     * applies to sellers, because a listing has nothing to show without it.
     */
    public function destroyImage($id, $imageId)
    {
        $product = Product::findOrFail($id);
        $image = ProductImage::where('product_id', $product->id)->findOrFail($imageId);

        if (ProductImage::where('product_id', $product->id)->count() <= 1) {
            return redirect()->route('product.records')
                ->with('error', 'A product needs at least one photo. Add another before removing this one.');
        }

        $image->delete();
        // Same content uploaded twice shares a file name prefix but not the
        // file; only this row pointed at this file.
        if ($image->name && ! ProductImage::where('name', $image->name)->exists()) {
            Storage::disk('public')->delete('product_images/' . $image->name);
        }

        return redirect()->route('product.records')->with('success', "Photo removed from {$product->name}.");
    }
}
