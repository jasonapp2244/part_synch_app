<?php

namespace App\Http\Controllers\User;

use App\Models\User;
use App\Models\Company;
use App\Models\Product;
use App\Models\Category;
use App\Models\Wishlist;
use App\Models\SubCategory;
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

        $products = Product::with('productImages')
            ->where('status', 'active')
            ->where('stock_quantity', '>', 0)
            ->withExists(['wishlists as is_wishlisted' => function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('type', 'product');
            }])
            ->orderByDesc('is_wishlisted') // Wishlist products on top
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
                'products' => $products,
                'companies' => $companies,
                'vendor_shops' => $vendor_shops
            ]
        ]);
    }

    public function viewMoreProducts()
    {
        $view_more_products = Product::with('ProductImages')
            ->where('status', 'active')
            ->where('stock_quantity', '!=', 0)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'More companies fetched successfully',
            'data' => $view_more_products
        ]);
    }

    public function viewMoreCompines()
    {
        $view_more_companies = Company::where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get();


        return response()->json([
            'status' => true,
            'message' => 'More companies fetched successfully',
            'data' => $view_more_companies
        ]);
    }

    public function viewMoreVendors()
    {
        $view_more_vendor_shope = User::where('role_id', 2)->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'More vendors fetched successfully',
            'data' => $view_more_vendor_shope
        ]);
    }


    public function viewMoreConmpanyRelatedProdct(Request $request, $productId = NULL)
    {
        $links = CompanyProductCategoryLinks::with(['company', 'user'])
            ->where('product_id', $productId)
            ->get();

        $data = $links->map(function ($link) {
            return [
                'company_name'   => $link->company->company_name ?? null,
                'vendor_name'    => $link->vendor->first_name ?? null,
                'company_image' => $link->company->company_image,
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
            ->get();

        $data = $links->map(function ($link) {
            return [
                'product_name'     => $link->product->name ?? null,
                'description'      => $link->product->description ?? null,
                'modal_number'     => $link->product->modal_number ?? null,
                'sku'              => $link->product->sku ?? null,
                'created_at'       => $link->product->created_at ?? null,
                'price'            => $link->custom_price,
                'stock_quantity'   => $link->stock_quantity,
                'vendor_name'      => $link->vendor->first_name ?? null,
                'vendor_email'     => $link->vendor->email ?? null,
                'images'           => $link->product->productImages->pluck('image_url'), // all images
            ];
        });

        return response()->json($data);
    }

    public function viewMoreProductRelatedVendor(Request $request, $vendorId = NULL)
    {
        $products = Product::with('productImages')
            ->where('user_id', $vendorId)
            ->get();

        $data = $products->map(function ($product) {
            return [
                'product_name'     => $product->name,
                'description'      => $product->description,
                'modal_number' => $product->modal_number,
                'sku' => $product->sku,
                'service_status'=>$product->service_status,
                'brand'=>$product->brand,
                'price'            => $product->price,
                'stock_quantity'   => $product->stock_quantity,
                'images'           => $product->productImages->pluck('image_url'),
            ];
        });

        return response()->json($data);
    }
}
