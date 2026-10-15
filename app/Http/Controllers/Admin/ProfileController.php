<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
     * Upload a new profile photo, replacing (and deleting) the old one.
     */
    public function updatePhoto(Request $request)
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=64,min_height=64,max_width=4000,max_height=4000'],
        ], [
            'photo.max'        => 'The photo must be 2 MB or smaller.',
            'photo.dimensions' => 'The photo must be between 64 and 4000 pixels on each side.',
        ]);

        $admin = Auth::user();
        $file = $request->file('photo');
        $name = 'admin_' . $admin->id . '_' . time() . '.' . $file->extension();

        if (! $file->storeAs('profile_images', $name, 'public')) {
            return redirect()->route('admin.profile')
                ->withErrors(['photo' => 'The photo could not be saved. Please try again.']);
        }

        $old = $admin->profile_image;
        $admin->profile_image = $name;
        $admin->save();

        if ($old && $old !== $name) {
            Storage::disk('public')->delete('profile_images/' . basename($old));
        }

        return redirect()->route('admin.profile')->with('success', 'Profile photo updated.');
    }

    /**
     * Remove the profile photo and go back to the default avatar.
     */
    public function destroyPhoto()
    {
        $admin = Auth::user();

        if ($admin->profile_image) {
            Storage::disk('public')->delete('profile_images/' . basename($admin->profile_image));
            $admin->profile_image = null;
            $admin->save();
        }

        return redirect()->route('admin.profile')->with('success', 'Profile photo removed.');
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
