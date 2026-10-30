<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View { return view('admin.profile.edit', ['user' => $request->user()]); }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email,'.$request->user()->id],
            'current_password' => ['required', 'current_password'],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
        ]);
        $user = $request->user();
        $user->name = $data['name'];
        $user->email = $data['email'];
        if (! empty($data['password'])) $user->password = Hash::make($data['password']);
        $user->save();
        $request->session()->regenerate();
        AdminAudit::record('profile.updated', 'Updated own admin profile.', $user, ['password_changed' => ! empty($data['password'])]);
        return back()->with('success', 'Your profile was updated.');
    }
}
