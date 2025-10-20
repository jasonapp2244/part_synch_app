<?php

namespace App\Http\Controllers\Vendor;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        // $categories = Category::all();
        $categories = DB::table('categories')->get();
        if ($categories->isEmpty()) {
            return response()->json([
                'status'  => false,
                'message' => "No records found."
            ], 404);
        }

        return response()->json([
            'status'    => true,
            'message'   => "Categories retrieved successfully.",
            'data'      => $categories
        ], 200);
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
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.

     */
    public function edit(string $id)
    {
        //
    }
#Thanks Bhai Talah.

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
