<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordResetCodes;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Admin "forgot password": the same 6-digit emailed code the app uses
 * (PasswordResetCodes), issued only for administrator accounts.
 */
class PasswordResetController extends Controller
{
    public function __construct(private PasswordResetCodes $codes)
    {
    }

    public function showRequest()
    {
        return view('admin.auth.forgot-password');
    }

    /**
     * Email a code. The answer is the same whether or not an admin account
     * exists, so the form cannot be used to find out which addresses do.
     */
    public function sendCode(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $admin = User::where('email', $data['email'])->where('role_id', 1)->first();
        if ($admin) {
            $this->codes->issue($admin);
        }

        return redirect()
            ->route('admin.password.reset', ['email' => $data['email']])
            ->with('success', 'If an admin account exists for that email, we have sent it a 6-digit code. It expires in '
                . PasswordResetCodes::MINUTES . ' minutes.');
    }

    public function showReset(Request $request)
    {
        return view('admin.auth.reset-password', ['email' => (string) $request->query('email', '')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // Only admin accounts can be reset here; anything else is "wrong code".
        $isAdmin = User::where('email', $data['email'])->where('role_id', 1)->exists();
        $user = $isAdmin ? $this->codes->reset($data['email'], $data['code'], $data['password']) : null;

        if (! $user) {
            return back()
                ->withInput($request->only('email'))
                ->with('error', 'This code is wrong or has expired. Request a new code and try again.');
        }

        // Signs out "remember me" sessions on other devices.
        $user->setRememberToken(Str::random(60));
        $user->save();

        return redirect()->route('admin.login')
            ->with('success', 'Password updated. Sign in with your new password.');
    }
}
