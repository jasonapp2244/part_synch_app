<?php

namespace App\Http\Controllers\Vendor;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class VendorMapController extends Controller
{
    /**
     * What a map pin may show about a seller. These endpoints used to return
     * whole `users` rows (email, phone, home address, push tokens) for every
     * located account, customers and admins included.
     */
    private const PIN_COLUMNS = [
        'id', 'first_name', 'last_name', 'business_name', 'business_logo',
        'profile_image', 'city', 'state', 'latitude', 'longitude',
    ];

    /**
     * Update the signed-in vendor's own location.
     *
     * This used to take a `user_id` from the request, so any vendor could move
     * any account (and read it back in full).
     */
    public function updateVendor(Request $request)
    {
        $validatedData = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $user = Auth::user();
        $user->latitude = $validatedData['latitude'];
        $user->longitude = $validatedData['longitude'];

        if (! $user->save()) {
            return response()->json([
                'status' => false,
                'message' => 'location not updated',
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'location updated successfully',
            'data' => [
                'id' => $user->id,
                'latitude' => $user->latitude,
                'longitude' => $user->longitude,
            ],
        ]);
    }

    /**
     * Fetch all active sellers with a location.
     */
    public function getVendors()
    {
        $vendors = $this->sellers()->get(self::PIN_COLUMNS);

        return response()->json(['vendors' => $vendors]);
    }

    /**
     * Get nearest sellers based on latitude & longitude.
     */
    public function getNearestVendors(Request $request)
    {
        $validatedData = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $latitude = $validatedData['latitude'];
        $longitude = $validatedData['longitude'];

        $vendors = $this->sellers()
            ->select(self::PIN_COLUMNS)
            ->selectRaw(
                "(6371 * acos(cos(radians(?)) * cos(radians(latitude))
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

    private function sellers()
    {
        return User::where('role_id', 2)
            ->where('status', 'active')
            ->where('business_status', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');
    }
}
