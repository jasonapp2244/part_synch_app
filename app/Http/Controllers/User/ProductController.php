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

        $product = Product::with(['productImages', 'user:id,first_name,last_name,business_name'])->find($id);

        if (is_null($product)) {
            return $this->errorResponse('No product record found.', [], code: 400);
        }

        // Who sells it, by name only: the app titles the chat thread and the
        // report sheet with it. Without it both fell back to the product name
        // ("Say hello to <part>"). No contact details leave the server here.
        $seller = $product->user;
        $product->setRelation('user', null);
        $product->setAttribute('seller', $seller ? [
            'id' => $seller->id,
            'name' => trim((string) $seller->business_name) !== ''
                ? $seller->business_name
                : trim($seller->first_name . ' ' . $seller->last_name),
        ] : null);

        return $this->successResponse('Product details fetched successfully.', $product->makeHidden('user'));
    }

    /**
     * The filter's product category: one id, a comma list ("24,26") or an
     * array, since one category name can stand for a row per company.
     */
    private static function categoryIds(Request $request): array
    {
        $raw = $request->input('com_pro_cat_id');
        $ids = is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    public function getAllCompaniesProductCategory(Request $request)
    {
        try {
            // Each company keeps its own copy of a product category, so the
            // list read "Cylinders, Pistons, Cylinders, Pistons" and choosing
            // one "Cylinders" reached only that copy's company. One entry per
            // name now, carrying every id behind it in `ids` for the next step.
            $categories = ProductCategory::all()
                ->reject(fn ($category) => $category->status === 'inactive')
                ->groupBy(fn ($category) => mb_strtolower(trim((string) $category->product_categories_name)))
                ->map(function ($group) {
                    $first = $group->first();
                    $first->setAttribute('ids', $group->pluck('id')->values()->all());

                    return $first;
                })
                ->values();

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
            $categoryIds = self::categoryIds($request);

            if (!$categoryIds) {
                return response()->json([
                    'status' => false,
                    'message' => 'Product category ID (com_pro_cat_id) is required.',
                ], 400);
            }

            $links = CompanyProductCategoryLinks::with('company')
                ->whereIn('com_pro_cat_id', $categoryIds)
                ->where('is_deleted', 0) // optional: exclude soft deleted
                ->get();

            if ($links->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No companies found for the selected product category.',
                    'data' => []
                ], 400);
            }

            // A link whose company is gone came through as a null entry.
            $companies = $links->pluck('company')->filter()->unique('id')->values();

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
            $categoryIds = self::categoryIds($request);
            $company_id = $request->company_id;

            if (!$categoryIds || !$company_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Both company_id and com_pro_cat_id are required.',
                ], 400);
            }

            $modalNumbers = CompanyProductCategoryLinks::whereIn('com_pro_cat_id', $categoryIds)
                ->where('company_id', $company_id)
                ->where('is_deleted', 0)
                ->whereNotNull('modal_number')
                ->pluck('modal_number')
                ->unique()
                ->values();

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
            $categoryIds    = self::categoryIds($request);
            $modal_number   = $request->modal_number;

            if (!$company_id || !$categoryIds || !$modal_number) {
                return response()->json([
                    'status' => false,
                    'message' => 'company_id, com_pro_cat_id, and modal_number are required.'
                ], 400);
            }

            $productIDs = CompanyProductCategoryLinks::where('company_id', $company_id)
                ->whereIn('com_pro_cat_id', $categoryIds)
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
