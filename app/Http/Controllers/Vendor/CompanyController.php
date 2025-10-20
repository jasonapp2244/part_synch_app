<?php

namespace App\Http\Controllers\Vendor;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
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
    // $company = Company::where('sub_category_id',$id)->where('status','active')->get();
    $company = DB::table('companies')
    ->where('sub_category_id', $id)
    ->where('status', 'active')
    ->get();
    if (!$company) {
        return response()->json([
            'status'  => false,
            'message' => "Company not found."
        ], 400);
    }


    return response()->json([
        'status'  => true,
        'message' => "Company retrieved successfully.",
        'data'    => $company
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
