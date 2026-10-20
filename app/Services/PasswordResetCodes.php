<?php

namespace App\Services;

use App\Mail\ForgotPasswordMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The 6-digit password reset code (BCR-1), shared by the app API and the
 * admin panel. A code is stored hashed, lasts MINUTES, dies after ATTEMPTS
 * wrong guesses, and nothing about the account changes until it comes back.
 */
class PasswordResetCodes
{
    public const MINUTES = 15;

    public const ATTEMPTS = 5;

    /** Create a code for $user and email it. A mail failure is logged, not thrown. */
    public function issue(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($code), 'created_at' => now()]
        );
        Cache::forget(self::attemptsKey($user->email));

        try {
            Mail::to($user->email)->send(new ForgotPasswordMail($user, $code));
        } catch (\Throwable $e) {
            Log::error('Password reset email failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Check the code and, when it is right, set the new password, sign the
     * account out of the app everywhere and use the code up. Returns the user,
     * or null for a wrong, expired or exhausted code.
     */
    public function reset(string $email, string $code, string $newPassword): ?User
    {
        $row = DB::table('password_reset_tokens')->where('email', $email)->first();
        $attemptsKey = self::attemptsKey($email);

        if (! $row
            || now()->subMinutes(self::MINUTES)->gt($row->created_at)
            || Cache::get($attemptsKey, 0) >= self::ATTEMPTS) {
            return null;
        }

        if (! Hash::check($code, $row->token)) {
            Cache::put($attemptsKey, Cache::get($attemptsKey, 0) + 1, now()->addMinutes(self::MINUTES));

            return null;
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            return null;
        }

        $user->password = Hash::make($newPassword);
        $user->save();
        $user->tokens()->delete();

        DB::table('password_reset_tokens')->where('email', $email)->delete();
        Cache::forget($attemptsKey);

        return $user;
    }

    private static function attemptsKey(string $email): string
    {
        return 'password-reset-attempts:' . sha1(strtolower($email));
    }
}
