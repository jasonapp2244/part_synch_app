<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Mail\OtpMail;
use Illuminate\Http\Request;
use App\Mail\ForgotPasswordMail;
use Illuminate\Support\Facades\Cache;
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
            $user->otp_expires_at = now()->addMinutes(10);

            if ($accountType === 'business') {
                $user->vendor_type_id = $request->vendor_type_id;
                $user->category_id    = $request->category_id;
                $user->business_type  = $request->business_type;
            }

            $user->save();

            // Whether the verification email actually left the building. The
            // client has to know: without the code the account can never be
            // verified and can never sign in, so "created successfully" on its
            // own is a lie the user only discovers at the OTP screen.
            $otpEmailSent = true;

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
                $otpEmailSent = false;
                Log::error('Email sending failed: ' . $mailEx->getMessage());
            }

            return response()->json([
                'status'         => true,
                'otp_email_sent' => $otpEmailSent,
                'message'        => $otpEmailSent
                    ? 'Account created successfully. We have emailed you a verification code.'
                    : 'Account created, but we could not email your verification code. Please tap Resend, or contact support.',
                'data'           => $user,
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
        $user->otp_expires_at = now()->addMinutes(10);
        if (! $user->save()) {
            return response()->json([
                'status'  => false,
                'error'   => 'Failed to generate and send OTP.',
                'message' => 'Failed to generate and send OTP.',
            ], 500);
        }

        // Signup already guards its Mail::send; this one did not, so a mailer
        // failure escaped as an unhandled TransportException — a 500 carrying a
        // full stack trace, server paths and the SMTP username, straight to the
        // phone. Report the failure as data instead.
        try {
            Mail::to($user->email)->send(new OtpMail(
                ['otp' => $otp],
                'Mails.otp_generate',
                'Your New OTP Code'
            ));
        } catch (\Exception $mailEx) {
            Log::error('Resend OTP email failed: ' . $mailEx->getMessage());

            return response()->json([
                'status'  => false,
                'error'   => 'We could not send the email just now. Please try again shortly.',
                'message' => 'We could not send the email just now. Please try again shortly.',
            ], 503);
        }

        return response()->json([
            'status'  => true,
            'message' => 'New OTP sent successfully to your email.',
        ]);
    }

    public function otpVerification(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'otp'   => 'required|digits:4',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => false,
                    'data'    => [],
                    'message' => 'Validation failed',
                    'error'   => $validator->errors()->all(),
                ], 400);
            }

            // Scope the code to the account it was issued for.
            //
            // This used to be User::where('otp', $request->otp)->first() — the
            // email the client sends was accepted and then ignored, so ANY
            // pending account holding that code was activated. With four digits
            // and several signups in flight, guessing a code that belongs to
            // somebody else is cheap. Matching on both columns means a code is
            // only ever good for its own account.
            $otpCheck = User::where('email', $request->email)
                ->where('otp', $request->otp)
                ->first();

            if (!$otpCheck) {
                return response()->json([
                    'status'  => false,
                    'data'    => [],
                    'message' => 'Invalid OTP. Please check your email and try again.',
                ], 400);
            }

            if ($otpCheck->otp_expires_at && now()->greaterThan($otpCheck->otp_expires_at)) {
                return response()->json([
                    'status'  => false,
                    'data'    => [],
                    'message' => 'OTP has expired. Please request a new one.',
                ], 400);
            }

            $otpCheck->otp = null;
            $otpCheck->otp_expires_at = null;
            $otpCheck->status = 'active';
            $otpCheck->save();

            return response()->json([
                'status'  => true,
                'data'    => [],
                'message' => 'OTP verified successfully.',
                'user'    => $otpCheck,
            ]);
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
            // `status` is the admin's block switch (and account deletion sets
            // it too). Signing in used to write 'active' back, so a blocked
            // user unblocked themselves by logging in.
            if ($user->status !== 'active') {
                return response()->json([
                    'status' => false,
                    'data'   => [],
                    'error'  => 'Your account has been blocked. Please contact support.',
                ], 403);
            }

            $user->web_token = $request->web_token;
            $user->save();

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
    /**
     * Step 1 of a password reset: email a 6-digit code (BCR-1).
     *
     * This used to generate `<name><4 digits>`, write it over the password and
     * email it, so anyone who knew an address could lock its owner out with
     * one request. Now nothing changes until the code comes back through
     * resetPasswordWithCode(). The answer is the same whether or not the
     * address has an account, so it cannot be used to find out who does.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            $code = (string) random_int(100000, 999999);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($code), 'created_at' => now()]
            );
            Cache::forget(self::resetAttemptsKey($user->email));

            try {
                Mail::to($user->email)->send(new ForgotPasswordMail($user, $code));
            } catch (\Throwable $e) {
                Log::error('Password reset email failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'status'  => true,
            'message' => 'If an account exists for that email, we have sent it a 6-digit code. It expires in '
                . self::RESET_CODE_MINUTES . ' minutes.',
        ]);
    }

    /**
     * Step 2: the code from the email plus the new password. A code lasts
     * RESET_CODE_MINUTES and dies after RESET_CODE_ATTEMPTS wrong guesses;
     * success signs the account out everywhere.
     */
    public function resetPasswordWithCode(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'otp'      => 'required|digits:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $invalid = response()->json([
            'status'  => false,
            'message' => 'This code is wrong or has expired. Request a new one and try again.',
        ], 422);

        $row = DB::table('password_reset_tokens')->where('email', $request->email)->first();
        $attemptsKey = self::resetAttemptsKey($request->email);

        if (! $row
            || now()->subMinutes(self::RESET_CODE_MINUTES)->gt($row->created_at)
            || Cache::get($attemptsKey, 0) >= self::RESET_CODE_ATTEMPTS) {
            return $invalid;
        }

        if (! Hash::check($request->otp, $row->token)) {
            Cache::put($attemptsKey, Cache::get($attemptsKey, 0) + 1, now()->addMinutes(self::RESET_CODE_MINUTES));

            return $invalid;
        }

        $user = User::where('email', $request->email)->first();
        if (! $user) {
            return $invalid;
        }

        $user->password = Hash::make($request->password);
        $user->save();
        $user->tokens()->delete();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        Cache::forget($attemptsKey);

        return response()->json([
            'status'  => true,
            'message' => 'Your password has been changed. Sign in with the new one.',
        ]);
    }

    private const RESET_CODE_MINUTES = 15;

    private const RESET_CODE_ATTEMPTS = 5;

    private static function resetAttemptsKey(string $email): string
    {
        return 'password-reset-attempts:' . sha1(strtolower($email));
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
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Logout successful',
        ]);
    }
}
