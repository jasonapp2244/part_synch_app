<?php

namespace App\Http\Controllers\User;

use App\Models\Product;
use App\Models\BoostedProduct;
use App\Models\BoostPosition;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BoostedProductsController extends Controller
{
    /**
     * Get boosted products slider for all sections (Top, Middle, Bottom)
     */
    public function getBoostedProductsSlider()
    {
        $userId = Auth::id();

        $positions = ['Top Section', 'Middle Section', 'Bottom Section'];
        $result = [
            'top' => [],
            'middle' => [],
            'bottom' => []
        ];

        foreach ($positions as $positionName) {
            $position = BoostPosition::where('name', $positionName)->first();

            if (!$position) {
                continue;
            }

            $boostedProducts = Product::liveBoosted($position->id)
                ->with('productImages')
                ->where('status', 'active')
                ->where('stock_quantity', '>', 0)
                ->withExists(['wishlists as is_wishlisted' => function ($query) use ($userId) {
                    $query->where('user_id', $userId)->where('type', 'product');
                }])
                ->inRandomOrder((int) now()->format('YmdH'))
                ->limit($position->display_limit ?? 10)
                ->get();

            $key = strtolower(str_replace(' Section', '', $positionName));
            $result[$key] = $boostedProducts;
        }

        return response()->json([
            'status' => true,
            'message' => 'Boosted products by position',
            'data' => $result
        ]);
    }

    /**
     * Get all boosted products (all sections combined)
     */
    public function getAllBoostedProducts()
    {
        $userId = Auth::id();

        $boostedProducts = Product::liveBoosted()
            ->with(['productImages', 'boostedProducts.vendorBoost.position'])
            ->where('status', 'active')
            ->where('stock_quantity', '>', 0)
            ->withExists(['wishlists as is_wishlisted' => function ($query) use ($userId) {
                $query->where('user_id', $userId)->where('type', 'product');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'All boosted products',
            'data' => $boostedProducts
        ]);
    }
}

