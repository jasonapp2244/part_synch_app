<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * The signed-in admin's profile and change-password forms.
     */
    public function show()
    {
        return view('admin.profile', ['admin' => Auth::user()]);
    }

    /**
     * Update the admin's name, email and phone.
     */
    public function update(Request $request)
    {
        $admin = Auth::user();

        $data = $request->validate([
            'first_name'   => ['required', 'string', 'max:150'],
            'last_name'    => ['nullable', 'string', 'max:150'],
            'email'        => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($admin->id)],
            'phone_number' => ['nullable', 'string', 'max:50'],
        ]);

        $admin->fill($data)->save();

        return redirect()->route('admin.profile')->with('success', 'Profile updated.');
    }

    /**
     * Change the admin's password after checking the current one.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ], [
            'current_password.current_password' => 'The current password is incorrect.',
            'password.different'                => 'The new password must be different from the current one.',
        ]);

        $admin = Auth::user();
        $admin->password = Hash::make($request->password);
        $admin->save();

        $request->session()->regenerate();

        return redirect()->route('admin.profile', ['tab' => 'password'])
            ->with('success', 'Password changed.');
    }
}
