<?php

namespace App\Http\Controllers\User;

use App\Models\User;
use App\Models\Company;
use App\Models\Product;
use App\Models\Category;
use App\Models\Wishlist;
use App\Models\SubCategory;
use App\Models\BoostedProduct;
use App\Models\BoostPosition;
use Illuminate\Http\Request;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Models\CompanyProductCategoryLinks;

class HomeController extends Controller
{

    public function search(Request $request)
    {
        if ($request->has('keyword') && !empty($request->keyword)) {
            $keyword = '%' . $request->keyword . '%';
            // Add percent signs for LIKE query
            // return $keyword;
            $products = Product::with('ProductImages')
                ->Where('name', 'LIKE', $keyword)
                ->orWhere('description', 'LIKE', $keyword)
                ->orWhere('tags', 'LIKE', $keyword)
                ->orWhere('price', 'LIKE', $keyword)
                ->get();

            $companies = Company::where('company_name', 'LIKE', $keyword)
                ->get();

            $subCategories = SubCategory::where('sub_category_name', 'LIKE', $keyword)
                ->get();

            return response()->json([
                'status' => true,
                'products' => $products,
                'companies' => $companies,
                'subCategories' => $subCategories
            ]);
        }
    }

    public function getCompaniesByProductCategory(Request $request)
    {
        $categoryName = $request->categoryName;

        $companies = Company::whereHas('productCategories', function ($query) use ($categoryName) {
            $query->where('product_categories_name', $categoryName);
        })->get();

        return response()->json([
            'status' => true,
            'message' => 'Companies with product category: ' . $categoryName,
            'data' => $companies
        ]);
    }

    public function getCompanyProducts(Request $request)
    {
    

        $companyId = $request->company_id;
        $company = Company::with(['products' => function ($query) {
            $query->select('id', 'company_id', 'modal_number');
        }])->find($companyId);
        if (!$company) {
            return response()->json([
                'status' => false,
                'message' => 'Company not found.'
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Products for company: ' . $company->company_name,
            'data' => $company->products
        ]);
    }

    public function getHomeScreen()
    {
        $userId = Auth::id();

        $topBoostedProducts = $this->boostedSection('Top Section', 6, $userId);
        $middleBoostedProducts = $this->boostedSection('Middle Section', 8, $userId);
        $bottomBoostedProducts = $this->boostedSection('Bottom Section', 10, $userId);

        // Regular Products (non-boosted)
        $regularProducts = Product::with('productImages')
            ->where('status', 'active')
            ->where('stock_quantity', '>', 0)
            ->whereDoesntHave('boostedProducts', fn ($query) => $query->live())
            ->withExists(['wishlists as is_wishlisted' => function ($query) use ($userId) {
                $query->where('user_id', $userId)->where('type', 'product');
            }])
            ->orderByDesc('is_wishlisted')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $companies = Company::select('id', 'company_name', 'company_image')
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $vendor_shops = User::select('id', 'vendor_type_id', 'business_type', 'business_description', 'business_license', 'business_logo', 'first_name')
            ->where('role_id', 2)
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'User Home Screen Data',
            'data' => [
                'boosted_products' => [
                    'top_section' => $topBoostedProducts,
                    'middle_section' => $middleBoostedProducts,
                    'bottom_section' => $bottomBoostedProducts
                ],
                'regular_products' => $regularProducts,
                'companies' => $companies,
                'vendor_shops' => $vendor_shops
            ]
        ]);
    }

    /**
     * Products boosted into one home-page position right now.
     *
     * A position shows at most display_limit products. When more are paid for
     * than fit (the admin schedules them, but a limit can be lowered later),
     * the order rotates every hour so every paying vendor gets seen, while one
     * buyer scrolling still sees a stable list.
     */
    private function boostedSection(string $positionName, int $defaultLimit, $userId)
    {
        $position = BoostPosition::where('name', $positionName)->first();
        if (! $position) {
            return collect();
        }

        return Product::liveBoosted($position->id)
            ->with('productImages')
            ->where('status', 'active')
            ->where('stock_quantity', '>', 0)
            ->withExists(['wishlists as is_wishlisted' => function ($query) use ($userId) {
                $query->where('user_id', $userId)->where('type', 'product');
            }])
            ->inRandomOrder((int) now()->format('YmdH'))
            ->limit($position->display_limit ?: $defaultLimit)
            ->get();
    }

    public function viewMorePproducts()
    {
        $products = Product::with('ProductImages')
            ->where('status', 'active')
            ->where('stock_quantity', '!=', 0)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'status' => true,
            'message' => 'Products fetched successfully',
            'data' => $products
        ]);
    }

    public function viewMoreCompines()
    {
        $companies = Company::where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'status' => true,
            'message' => 'Companies fetched successfully',
            'data' => $companies
        ]);
    }

    /**
     * The sellers directory. Same public columns as the home feed's sellers
     * strip: this returned each vendor's whole row, push token and private
     * address included (BCR-22).
     */
    public function viewMoreVendors()
    {
        $vendors = User::select('id', 'vendor_type_id', 'business_name', 'business_type', 'business_description', 'business_license', 'business_logo', 'first_name')
            ->where('role_id', 2)->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'status' => true,
            'message' => 'Vendors fetched successfully',
            'data' => $vendors
        ]);
    }

    public function viewMoreConmpanyRelatedProdct(Request $request, $productId = NULL)
    {
        $links = CompanyProductCategoryLinks::with(['company', 'user'])
            ->where('product_id', $productId)
            ->paginate(20);

        $data = $links->through(function ($link) {
            return [
                'company_name'   => $link->company->company_name ?? null,
                'vendor_name'    => $link->user->first_name ?? null,
                'company_image'  => $link->company->company_image ?? null,
                'price'          => $link->custom_price,
                'stock_quantity' => $link->stock_quantity,
                'warranty'       => $link->warranty,
                'discount'       => $link->discount,
            ];
        });

        return response()->json($data);
    }

    public function viewMoreProductRelatedCompany(Request $request, $companyId = NULL)
    {
        $links = CompanyProductCategoryLinks::with(['product.productImages', 'user'])
            ->where('company_id', $companyId)
            ->paginate(20);

        $data = $links->through(function ($link) {
            return [
                'product_name'     => $link->product->name ?? null,
                'description'      => $link->product->description ?? null,
                'modal_number'     => $link->product->modal_number ?? null,
                'sku'              => $link->product->sku ?? null,
                'created_at'       => $link->product->created_at ?? null,
                'price'            => $link->custom_price,
                'stock_quantity'   => $link->stock_quantity,
                'vendor_name'      => $link->user->first_name ?? null,
                'images'           => $link->product->productImages->pluck('image_url'),
            ];
        });

        return response()->json($data);
    }

    /**
     * One seller's catalogue, for their page in the app.
     *
     * Rows carry `id`, `name` and `product_images` in the home feed's shape so
     * each one can be opened (BCR-21); the older keys stay for existing
     * callers. Only active listings of an active vendor account are listed,
     * the same rule as the feed.
     */
    public function viewMoreProductRelatedVendor(Request $request, $vendorId = NULL)
    {
        $products = Product::with('productImages')
            ->where('user_id', $vendorId)
            ->where('status', 'active')
            ->whereHas('user', fn ($query) => $query->where('role_id', 2)->where('status', 'active'))
            ->orderByDesc('created_at')
            ->paginate(20);

        $data = $products->through(function ($product) {
            return [
                'id'               => $product->id,
                'name'             => $product->name,
                'product_images'   => $product->productImages->map(fn ($image) => ['image_url' => $image->image_url])->values(),
                'product_name'     => $product->name,
                'description'      => $product->description,
                'modal_number'     => $product->modal_number,
                'sku'              => $product->sku,
                'service_status'   => $product->service_status,
                'brand'            => $product->brand,
                'price'            => $product->price,
                'stock_quantity'   => $product->stock_quantity,
                'images'           => $product->productImages->pluck('image_url'),
            ];
        });

        return response()->json($data);
    }
}
