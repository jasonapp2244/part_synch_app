<?php

namespace App\Http\Controllers\Vendor;


use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

use function PHPUnit\Framework\isFalse;

class VendorMapController extends Controller
{
    /**
     * Update the specified resource in storage.
     */
    public function updateVendor(Request $request)
    {

        $validatedData = $request->validate([
            'user_id' => 'required|exists:users,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);


        $user = User::findOrFail($validatedData['user_id']);

        if ($user->role_id === 2 || $user->role_id === 3) {

            $user->latitude = $validatedData['latitude'];
            $user->longitude = $validatedData['longitude'];


            if ($user->role_id === 3) {
                $user->address = $request->address;
            }


            if ($user->save()) {
                return response()->json([
                    'status' => true,
                    'message' => 'location updated successfully',
                    'data' => $user,
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'location not updated',
                ]);
            }
        }


        return response()->json([
            'status' => false,
            'message' => 'Unauthorized user role',
        ]);
    }


    /**
     * Fetch all vendors with a location.
     */
    public function getVendors()
    {

        // dd($requst);
        $vendors = User::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        return response()->json(['vendors' => $vendors]);
    }

    /**
     * Get nearest vendors based on latitude & longitude.
     */
    //
    public function getNearestVendors(Request $request)
    {
        dd('testing');
        $validatedData = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $latitude = $validatedData['latitude'];
        $longitude = $validatedData['longitude'];

        $vendors = User::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw(
                "*,
        (6371 * acos(cos(radians(?)) * cos(radians(latitude))
        * cos(radians(longitude) - radians(?))
        + sin(radians(?)) * sin(radians(latitude)))) AS distance",
                [$latitude, $longitude, $latitude]
            )
            ->having('distance', '<', 1000)
            ->orderBy('distance', 'asc')
            ->limit(10)
            ->get();
        return response()->json(['nearest_vendors' => $vendors]);
    }


    public function destroy(string $id)
    {
        //
    }
}
