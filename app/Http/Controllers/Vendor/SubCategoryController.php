<?php

namespace App\Http\Controllers\Vendor;

use App\Models\SubCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;


class SubCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() {}

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
    public function show($categoryId = null)
    {



        if (!$categoryId || !is_numeric($categoryId)) {
            return response()->json([
                'status'  => false,
                'message' => "Invalid or missing category ID."
            ], 400);
        }

        $subCategories = DB::table('sub_categories')
        ->where('category_id', $categoryId)
        ->where('status', 'active')
        ->get();
        // $subCategories = SubCategory::where('category_id', $categoryId)
        //     ->where('status', 'active')
        //     ->get();

        if ($subCategories->isEmpty()) {
            return response()->json([
                'status'  => false,
                'message' => "No subcategories found for this category."
            ], 400);
        }

        return response()->json([
            'status'  => true,
            'message' => "Subcategories retrieved successfully.",
            'data'    => $subCategories
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
