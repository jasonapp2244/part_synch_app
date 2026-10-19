<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function UserRecords()
    {
        $users = User::where('role_id', 3)->get();
        return view('admin.view_users_records', compact('users'));
    }

    public function toggleStatus($id)
    {
        $user = User::where('role_id', 3)->findOrFail($id);
        $user->status = $user->status === 'active' ? 'inactive' : 'active';
        $user->save();

        // Blocking also ends their app sessions; signin refuses them after.
        if ($user->status !== 'active') {
            $user->tokens()->delete();
        }

        return redirect()->route('user.records')->with('success', 'User status updated to ' . $user->status . '.');
    }

    public function destroy($id)
    {
        $user = User::where('role_id', 3)->findOrFail($id);
        $user->delete();

        return redirect()->route('user.records')->with('success', 'User deleted successfully.');
    }
}
