<?php

namespace App\Http\Controllers\Vendor;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;

class ProductCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */


    public function show($id = null)
    {

        if (!$id || !is_numeric($id)) {
            return response()->json([
                'status'  => false,
                'message' => "Invalid or missing company ID."
            ], 400);
        }

        // $productCategories = ProductCategory::where('company_id', $id)->where('status', 'active')->get();

        $productCategories = DB::table('company_product_categories')
        ->where('company_id', $id)
        ->where('status', 'active')
        ->get();

        if ($productCategories->isEmpty()) {
            return response()->json([
                'status'  => false,
                'message' => "No product categories found for this company."
            ], 400);
        }


        return response()->json([
            'status'  => true,
            'message' => "Product categories retrieved successfully.",
            'data'    => $productCategories
        ], 200);
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
