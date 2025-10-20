<?php

namespace App\Http\Controllers\User;

use App\Traits\ApiResponseTrait;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use App\Models\CompanyProductCategoryLinks;

class ProductController extends Controller
{
    use ApiResponseTrait;
    public function getProductDetails(Request $request, $id = null)
    {
        if (!$id) {
            return $this->errorResponse('Product ID is required.', [], 400);
        }

        $product = Product::with('productImages')->find($id);

        return is_null($product)
            ? $this->errorResponse('No product record found.', [], code: 400)
            : $this->successResponse('Product details fetched successfully.', $product);
    }

    public function getAllCompaniesProductCategory(Request $request)
    {
        try {
            $categories = ProductCategory::all();

            if ($categories->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No product categories found.',
                    'data' => []
                ], 400);
            }

            return response()->json([
                'status' => true,
                'message' => 'Product categories retrieved successfully.',
                'data' => $categories
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while retrieving product categories.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getAllRelatedCompaniesProductCategory(Request $request)
    {
        try {
            $com_pro_cat_id = $request->com_pro_cat_id;

            if (!$com_pro_cat_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Product category ID (com_pro_cat_id) is required.',
                ], 400);
            }

            $links = CompanyProductCategoryLinks::with('company')
                ->where('com_pro_cat_id', $com_pro_cat_id)
                ->where('is_deleted', 0) // optional: exclude soft deleted
                ->get();

            if ($links->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No companies found for the selected product category.',
                    'data' => []
                ], 400);
            }

            $companies = $links->pluck('company')->unique('id')->values();

            return response()->json([
                'status' => true,
                'message' => 'Companies retrieved successfully for the selected product category.',
                'data' => $companies
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while retrieving companies.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getModalNumbersByCategoryAndCompany(Request $request)
    {
        try {
            $com_pro_cat_id = $request->com_pro_cat_id;
            $company_id = $request->company_id;

            if (!$com_pro_cat_id || !$company_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Both company_id and com_pro_cat_id are required.',
                ], 400);
            }

            $modalNumbers = CompanyProductCategoryLinks::where('com_pro_cat_id', $com_pro_cat_id)
                ->where('company_id', $company_id)
                ->where('is_deleted', 0)
                ->pluck('modal_number');

            if ($modalNumbers->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No modal numbers found for the given company and category.',
                    'data' => []
                ], 400);
            }

            return response()->json([
                'status' => true,
                'message' => 'Modal numbers retrieved successfully.',
                'data' => $modalNumbers
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while retrieving modal numbers.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getProductsByCompanyCategoryModal(Request $request)
    {
        try {
            $company_id     = $request->company_id;
            $com_pro_cat_id = $request->com_pro_cat_id;
            $modal_number   = $request->modal_number;

            if (!$company_id || !$com_pro_cat_id || !$modal_number) {
                return response()->json([
                    'status' => false,
                    'message' => 'company_id, com_pro_cat_id, and modal_number are required.'
                ], 400);
            }

            $productIDs = CompanyProductCategoryLinks::where('company_id', $company_id)
                ->where('com_pro_cat_id', $com_pro_cat_id)
                ->where('modal_number', $modal_number)
                ->where('is_deleted', 0)
                ->pluck('product_id');
            if ($productIDs->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No products found for the selected filters.',
                    'data' => []
                ], 400);
            }

            $products = Product::with('productImages')
                ->whereIn('id', $productIDs)
                ->where('is_active', 1)
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Products retrieved successfully.',
                'data' => $products
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while retrieving products.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
