<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Mail\OtpMail;
use Illuminate\Http\Request;
use App\Mail\ForgotPasswordMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{


    public function signup(Request $request)
    {
        $accountType = !empty($request->business_type) ? 'business' : 'user';

        $rules = [
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|min:6',
            'phone_number' => 'required',
        ];

        if ($accountType === 'business') {
            $rules['first_name']     = 'required|string|max:150';
            $rules['business_type']  = 'required';
            // $rules['vendor_type_id'] = 'required|integer';
            // $rules['category_id']    = 'required|integer';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'data'   => [],
                'error'  => $validator->errors()->all(),
            ], 400);
        }

        try {
            $otp = str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT);

            $user = new User();
            $user->role_id      = ($accountType === 'business') ? 2 : 3;
            $user->first_name   = $request->first_name ?? null;
            $user->email        = $request->email;
            $user->password     = Hash::make($request->password);
            $user->phone_number = $request->phone_number;
            $user->otp          = $otp;

            if ($accountType === 'business') {
                $user->vendor_type_id = $request->vendor_type_id;
                $user->category_id    = $request->category_id;
                $user->business_type  = $request->business_type;
            }

            $user->save();

            try {
                Mail::to($user->email)->send(new OtpMail(
                    ['otp' => $otp],
                    'Mails.otp_generate',
                    'Your OTP Code'
                ));

                $adminEmail = env('Admin_Email');
                Mail::to($adminEmail)->send(new OtpMail(
                    [
                        'first_name'      => $user->first_name,
                        'email'           => $user->email,
                        'phone_number'    => $user->phone_number,
                        'business_type'   => $user->business_type ?? null,
                        'vendor_type_id'  => $user->vendor_type_id ?? null,
                    ],
                    'Mails.new_user_mail',
                    $accountType === 'business' ? 'New Vendor Registered' : 'New User Registered'
                ));
            } catch (\Exception $mailEx) {
                Log::error('Email sending failed: ' . $mailEx->getMessage());
            }

            return response()->json([
                'status'  => true,
                'message' => 'Account created successfully. OTP sent (if email delivery succeeded).',
                'data'    => $user,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'data'   => [],
                'error'  => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Otp Verification.
     */

    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->email;
        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['message' => 'Email not found in our records.'], 400);
        }
        $otp_number = rand(1000, 9999);
        $otp        = str_pad((string) $otp_number, 4, '0', STR_PAD_LEFT);
        $user->otp = $otp;
        if ($user->save()) {
            Mail::to($user->email)->send(new OtpMail(
                ['otp' => $otp],
                'Mails.otp_generate',
                'Your New OTP Code'
            ));
            return response()->json([
                'status' => true,
                'otp' => $otp,
                'message' => 'New OTP sent successfully to your email.',
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Failed to generate and send OTP.',
            ], 500);
        }
    }



    public function otpVerification(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'otp' => 'required|digits:4',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'data'    => [],
                    'message' => 'Validation failed',
                    'error'   => $validator->errors(),
                ], 400);
            }

            $otpCheck = User::where('otp', $request->otp)->first();

            if ($otpCheck) {
                $otpCheck->otp    = null;
                $otpCheck->status = 'active';
                $otpCheck->save();
                if ($otpCheck) {
                    return response()->json([
                        'status'  => true,
                        'data'    => [],
                        'message' => 'OTP verified successfully.',
                        'user'    => $otpCheck,
                    ]);
                }
            } else {
                return response()->json([
                    'status'  => false,
                    'data'    => [],
                    'message' => 'Invalid OTP Or Expire. Please check your email and try again.',
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'data'   => [],
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Registration.
     */


    public function signin(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'email'    => 'required|email',
                'password' => 'required',
                // 'web_token' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'data'   => [],
                    'errors' => $validator->errors()->all(),
                ]);
            }

            $user = User::where('email', $request->email)->first();

            if (! $user) {
                return response()->json([
                    'status' => false,
                    'data'   => [],
                    'error'  => 'Invalid Email or Password',
                ], 400);
            }

            if ($user->otp !== null) {
                return response()->json([
                    'status' => false,
                    'data'   => [],
                    'error'  => 'Your OTP is not verified. Please verify your OTP first.',
                ], 400);
            }

            if (! Hash::check($request->password, $user->password)) {
                return response()->json([
                    'status' => false,
                    'data'   => [],
                    'error'  => 'Invalid Email or Password',
                ], 400);
            }
            if ($user) {
                $user->status    = 'active';
                $user->web_token = $request->web_token;
                $user->save();
            }

            $token = $user->createToken('API Token')->plainTextToken;

            return response()->json([
                'status'    => true,
                'message'   => 'Login successful',
                'user'      => $user,
                'token_type' => 'Bearer',
                'token'     => $token,
                'data'      => [],
            ], 200);
        } catch (\Exception $e) {
            // Handle any unexpected errorsb
            return response()->json([
                'status' => false,
                'data'   => [],
                'error'  => $e->getMessage(),
            ]);
        }
    }
    /**
     * Forgot Password Users.
     */
    public function forgotPassword(Request $request)
    {
        try {

            $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);


            $user = User::where('email', $request->email)->first();


            if (! $user) {
                return response()->json([
                    'status'  => false,
                    'message' => 'User not found',
                ], 400);
            }

            // Ensure business_name and first_name are not null
            $nameForOTP = ! empty($user->business_name) ? $user->business_name : ($user->first_name ?? 'User');

            // Generate OTP
            // $new_password = $nameForOTP . rand(1000, 9999);
            // $nameForOTP = str_replace(' ', '_', $nameForOTP);
            // $new_password = $nameForOTP . rand(1000, 9999);

            $nameForOTP   = strtolower(str_replace(' ', '', $nameForOTP));
            $new_password = $nameForOTP . rand(1000, 9999);


            $user->password = Hash::make($new_password);
            $user->save();


            Mail::to($user->email)->send(new ForgotPasswordMail($user, $new_password));

            return response()->json([
                'status'  => true,
                'code'    => $new_password,
                'message' => 'New Password sent successfully to your email.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        // return $request->all();
        $validator = Validator::make($request->all(), [
            'old_password' => 'required|min:6',
            'password'     => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'data'    => [],
                'message' => 'validation error',
                'error'   => $validator->errors()->all(),
            ], 500);
        }

        // $user = User::find($)
        $user = auth('sanctum')->user();
        if (! $user) {
            return response()->json([
                'status'  => false,
                'message' => 'User not authenticated',
            ], 500);
        }

        $user = User::where('id', $user->id)->first();
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'status'  => false,
                'message' => 'Old password is incorrect',
            ], 400);
        }

        if (Hash::check($request->password, $user->password)) {
            return response()->json([
                'status'  => false,
                'message' => 'New password cannot be the same as the old password',
            ], 400);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Mail::to($user->email)->send(new PasswordChangedMail($user));
        // auth()->logout();

        return response()->json([
            'status'  => true,
            'message' => 'Password updated successfully. Please log in again.',
        ], 400);
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
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Logout successful',
        ]);
    }
}
