<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function ProductRecords()
    {
        $products = Product::with('user')->get();
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
}
