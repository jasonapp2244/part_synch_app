<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorType;
use Illuminate\Http\Request;

class VendorTypeController extends Controller
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
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function vendorType()
    {

        $data = VendorType::all();
        // dd($data->toArray());
        if ($data->isEmpty()) {
            return response()->json([
                'status' => false,
                'data'   => [],
                'error'  => "Record not found",
            ], 400);
        }

        return response()->json([
            'status'  => true,
            'message' => "Data fetched successfully",
            'data'    => $data,
        ], 200);
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
