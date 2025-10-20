<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{

    public function viewProfile()
    {
        $user = auth('sanctum')->user();
        if ($user) {
            return response()->json([
                'status'  => true,
                'message' => 'fethed user profile record successfully',
                'data'    => $user,
            ]);
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $user = auth('sanctum')->user();
            if (! $user) {
                return response()->json([
                    'status'  => false,
                    'message' => 'User not authenticated',
                ], 400);
            }



            $accountType = ! empty($request->business_type) ? 'business' : 'user';

            // Validation rules
            // $rules = [
            //     'full_name'    => 'required|string|max:255',
            //     'phone_number' => 'required|string|max:15',
            //     'address'      => 'required|string|max:255',
            //     'city'         => 'required|string|max:255',
            //     'state'        => 'required|string|max:255',
            //     'country'      => 'required|string|max:255',
            //     'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB max
            // ];

            // Additional rules for business users
            // if ($accountType === 'business') {
            //     $rules['business_name'] = 'required|string|max:255';
            //     $rules['business_discription'] = 'required|string|max:500';
            //     $rules['business_license'] = 'required|string|max:255';
            //     $rules['business_logo'] = 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'; // 2MB max
            // }

            // Validate the request
            // $validator = Validator::make($request->all(), $rules);
            // if ($validator->fails()) {
            //     return response()->json([
            //         'status' => false,
            //         'message' => 'Validation error',
            //         'errors' => $validator->errors()->all(),
            //     ], 400);
            // }
            // return $user;

            // $user = User::where('id', auth()->user()->id)->first();

            $user->first_name   = $request->first_name;
            $user->web_token   = $request->web_token;
            $user->phone_number = $request->phone_number;
            $user->address      = $request->address;
            $user->city         = $request->city;
            $user->state        = $request->state;
            $user->country      = $request->country;
            $user->zipcode      = $request->zipcode;

            if ($accountType === 'business') {
                $user->first_name        = $request->first_name;
                $user->business_type        = $request->business_type;
                $user->business_description = $request->business_description;
                $user->business_license     = $request->business_license;
            }

            if ($request->hasFile('profile_image')) {

                if ($user->profile_image) {
                    Storage::delete('profile_images/' . $user->profile_image);
                }

                $image     = $request->file('profile_image');
                $imageName = 'profile_' . time() . '.' . $image->getClientOriginalExtension();
                $image->storeAs('profile_images/', $imageName);
                $user->profile_image = $imageName;
            }


            // if ($accountType === 'business' && $request->hasFile('business_logo')) {

            //     if ($user->business_logo) {
            //         Storage::delete('business_logos/' . $user->business_logo);
            //     }


            //     $logo     = $request->file('business_logo');
            //     $logoName = 'business_logo_' . time() . '.' . $logo->getClientOriginalExtension();
            //     $logo->storeAs('business_logos', $logoName);
            //     $user->business_logo = $logoName;
            // }

            $user->save();

            $responseData = [
                'user_id'       => $user->id,
                'role_id'       => $user->role_id,
                'first_name'    => $user->first_name,
                'web_token'     => $user->web_token,
                'phone_number'  => $user->phone_number,
                'email'         => $user->email,
                'address'       => $user->address,
                'city'          => $user->city,
                'state'         => $user->state,
                'country'       => $user->country,
                'zipcode'       => $user->zipcode,
                'avatar'       => $user->avatar,
                'profile_image' => $user->profile_image ? $user->profile_image : null,
                // 'profile_image' => $user->profile_image ? url('storage/app/private/assets/profile_images/' . $user->profile_image) : null,
            ];

            if ($accountType === 'business' or $user->role_id == 2) {

                // $responseData['role_id']        = $user->role_id;
                $responseData['vendor_type_id']        = $user->vendor_type_id;
                $responseData['category_id']           = $user->category_id;
                $responseData['sub_category_id']       = $user->sub_category_id;
                $responseData['companies_id']          = $user->companies_id;
                $responseData['category_id']           = $user->category_id;
                $responseData['company_product_categories_id']        = $user->company_product_categories_id;
                $responseData['business_status']       = $user->business_status;
                $responseData['category_id']           = $user->category_id;
                // $responseData['first_name']        = $user->first_name;

                $responseData['business_description'] = $user->business_description;
                $responseData['business_license'] = $user->business_license;
                $responseData['business_type']        = $user->business_type;
                // $responseData['business_logo'] = $user->business_logo ? url('storage/app/private/assets/business_logos/' . $user->business_logo) : null;
                $responseData['profile_image'] = $user->profile_image ? $user->profile_image : null;
            }

            return response()->json([
                'status'  => true,
                'message' => 'Profile updated successfully',
                'data'    => $responseData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong',
                'error'   => $e->getMessage(),
            ], 500);
        }
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
